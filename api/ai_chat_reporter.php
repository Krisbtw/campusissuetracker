<?php
session_start();
header('Content-Type: application/json');

require_once '../includes/auth_check.php';
require_once '../config/db.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized access. Please log in.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? $_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(['error' => 'Please provide a description of the issue.']);
    exit();
}

// Fetch categories from DB
$categories = $pdo->query("SELECT category_id, category_name FROM categories")->fetchAll();

$apiKey = getenv('GEMINI_API_KEY') ?: '';
$extracted = null;

if (!empty($apiKey)) {
    // Call Gemini API for structured extraction
    $catList = implode(", ", array_map(function($c) { return $c['category_name'] . " (ID: " . $c['category_id'] . ")"; }, $categories));
    
    $prompt = "You are an AI campus issue assistant for FixMyCampus. Extract structured ticket details from this student message: \"{$message}\".
Available Categories: {$catList}.
Priorities allowed: low, medium, high, critical.

Return ONLY a valid JSON object (no markdown formatting, no backticks) with keys:
- title (concise summary, max 10 words)
- category_id (integer matching available categories)
- category_name (string matching category)
- location (specific campus building, room, lab, or area extracted)
- priority (low, medium, high, or critical)
- description (clear detailed description of the reported issue)";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $res = curl_exec($ch);
    curl_close($ch);

    if ($res) {
        $jsonRes = json_decode($res, true);
        $text = $jsonRes['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*/i', '', $text);
        $text = trim($text);
        $parsed = json_decode($text, true);
        if ($parsed && !empty($parsed['title'])) {
            $extracted = $parsed;
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
        'Electrical' => ['light', 'tube', 'switch', 'power', 'socket', 'plug', 'fan', 'ac', 'electricity', 'wire', 'spark', 'bulb', 'hvac'],
        'Plumbing' => ['water', 'pipe', 'leak', 'flooding', 'tap', 'sink', 'drain', 'toilet', 'flush', 'washroom', 'restroom', 'shower'],
        'Cleanliness' => ['clean', 'trash', 'garbage', 'bin', 'dirty', 'stink', 'smell', 'dust', 'waste', 'litter'],
        'Infrastructure' => ['wall', 'door', 'window', 'glass', 'ceiling', 'roof', 'floor', 'tile', 'stair', 'crack', 'building'],
        'IT' => ['wifi', 'internet', 'network', 'router', 'computer', 'pc', 'projector', 'hdmi', 'screen', 'software', 'lab pc'],
        'Furniture' => ['chair', 'table', 'desk', 'bench', 'board', 'whiteboard', 'podium', 'furniture', 'seat'],
        'Safety' => ['lock', 'key', 'cctv', 'camera', 'fire', 'hazard', 'emergency', 'alarm', 'security', 'danger']
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
    if (preg_match('/(urgent|danger|hazard|fire|flood|spark|emergency|critical|severe)/i', $message)) {
        $priority = 'critical';
    } elseif (preg_match('/(broken|not working|cannot|impossible|disrupt|high|no wifi|no power|loud|buzzing)/i', $message)) {
        $priority = 'high';
    } elseif (preg_match('/(minor|aesthetic|slow|dirty|small|low)/i', $message)) {
        $priority = 'low';
    }

    // Location Extraction
    $location = 'Campus Location';
    if (preg_match('/(in|at|near|outside)\s+([A-Za-z0-9\s\-]+?)(?=\s+(is|has|was|not|are|with|\.|\,|$))/i', $message, $locMatches)) {
        $locCandidate = trim($locMatches[2]);
        if (strlen($locCandidate) > 2 && strlen($locCandidate) < 50) {
            $location = ucwords($locCandidate);
        }
    } elseif (preg_match('/(room|lab|block|hall|floor|canteen|hostel|library|classroom|auditorium)\s*[A-Za-z0-9\-]+/i', $message, $locMatches)) {
        $location = ucwords(trim($locMatches[0]));
    }

    // Title Generation
    $titleWords = explode(' ', $message);
    $title = (count($titleWords) > 7) ? implode(' ', array_slice($titleWords, 0, 7)) . '...' : $message;
    $title = ucfirst($title);

    $extracted = [
        'title'         => $title,
        'category_id'   => $matchedCatId,
        'category_name' => $matchedCatName,
        'location'      => $location,
        'priority'      => $priority,
        'description'   => $message
    ];
}

echo json_encode([
    'success' => true,
    'data'    => $extracted
]);
