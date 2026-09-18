<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');

// Suppress deprecations to ensure JSON purity
if (defined('E_DEPRECATED')) {
    error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

try {
    // Read input and restore session from body token, cookie, or header
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: [];
    ensureSession($input);

    // Ensure user is logged in
    if (!isLoggedIn()) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please log in.']);
        exit();
    }

    $message = trim($input['message'] ?? $_POST['message'] ?? '');

    if (empty($message)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Please provide a description of the issue.']);
        exit();
    }

    // Fetch categories from DB
    $categories = [];
    try {
        $categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $categories = [];
    }

    if (empty($categories)) {
        $categories = [
            ['category_id' => 1, 'category_name' => 'General / Infrastructure']
        ];
    }

    $apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ($_SERVER['GEMINI_API_KEY'] ?? ''));
    $extracted = null;

    if (!empty($apiKey)) {
        // Call Gemini API for structured extraction
        $catList = implode(", ", array_map(function($c) { 
            return $c['category_name'] . " (ID: " . $c['category_id'] . ")"; 
        }, $categories));
        
        $prompt = "You are an AI campus issue assistant for FixMyCampus. Extract structured ticket details from this student message: \"{$message}\".
Available Categories: {$catList}.
Priorities allowed: low, medium, high, critical.

Return ONLY a valid JSON object (no markdown formatting, no backticks) with keys:
- title (concise summary, max 8-10 words)
- category_id (integer matching available categories)
- category_name (string matching category)
- location (specific campus building, floor, room, lab, or area extracted)
- priority (low, medium, high, or critical)
- description (clear detailed description of the reported issue)";

        // Try gemini-1.5-flash or gemini-2.0-flash
        $models = ['gemini-1.5-flash', 'gemini-2.0-flash'];
        foreach ($models as $m) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . $apiKey;
            $postData = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $res = curl_exec($ch);
            if (function_exists('curl_close') && PHP_VERSION_ID < 80000 && is_resource($ch)) {
                curl_close($ch);
            }

            if ($res) {
                $jsonRes = json_decode($res, true);
                $text = $jsonRes['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $text = preg_replace('/^```json\s*/i', '', $text);
                $text = preg_replace('/```\s*$/i', '', $text);
                $text = trim($text);
                $parsed = json_decode($text, true);
                if ($parsed && !empty($parsed['title'])) {
                    $extracted = $parsed;
                    break;
                }
            }
        }
    }

    // Fallback Rule-Based Natural Language Parser if Gemini key is missing or API call fails
    if (!$extracted) {
        $msgLower = strtolower($message);
        
        // Category Matching
        $matchedCatId = $categories[0]['category_id'];
        $matchedCatName = $categories[0]['category_name'];
        
        $catKeywords = [
            'Furniture' => ['chair', 'table', 'desk', 'bench', 'board', 'whiteboard', 'podium', 'furniture', 'seat', 'stool', 'drawer'],
            'Electrical' => ['light', 'tube', 'switch', 'power', 'socket', 'plug', 'fan', 'ac', 'electricity', 'wire', 'spark', 'bulb', 'hvac', 'cooler', 'voltage'],
            'Plumbing' => ['water', 'pipe', 'leak', 'flooding', 'tap', 'sink', 'drain', 'toilet', 'flush', 'washroom', 'restroom', 'shower', 'basin', 'overflow'],
            'Cleanliness' => ['clean', 'trash', 'garbage', 'bin', 'dirty', 'stink', 'smell', 'dust', 'waste', 'litter', 'spill', 'sanitize'],
            'IT' => ['wifi', 'internet', 'network', 'router', 'computer', 'pc', 'projector', 'hdmi', 'screen', 'software', 'lab pc', 'lan', 'ethernet'],
            'Safety' => ['lock', 'key', 'cctv', 'camera', 'fire', 'hazard', 'emergency', 'alarm', 'security', 'danger', 'extinguisher', 'theft'],
            'Infrastructure' => ['wall', 'door', 'window', 'glass', 'ceiling', 'roof', 'floor', 'tile', 'stair', 'crack', 'building', 'step', 'railing']
        ];

        foreach ($catKeywords as $key => $kwList) {
            foreach ($kwList as $kw) {
                if (strpos($msgLower, $kw) !== false) {
                    foreach ($categories as $c) {
                        if (stripos($c['category_name'], $key) !== false || stripos($key, $c['category_name']) !== false) {
                            $matchedCatId = $c['category_id'];
                            $matchedCatName = $c['category_name'];
                            break 2;
                        }
                    }
                }
            }
        }

        // Priority Detection
        $priority = 'medium';
        if (preg_match('/(urgent|danger|hazard|fire|flood|spark|emergency|critical|severe|immediately|asap)/i', $message)) {
            $priority = 'critical';
        } elseif (preg_match('/(broken|not working|cannot|impossible|disrupt|high|no wifi|no power|loud|buzzing|cracked)/i', $message)) {
            $priority = 'high';
        } elseif (preg_match('/(minor|aesthetic|slow|dirty|small|low|slight)/i', $message)) {
            $priority = 'low';
        }

        // Location Extraction
        $location = 'Main Campus';
        $locationParts = [];

        // Check for specific room/class indicators
        if (preg_match('/\b([a-z0-9\-]+)\s*(class|classroom|lab|laboratory|hall|auditorium|block|room)\b/i', $message, $m)) {
            $locationParts[] = strtoupper($m[1]) . ' ' . ucfirst($m[2]);
        } elseif (preg_match('/\b(class|classroom|lab|laboratory|hall|auditorium|block|room)\s*([a-z0-9\-]+)\b/i', $message, $m)) {
            $locationParts[] = ucfirst($m[1]) . ' ' . strtoupper($m[2]);
        }

        // Check for floor indicators
        if (preg_match('/\b(ground|1st|first|2nd|second|3rd|third|4th|fourth|top|basement)\s*(floor)?\b/i', $message, $m)) {
            $locationParts[] = ucfirst($m[1]) . ' Floor';
        }

        // Check for building indicators (match up to 3 words before building keyword)
        if (preg_match('/\b([a-z0-9\-]+(?:\s+[a-z0-9\-]+){0,2})\s+(building|block|wing|tower|hostel|library|canteen|cafeteria)\b/i', $message, $m)) {
            $bldCandidate = trim($m[1] . ' ' . $m[2]);
            $bldCandidate = preg_replace('/^(in|at|near|on|the|from)\s+/i', '', $bldCandidate);
            if (strlen($bldCandidate) > 3) {
                $locationParts[] = ucwords(strtolower($bldCandidate));
            }
        }

        if (!empty($locationParts)) {
            $location = implode(', ', array_unique($locationParts));
        } elseif (preg_match('/(in|at|near|outside)\s+([A-Za-z0-9\s\-]+?)(?=\s+(is|has|was|not|are|with|\.|\,|$))/i', $message, $locMatches)) {
            $locCandidate = trim($locMatches[2]);
            if (strlen($locCandidate) > 2 && strlen($locCandidate) < 50) {
                $location = ucwords(strtolower($locCandidate));
            }
        }

        // Title Generation: Extract core problem and location
        $cleanTitle = '';
        if (preg_match('/(broken|damaged|leaking|flickering|cracked|stuck|dead|faulty)\s+([a-z0-9\s]+?)(?=\s+(in|at|on|near|\.|\,|$))/i', $message, $tm)) {
            $cleanTitle = ucfirst(trim($tm[1] . ' ' . $tm[2]));
        } else {
            $words = explode(' ', $message);
            $cleanTitle = ucfirst(implode(' ', array_slice($words, 0, 6)));
            if (count($words) > 6) $cleanTitle .= '...';
        }

        $extracted = [
            'title'         => $cleanTitle,
            'category_id'   => $matchedCatId,
            'category_name' => $matchedCatName,
            'location'      => $location,
            'priority'      => $priority,
            'description'   => $message
        ];
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'data'    => $extracted
    ]);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error'   => 'AI Assistant error: ' . $e->getMessage()
    ]);
}
