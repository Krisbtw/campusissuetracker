<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole(['student','staff']);
$u=currentUser();
$error=$success='';
$categories=$pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $title=trim($_POST['title']??'');$description=trim($_POST['description']??'');
  $category_id=intval($_POST['category_id']??0);$location=trim($_POST['location']??'');
  $priority=$_POST['priority']??'medium';
  if(empty($title)||empty($description)||empty($location)||$category_id<=0){$error='Please fill in all required fields.';}
  elseif(!in_array($priority,['low','medium','high','critical'])){$error='Invalid priority.';}
  else{
    $stmt=$pdo->prepare("INSERT INTO issues (title,description,category_id,location,priority,status,reported_by) VALUES (?,?,?,?,?,'pending',?)");
    $stmt->execute([$title,$description,$category_id,$location,$priority,$u['id']]);
    $issue_id=$pdo->lastInsertId();
    if(!empty($_FILES['images']['name'][0])){
      if(!file_exists(UPLOAD_DIR)){ @mkdir(UPLOAD_DIR, 0777, true); }
      $allowed=['image/jpeg','image/png','image/jpg','image/webp'];
      foreach($_FILES['images']['tmp_name'] as $idx=>$tmp){
        if($_FILES['images']['error'][$idx]!==UPLOAD_ERR_OK)continue;
        $mime=mime_content_type($tmp);if(!in_array($mime,$allowed))continue;
        if($_FILES['images']['size'][$idx]>MAX_FILE_SIZE)continue;
        
        $cloudinaryUrl = uploadToCloudinary($tmp);
        if ($cloudinaryUrl) {
          $pdo->prepare("INSERT INTO issue_images (issue_id,image_path) VALUES (?,?)")->execute([$issue_id,$cloudinaryUrl]);
        } else {
          $ext=pathinfo($_FILES['images']['name'][$idx],PATHINFO_EXTENSION);
          $newName='issue_'.$issue_id.'_'.uniqid().'.'.strtolower($ext);
          if(move_uploaded_file($tmp,UPLOAD_DIR.$newName)){
            $pdo->prepare("INSERT INTO issue_images (issue_id,image_path) VALUES (?,?)")->execute([$issue_id,$newName]);
          }
        }
      }
    } elseif (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
      $tmp = $_FILES['image']['tmp_name'];
      $cloudinaryUrl = uploadToCloudinary($tmp);
      if ($cloudinaryUrl) {
        $pdo->prepare("INSERT INTO issue_images (issue_id,image_path) VALUES (?,?)")->execute([$issue_id,$cloudinaryUrl]);
      } else {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $newName = 'issue_' . $issue_id . '_' . uniqid() . '.' . strtolower($ext);
        if (move_uploaded_file($tmp, UPLOAD_DIR . $newName)) {
          $pdo->prepare("INSERT INTO issue_images (issue_id,image_path) VALUES (?,?)")->execute([$issue_id,$newName]);
        }
      }
    }
    logStatusChange($pdo,$issue_id,$u['id'],'','pending','Issue submitted by '.$u['name']);
    
    // Automated Duplicate Complaint Detection & Incident Clustering
    $recentCutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $duplicateCheck = $pdo->prepare("SELECT issue_id, parent_id, is_parent FROM issues WHERE category_id = ? AND LOWER(location) = LOWER(?) AND status IN ('pending','in_progress') AND parent_id IS NULL AND issue_id != ? AND created_at >= ? ORDER BY issue_id ASC LIMIT 1");
    $duplicateCheck->execute([$category_id, $location, $issue_id, $recentCutoff]);
    $activeMatch = $duplicateCheck->fetch();

    $mergedParentId = null;
    if ($activeMatch) {
      $mergedParentId = $activeMatch['issue_id'];
      
      // Set child report's parent_id
      $pdo->prepare("UPDATE issues SET parent_id = ? WHERE issue_id = ?")->execute([$mergedParentId, $issue_id]);
      
      // Mark primary issue as parent and update affected_count
      $pdo->prepare("UPDATE issues SET is_parent = 1, affected_count = (SELECT COUNT(*) + 1 FROM issues WHERE parent_id = ?) WHERE issue_id = ?")->execute([$mergedParentId, $mergedParentId]);
      
      // Log status timeline on parent issue
      logStatusChange($pdo, $mergedParentId, $u['id'], '', '', "Duplicate report merged from " . $u['name'] . " (Issue #" . $issue_id . ")");
    }

    $admins=$pdo->query("SELECT user_id FROM users WHERE role='admin'")->fetchAll();
    foreach($admins as $admin){
      if ($mergedParentId) {
        sendNotification($pdo, $admin['user_id'], $mergedParentId, "Duplicate report #{$issue_id} submitted by {$u['name']} was auto-merged into Parent Incident #{$mergedParentId}.", 'info');
      } else {
        sendNotification($pdo, $admin['user_id'], $issue_id, "New issue #{$issue_id} submitted by {$u['name']}: {$title}", 'warning');
      }
    }
    
    if ($mergedParentId) {
      $success="Issue #{$issue_id} submitted successfully and auto-merged into Parent Incident #{$mergedParentId}!";
    } else {
      $success="Issue #{$issue_id} submitted successfully!";
    }
  }
}
$pageTitle='Report an Issue';$pageSubtitle='Submit a campus problem for resolution';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Report Issue – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php';?>
  <div class="main-content">
    <?php include '../includes/topbar.php';?>
    <main class="page-content">
      <div class="page-head">
        <h1 class="page-title"><?=htmlspecialchars($pageTitle)?></h1>
        <p class="page-sub"><?=htmlspecialchars($pageSubtitle)?></p>
      </div>

      <?php if($error):?><div class="alert-banner alert-danger" role="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
      <?php if($success):?><div class="alert-banner alert-success" role="status"><?=htmlspecialchars($success)?><a href="my_issues.php" style="margin-left:6px;">Track issue</a></div><?php endif;?>

      <div class="report-tabs" role="tablist" aria-label="Reporting mode">
        <button type="button" role="tab" id="tabStandard" aria-selected="true" onclick="switchReportMode('standard')">Standard form</button>
        <button type="button" role="tab" id="tabAi" aria-selected="false" onclick="switchReportMode('ai')">AI-assisted report</button>
      </div>

      <div id="aiReportPanel" class="panel" style="display:none;">
        <div class="panel-header">
          <div class="assist-heading">
            <span>AI-assisted report</span>
            <p>Describe the issue, then review the extracted details before submitting.</p>
          </div>
        </div>
        <div class="panel-body">
          <div id="chatWindow" class="chat-window">
            <div class="chat-msg">
              <div class="chat-avatar">AI</div>
              <div class="chat-bubble">Describe the problem and location, <b><?=htmlspecialchars($u['name'])?></b>. Review the ticket details before submitting.</div>
            </div>
          </div>

          <div id="aiSummaryCard" class="chat-summary" style="display:none;">
            <div class="chat-summary-head">
              <span><i class="bi bi-file-earmark-check me-1"></i>Extracted ticket summary</span>
              <span id="aiPriorityBadge" class="badge badge-amber">Medium priority</span>
            </div>
            <div class="two-col-grid">
              <div><span class="meta-label">Location</span><div id="aiSummaryLocation" class="meta-value">–</div></div>
              <div><span class="meta-label">Category</span><div id="aiSummaryCategory" class="meta-value">–</div></div>
              <div style="grid-column: 1 / -1;"><span class="meta-label">Issue title</span><div id="aiSummaryTitle" class="meta-value">–</div></div>
              <div style="grid-column: 1 / -1;"><span class="meta-label">Description</span><div id="aiSummaryDescription" class="muted-note" style="margin-top:2px;">–</div></div>
            </div>
            <div class="chat-summary-actions">
              <button type="button" class="btn btn-secondary" onclick="editAiDetails()"><i class="bi bi-pencil me-1"></i>Edit details</button>
              <button type="button" class="btn btn-primary" onclick="confirmAndCreateTicket()"><i class="bi bi-check-circle-fill me-1"></i>Confirm &amp; create ticket</button>
            </div>
          </div>

          <div class="assist-controls" id="aiInputControls">
            <input type="text" id="aiUserInput" aria-label="Describe the campus issue for AI assistance" placeholder="Type campus issue details here… (e.g. 'AC in C2 class is making a loud buzzing noise')" class="form-control" onkeydown="if(event.key==='Enter'){event.preventDefault();sendAiMessage();}">
            <button type="button" id="btnSendAi" class="btn btn-primary" onclick="sendAiMessage()"><span>Analyze</span> <i class="bi bi-send-fill me-1"></i></button>
          </div>
        </div>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <div class="content-grid report-grid">
          <div>
            <div class="voice-toolbar" id="voiceToolbar">
              <div class="voice-controls">
                <button type="button" id="btnVoiceRecord" class="btn btn-secondary" onclick="toggleVoiceRecording()">
                  <i class="bi bi-mic-fill" id="micIcon"></i>
                  <span id="micText">Speak &amp; auto-fill</span>
                </button>
                <select id="voiceLangSelect" aria-label="Voice reporting language" class="form-control">
                  <option value="en-IN">English</option>
                  <option value="hi-IN">Hindi – हिन्दी</option>
                  <option value="mr-IN">Marathi – मराठी</option>
                  <option value="kok-IN">Konkani – कोंकणी</option>
                </select>
                <button type="button" id="btnToggleDictation" class="btn btn-secondary btn-sm" style="padding: 6px 12px; font-size: 12px;" onclick="toggleDictationPanel()" title="Open voice / dictation input box">
                  <i class="bi bi-keyboard me-1"></i>Type / Dictate
                </button>
              </div>
              <div id="voiceStatus" class="status-text"><i class="bi bi-translate me-1"></i>Speak in your language — auto-translated and filled into the form</div>
            </div>

            <!-- Inline Voice / Dictation Fallback & Quick Input Panel -->
            <div id="voiceDictationPanel" class="panel fade-in-up" style="display:none; margin-bottom: 16px; padding: 16px 20px; border-left: 4px solid var(--primary, #7b1e2b); background: var(--surface); box-shadow: 0 4px 16px -2px rgba(0,0,0,0.06);">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <div style="font-weight: 600; font-size: 14px; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                  <i class="bi bi-mic-fill" style="color: var(--primary, #7b1e2b);"></i>
                  <span>Voice Speech &amp; Auto-fill Assistant</span>
                </div>
                <button type="button" onclick="toggleDictationPanel(false)" style="background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer;" aria-label="Close panel"><i class="bi bi-x"></i></button>
              </div>
              <p id="dictationHelpNotice" style="font-size: 12px; color: var(--text-muted); margin-bottom: 10px; line-height: 1.5;">
                Speak or paste issue details in any language (Hindi, Marathi, Konkani, or English). <span style="font-weight: 500; color: var(--text-primary);">Tip: Press <kbd style="background:var(--border, #e2e8f0);padding:2px 6px;border-radius:4px;font-size:11px;">Win + H</kbd> on Windows to dictate directly via microphone:</span>
              </p>
              <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <textarea id="voiceManualInput" class="form-control" rows="2" style="flex: 1; min-width: 260px; font-size: 13px;" placeholder="e.g. 'There is a broken tubelight in C3 classroom ground floor' or 'पानी की पाइप लीक हो रही है'"></textarea>
                <button type="button" class="btn btn-primary" style="height: auto; align-self: stretch; padding: 8px 18px; font-size: 13px;" onclick="submitVoiceDictation()">
                  <i class="bi bi-magic me-1"></i>Auto-fill Form
                </button>
              </div>
            </div>

            <div class="panel">
              <div class="panel-header">Issue details</div>
              <div class="panel-body">
                <div class="field-group"><label class="form-label" for="title">Issue title *</label><input type="text" id="title" name="title" class="form-control" placeholder="e.g. Broken light in Lab 3" maxlength="200" required value="<?=htmlspecialchars($_POST['title']??'')?>"></div>
                <div class="field-group"><label class="form-label" for="description">Description *</label><textarea id="description" name="description" class="form-control" rows="5" placeholder="Describe the issue in detail — what's wrong, how long it has been happening, and its impact…" required><?=htmlspecialchars($_POST['description']??'')?></textarea><div class="muted-note" style="margin-top:4px;">Be as descriptive as possible for faster resolution.</div></div>
                <div class="field-group" style="margin-bottom:0"><label class="form-label" for="location">Campus location *</label><input type="text" id="location" name="location" class="form-control" placeholder="e.g. Block A – Computer Lab 3, 2nd Floor" required value="<?=htmlspecialchars($_POST['location']??'')?>"></div>
                <div id="similarIssueContainer" style="display:none;"></div>
              </div>
            </div>
            <div class="panel">
              <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
                <span>Photo evidence</span>
                <span id="imgCountBadge" class="badge badge-subtle" style="display:none;font-weight:600;"></span>
              </div>
              <div class="panel-body">
                <div class="photo-upload-options">
                  <button type="button" class="photo-option-card" id="uploadZone">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span class="option-title">Upload from device</span>
                    <span class="option-desc">Click, drag &amp; drop, or paste screenshot (Ctrl+V)</span>
                  </button>
                  <button type="button" class="photo-option-card camera-option-card" id="btnOpenCamera" onclick="openCameraModal()">
                    <i class="bi bi-camera-fill"></i>
                    <span class="option-title">Open camera &amp; click photo</span>
                    <span class="option-desc">Snap live photo from webcam or phone camera</span>
                  </button>
                </div>
                <input type="file" id="imgInput" name="images[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none;">
                <input type="file" id="nativeCameraInput" accept="image/*" capture="environment" style="display:none;" onchange="handleNativeCameraCapture(this)">
                <div id="uploadAlert" class="form-help" style="display:none;color:var(--rose);margin-top:8px;font-weight:500;"></div>
                <div id="imgPreview" class="img-preview-container" style="margin-top:12px;"></div>
              </div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div class="panel">
              <div class="panel-header">Classification</div>
              <div class="panel-body">
                <div class="field-group"><label class="form-label" for="category_id">Category *</label>
                  <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- Select category --</option>
                    <?php foreach($categories as $cat):?><option value="<?=$cat['category_id']?>"<?=($_POST['category_id']??'')==$cat['category_id']?' selected':''?>><?=htmlspecialchars($cat['category_name'])?></option><?php endforeach;?>
                  </select>
                </div>
                <div class="field-group" style="margin-bottom:0"><label class="form-label" for="priority">Priority *</label>
                  <select id="priority" name="priority" class="form-control">
                    <option value="low"<?=($_POST['priority']??'medium')==='low'?' selected':''?>>Low — minor inconvenience</option>
                    <option value="medium"<?=($_POST['priority']??'medium')==='medium'?' selected':''?>>Medium — needs attention</option>
                    <option value="high"<?=($_POST['priority']??'medium')==='high'?' selected':''?>>High — affects operations</option>
                    <option value="critical"<?=($_POST['priority']??'medium')==='critical'?' selected':''?>>Critical — safety hazard</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="panel">
              <div class="panel-header">Priority guide</div>
              <div class="panel-body">
                <div style="display:flex;flex-direction:column;gap:10px;font-size:var(--fs-sm);color:var(--text-muted);">
                  <div><span class="badge badge-emerald" style="margin-right:6px;">Low</span>Aesthetic or minor comfort</div>
                  <div><span class="badge badge-blue" style="margin-right:6px;">Medium</span>Functional, moderate impact</div>
                  <div><span class="badge badge-amber" style="margin-right:6px;">High</span>Class or work disruption</div>
                  <div><span class="badge badge-rose" style="margin-right:6px;">Critical</span>Safety risk, urgent</div>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary w-full"><i class="bi bi-send me-1"></i>Report an issue</button>
            <a href="dashboard.php" class="muted-note" style="text-align:center;">Back to dashboard</a>
          </div>
        </div>
      </form>
    </main>
  </div>
</div>
<script>
const FMC_AUTH_TOKEN = <?= json_encode(generateAuthToken($u)) ?>;
window.FMC_AUTH_TOKEN = FMC_AUTH_TOKEN;
let currentAiData = null;

function switchReportMode(mode) {
  const tabStd = document.getElementById('tabStandard');
  const tabAi = document.getElementById('tabAi');
  const aiPanel = document.getElementById('aiReportPanel');
  const toAi = mode === 'ai';
  tabAi.setAttribute('aria-selected', toAi ? 'true' : 'false');
  tabStd.setAttribute('aria-selected', toAi ? 'false' : 'true');
  aiPanel.style.display = toAi ? 'block' : 'none';
}

function escapeHtml(text) {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return String(text).replace(/[&<>"']/g, m => map[m]);
}

async function sendAiMessage() {
  const inputEl = document.getElementById('aiUserInput');
  const msg = inputEl.value.trim();
  if (!msg) return;

  const chatWin = document.getElementById('chatWindow');
  const btnSend = document.getElementById('btnSendAi');

  chatWin.insertAdjacentHTML('beforeend', `
    <div class="chat-msg from-user">
      <div class="chat-bubble from-user">${escapeHtml(msg)}</div>
      <div class="chat-avatar user">You</div>
    </div>`);
  inputEl.value = '';
  chatWin.scrollTop = chatWin.scrollHeight;

  const loadingId = 'aiLoading_' + Date.now();
  chatWin.insertAdjacentHTML('beforeend', `
    <div id="${loadingId}" class="chat-msg">
      <div class="chat-avatar">AI</div>
      <div class="chat-bubble is-loading">Analyzing your issue details…</div>
    </div>`);
  chatWin.scrollTop = chatWin.scrollHeight;

  btnSend.disabled = true;

  try {
    const response = await fetch('../api/ai_chat_reporter.php', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-Auth-Token': FMC_AUTH_TOKEN
      },
      credentials: 'include',
      body: JSON.stringify({ 
        message: msg,
        auth_token: FMC_AUTH_TOKEN
      })
    });
    const text = await response.text();
    let resData;
    try {
      resData = JSON.parse(text);
    } catch (parseErr) {
      console.error('AI chat non-JSON response:', text);
      throw new Error('Server returned an invalid response. Please try again or use the manual form.');
    }
    document.getElementById(loadingId)?.remove();

    if (resData.success && resData.data) {
      currentAiData = resData.data;

      chatWin.insertAdjacentHTML('beforeend', `
        <div class="chat-msg">
          <div class="chat-avatar">AI</div>
          <div class="chat-bubble">I've extracted the ticket details! Please review the summary below and click <b>Confirm &amp; create ticket</b>.</div>
        </div>`);

      document.getElementById('aiSummaryTitle').innerText = currentAiData.title;
      document.getElementById('aiSummaryLocation').innerText = currentAiData.location;
      document.getElementById('aiSummaryCategory').innerText = currentAiData.category_name;
      document.getElementById('aiSummaryDescription').innerText = currentAiData.description;
      document.getElementById('aiPriorityBadge').innerText = (currentAiData.priority || 'medium').toUpperCase() + ' PRIORITY';

      document.getElementById('aiSummaryCard').style.display = 'block';
    } else {
      chatWin.insertAdjacentHTML('beforeend', `
        <div class="chat-msg">
          <div class="chat-avatar">AI</div>
          <div class="chat-bubble is-error">${escapeHtml(resData.error || 'Could not parse issue details. Please try again or use the standard form.')}</div>
        </div>`);
    }
  } catch (err) {
    document.getElementById(loadingId)?.remove();
    console.error(err);
    chatWin.insertAdjacentHTML('beforeend', `
      <div class="chat-msg">
        <div class="chat-avatar">AI</div>
        <div class="chat-bubble is-error">${escapeHtml(err.message || 'Something went wrong reaching the AI assistant. Please try again.')}</div>
      </div>`);
  } finally {
    btnSend.disabled = false;
    chatWin.scrollTop = chatWin.scrollHeight;
  }
}

function confirmAndCreateTicket() {
  if (!currentAiData) return;

  document.getElementById('title').value = currentAiData.title || '';
  document.getElementById('description').value = currentAiData.description || '';
  document.getElementById('location').value = currentAiData.location || '';

  if (currentAiData.category_id) {
    document.getElementById('category_id').value = currentAiData.category_id;
  }
  if (currentAiData.priority) {
    document.getElementById('priority').value = currentAiData.priority.toLowerCase();
  }

  document.querySelector('form').submit();
}

function editAiDetails() {
  if (!currentAiData) return;

  document.getElementById('title').value = currentAiData.title || '';
  document.getElementById('description').value = currentAiData.description || '';
  document.getElementById('location').value = currentAiData.location || '';
  if (currentAiData.category_id) {
    document.getElementById('category_id').value = currentAiData.category_id;
  }
  if (currentAiData.priority) {
    document.getElementById('priority').value = currentAiData.priority.toLowerCase();
  }

  switchReportMode('standard');
}

/* Multi-language voice input & speech recognition */
let recognition = null;
let isRecording = false;
let activeMediaStream = null;

function toggleDictationPanel(show) {
  const panel = document.getElementById('voiceDictationPanel');
  const input = document.getElementById('voiceManualInput');
  if (!panel) return;
  const willShow = (show !== undefined) ? show : (panel.style.display === 'none');
  panel.style.display = willShow ? 'block' : 'none';
  if (willShow && input) {
    input.focus();
  }
}

function submitVoiceDictation() {
  const input = document.getElementById('voiceManualInput');
  const text = (input ? input.value : '').trim();
  if (!text) {
    alert('Please enter or dictate a short description of the campus issue.');
    if (input) input.focus();
    return;
  }
  const lang = document.getElementById('voiceLangSelect').value;
  processSpeechTranscript(text, lang);
  toggleDictationPanel(false);
  if (input) input.value = '';
}

async function processSpeechTranscript(transcript, language) {
  if (!transcript || !transcript.trim()) return;

  const status = document.getElementById('voiceStatus');
  status.className = 'status-text is-busy';
  status.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> Translating &amp; auto-filling fields…';

  try {
    const response = await fetch('../api/translate_voice_report.php', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-Auth-Token': FMC_AUTH_TOKEN
      },
      credentials: 'include',
      body: JSON.stringify({ 
        raw_text: transcript.trim(), 
        language: language,
        auth_token: FMC_AUTH_TOKEN
      })
    });
    const resData = await response.json();

    if (resData.success && resData.data) {
      const data = resData.data;

      const titleEl = document.getElementById('title');
      const descEl = document.getElementById('description');
      const locEl = document.getElementById('location');
      const catEl = document.getElementById('category_id');
      const prioEl = document.getElementById('priority');

      if (titleEl) {
        titleEl.value = data.title || transcript;
        titleEl.style.transition = 'box-shadow 0.3s ease';
        titleEl.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.35)';
        setTimeout(() => titleEl.style.boxShadow = '', 2000);
      }
      if (descEl) {
        descEl.value = data.description || data.translated_text || transcript;
        descEl.style.transition = 'box-shadow 0.3s ease';
        descEl.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.35)';
        setTimeout(() => descEl.style.boxShadow = '', 2000);
      }
      if (locEl && data.location) {
        locEl.value = data.location;
      }
      if (catEl && data.category_id) {
        catEl.value = data.category_id;
      }
      if (prioEl && data.priority) {
        prioEl.value = data.priority.toLowerCase();
      }

      status.className = 'status-text is-success';
      status.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Auto-filled form! Translated: "${escapeHtml(data.title)}"`;
    } else {
      status.className = 'status-text is-error';
      status.innerText = `Auto-fill failed: ${resData.error || 'Unknown error'}`;
    }
  } catch (err) {
    console.error('Translation error:', err);
    status.className = 'status-text is-error';
    status.innerText = 'Error processing speech auto-fill request.';
  }
}

function resetVoiceBtn() {
  isRecording = false;
  const btn = document.getElementById('btnVoiceRecord');
  const icon = document.getElementById('micIcon');
  const text = document.getElementById('micText');

  if (btn) {
    btn.classList.remove('is-recording');
    icon.className = 'bi bi-mic-fill';
    text.innerText = 'Speak & auto-fill';
  }
}

async function toggleVoiceRecording() {
  const status = document.getElementById('voiceStatus');

  if (isRecording) {
    if (recognition) {
      try { recognition.stop(); } catch(e){}
    }
    resetVoiceBtn();
    return;
  }

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) {
    if (status) {
      status.className = 'status-text is-error';
      status.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Web Speech is not supported in this browser. Click "Type / Dictate" to enter your issue.';
    }
    return;
  }

  // Pre-request and immediately release microphone permission to ensure clean hardware access
  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    try {
      const testStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      testStream.getTracks().forEach(track => track.stop());
    } catch (permErr) {
      console.warn('Microphone permission warning:', permErr);
      if (status) {
        status.className = 'status-text is-error';
        status.innerHTML = '<i class="bi bi-mic-mute me-1"></i> Microphone access blocked. Please allow microphone permission in your browser address bar.';
      }
      return;
    }
  }

  if (recognition) {
    try { recognition.abort(); } catch(e){}
    recognition = null;
  }

  const selectedVal = document.getElementById('voiceLangSelect').value;
  let speechLocale = selectedVal;
  if (selectedVal === 'kok-IN') speechLocale = 'hi-IN';

  let capturedTranscript = '';

  try {
    recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;
    recognition.lang = speechLocale;

    recognition.onstart = function() {
      isRecording = true;
      const btn = document.getElementById('btnVoiceRecord');
      const icon = document.getElementById('micIcon');
      const text = document.getElementById('micText');

      if (btn) {
        btn.classList.add('is-recording');
        icon.className = 'bi bi-record-fill';
        text.innerText = 'Listening… (Click to finish)';
      }
      if (status) {
        status.className = 'status-text is-recording';
        status.innerHTML = '<i class="bi bi-soundwave me-1"></i> Listening live… speak your issue details now.';
      }
    };

    recognition.onresult = function(event) {
      for (let i = event.resultIndex; i < event.results.length; ++i) {
        const transcriptPart = event.results[i][0].transcript;
        if (event.results[i].isFinal) {
          capturedTranscript += ' ' + transcriptPart;
        } else {
          if (status) {
            status.className = 'status-text is-recording';
            status.innerHTML = `<i class="bi bi-soundwave me-1"></i> "${escapeHtml(transcriptPart)}"`;
          }
        }
      }
    };

    recognition.onerror = function(event) {
      console.error('Speech recognition error:', event.error);
      resetVoiceBtn();

      if (status) {
        if (event.error === 'no-speech') {
          status.className = 'status-text is-busy';
          status.innerHTML = '<i class="bi bi-volume-mute me-1"></i> No speech detected. Click "Speak & auto-fill" and speak into your mic.';
        } else if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
          status.className = 'status-text is-error';
          status.innerHTML = '<i class="bi bi-mic-mute me-1"></i> Microphone permission denied. Click the lock icon in the address bar to allow mic access.';
        } else if (event.error === 'network') {
          status.className = 'status-text is-busy';
          status.innerHTML = '<i class="bi bi-wifi-off me-1"></i> Speech service could not connect over this network. Click "Type / Dictate" to enter your issue.';
        } else {
          status.className = 'status-text is-error';
          status.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> Speech error (${escapeHtml(event.error)}). Click "Type / Dictate" to enter your issue.`;
        }
      }
    };

    recognition.onend = function() {
      resetVoiceBtn();
      if (capturedTranscript && capturedTranscript.trim()) {
        processSpeechTranscript(capturedTranscript.trim(), selectedVal);
      }
    };

    recognition.start();
  } catch (err) {
    console.error('Recognition start error:', err);
    resetVoiceBtn();
    if (status) {
      status.className = 'status-text is-error';
      status.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Could not start speech engine. Click "Type / Dictate" to enter your issue.';
    }
  }
}

let selectedUploadFiles = [];

function setupImageUpload() {
  const input = document.getElementById('imgInput');
  const zone = document.getElementById('uploadZone');
  if (!input || !zone) return;

  zone.addEventListener('click', () => input.click());

  ['dragenter', 'dragover'].forEach(name => {
    zone.addEventListener(name, (e) => {
      e.preventDefault();
      e.stopPropagation();
      zone.classList.add('drag-active');
    });
  });

  ['dragleave', 'drop'].forEach(name => {
    zone.addEventListener(name, (e) => {
      e.preventDefault();
      e.stopPropagation();
      zone.classList.remove('drag-active');
    });
  });

  zone.addEventListener('drop', (e) => {
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
      handleNewUploadFiles(Array.from(e.dataTransfer.files));
    }
  });

  input.addEventListener('change', () => {
    if (input.files && input.files.length) {
      handleNewUploadFiles(Array.from(input.files));
      input.value = '';
    }
  });

  // Clipboard Paste Support (Ctrl+V / Command+V)
  document.addEventListener('paste', (e) => {
    // If active element is a text input or textarea, check if clipboard has actual files/images
    const activeEl = document.activeElement;
    const isTextFocused = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA') && (activeEl.type === 'text' || activeEl.tagName === 'TEXTAREA');

    if (e.clipboardData && e.clipboardData.items) {
      const items = e.clipboardData.items;
      const pastedImages = [];
      for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
          const blob = items[i].getAsFile();
          if (blob) {
            const ext = blob.type.split('/')[1] || 'png';
            const file = new File([blob], `screenshot_${Date.now()}.${ext}`, { type: blob.type });
            pastedImages.push(file);
          }
        }
      }
      if (pastedImages.length > 0) {
        if (isTextFocused) {
          // If they pasted an image while in a text input, don't block text pasting but attach the image
          handleNewUploadFiles(pastedImages);
        } else {
          e.preventDefault();
          handleNewUploadFiles(pastedImages);
        }
        const alertEl = document.getElementById('uploadAlert');
        if (alertEl) {
          alertEl.style.display = 'block';
          alertEl.style.color = 'var(--emerald)';
          alertEl.innerHTML = `<i class="bi bi-clipboard-check me-1"></i> Attached ${pastedImages.length} image(s) from clipboard!`;
          setTimeout(() => { if (alertEl.style.color === 'var(--emerald)') alertEl.style.display = 'none'; }, 4000);
        }
      }
    }
  });
}

// Similar issue real-time detection
let debounceSimilarTimer = null;
function checkSimilarIssuesDebounced() {
  clearTimeout(debounceSimilarTimer);
  debounceSimilarTimer = setTimeout(async () => {
    const catEl = document.getElementById('category_id');
    const locEl = document.getElementById('location');
    const titleEl = document.getElementById('title');
    const container = document.getElementById('similarIssueContainer');
    if (!container) return;

    const catId = catEl ? catEl.value : 0;
    const loc = locEl ? locEl.value.trim() : '';
    const title = titleEl ? titleEl.value.trim() : '';

    if (!catId && loc.length < 3 && title.length < 3) {
      container.style.display = 'none';
      container.innerHTML = '';
      return;
    }

    try {
      const query = new URLSearchParams({ category_id: catId, location: loc, title: title, auth_token: FMC_AUTH_TOKEN });
      const res = await fetch(`../api/check_similar_issues.php?${query.toString()}`, {
        credentials: 'include',
        headers: { 'X-Auth-Token': FMC_AUTH_TOKEN }
      });
      const data = await res.json();

      if (data.success && data.count > 0) {
        let issuesHtml = data.similar.map(item => `
          <div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:8px 12px;margin-top:6px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <div>
              <div style="font-weight:600;font-size:13px;color:#f8fafc;">#${item.issue_id} — ${escapeHtml(item.title)}</div>
              <div style="font-size:11px;color:#94a3b8;"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(item.location)} &bull; Reported by ${escapeHtml(item.reporter_name || 'Student')}</div>
            </div>
            <span class="badge badge-amber" style="text-transform:capitalize;font-size:11px;">${escapeHtml(item.status.replace('_', ' '))}</span>
          </div>
        `).join('');

        container.innerHTML = `
          <div style="margin-top:12px;background:rgba(245, 158, 11, 0.08);border:1px solid rgba(245, 158, 11, 0.35);border-radius:10px;padding:12px 14px;" class="fade-in-up">
            <div style="display:flex;align-items:center;gap:8px;color:#f59e0b;font-weight:600;font-size:13px;margin-bottom:4px;">
              <i class="bi bi-info-circle-fill"></i>
              <span>Similar Active Issue Detected (${data.count})</span>
            </div>
            <p style="font-size:12px;color:#cbd5e1;margin:0 0 6px 0;">Campus maintenance is already actively working on an issue in this area. If your report matches, it will automatically link to accelerate resolution:</p>
            ${issuesHtml}
          </div>
        `;
        container.style.display = 'block';
      } else {
        container.style.display = 'none';
        container.innerHTML = '';
      }
    } catch (err) {
      console.warn('Error checking similar issues:', err);
    }
  }, 400);
}

function handleNewUploadFiles(files) {
  const alertEl = document.getElementById('uploadAlert');
  if (alertEl) {
    alertEl.style.display = 'none';
    alertEl.innerText = '';
  }

  const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
  const maxPerFile = 5 * 1024 * 1024; // 5 MB
  const maxTotal = 5;
  const errors = [];

  for (const file of files) {
    if (selectedUploadFiles.length >= maxTotal) {
      errors.push(`Maximum ${maxTotal} images allowed.`);
      break;
    }
    const ext = file.name.split('.').pop().toLowerCase();
    const isAllowedExt = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
    if (!allowed.includes(file.type.toLowerCase()) && !isAllowedExt) {
      errors.push(`"${file.name}" is not an accepted image format (JPG, PNG, WEBP).`);
      continue;
    }
    if (file.size > maxPerFile) {
      errors.push(`"${file.name}" exceeds 5 MB limit (${formatFileSize(file.size)}).`);
      continue;
    }

    const isDuplicate = selectedUploadFiles.some(f => f.name === file.name && f.size === file.size);
    if (!isDuplicate) {
      selectedUploadFiles.push(file);
    }
  }

  if (errors.length > 0 && alertEl) {
    alertEl.innerText = errors.join(' ');
    alertEl.style.display = 'block';
  }

  syncUploadInput();
  renderImagePreviews();
}

function removeImage(index) {
  if (index >= 0 && index < selectedUploadFiles.length) {
    selectedUploadFiles.splice(index, 1);
    const alertEl = document.getElementById('uploadAlert');
    if (alertEl) {
      alertEl.style.display = 'none';
      alertEl.innerText = '';
    }
    syncUploadInput();
    renderImagePreviews();
  }
}

function clearAllImages() {
  selectedUploadFiles = [];
  const alertEl = document.getElementById('uploadAlert');
  if (alertEl) {
    alertEl.style.display = 'none';
    alertEl.innerText = '';
  }
  syncUploadInput();
  renderImagePreviews();
}

function syncUploadInput() {
  const input = document.getElementById('imgInput');
  if (!input) return;
  if (window.DataTransfer) {
    const dt = new DataTransfer();
    selectedUploadFiles.forEach(file => dt.items.add(file));
    input.files = dt.files;
  }
}

function renderImagePreviews() {
  const preview = document.getElementById('imgPreview');
  const countBadge = document.getElementById('imgCountBadge');
  if (!preview) return;

  if (selectedUploadFiles.length === 0) {
    preview.innerHTML = '';
    if (countBadge) countBadge.style.display = 'none';
    return;
  }

  if (countBadge) {
    countBadge.innerText = `${selectedUploadFiles.length}/5 selected`;
    countBadge.style.display = 'inline-block';
  }

  preview.innerHTML = `
    <div class="thumb-header-bar">
      <span class="thumb-count-text"><i class="bi bi-images me-1"></i>Attached photos (${selectedUploadFiles.length}/5)</span>
      <button type="button" class="btn-clear-all" onclick="clearAllImages()" title="Remove all photos">
        <i class="bi bi-trash3 me-1"></i>Clear all
      </button>
    </div>
    <div class="thumb-gallery-grid" id="thumbGalleryGrid"></div>
  `;

  const grid = document.getElementById('thumbGalleryGrid');

  selectedUploadFiles.forEach((file, idx) => {
    const card = document.createElement('div');
    card.className = 'thumb-card';
    card.innerHTML = `
      <div class="thumb-img-wrap">
        <img src="" alt="${escapeHtml(file.name)}" id="thumb_img_${idx}">
        <button type="button" class="thumb-delete-btn" onclick="removeImage(${idx})" title="Delete this image" aria-label="Delete ${escapeHtml(file.name)}">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="thumb-info">
        <span class="thumb-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
        <span class="thumb-size">${formatFileSize(file.size)}</span>
      </div>
    `;
    grid.appendChild(card);

    const reader = new FileReader();
    reader.onload = (e) => {
      const imgEl = document.getElementById(`thumb_img_${idx}`);
      if (imgEl) imgEl.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

function formatFileSize(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

document.addEventListener('DOMContentLoaded', () => {
  setupImageUpload();
  const locEl = document.getElementById('location');
  const catEl = document.getElementById('category_id');
  const titleEl = document.getElementById('title');
  if (locEl) locEl.addEventListener('input', checkSimilarIssuesDebounced);
  if (catEl) catEl.addEventListener('change', checkSimilarIssuesDebounced);
  if (titleEl) titleEl.addEventListener('input', checkSimilarIssuesDebounced);
});

// Live Camera Viewfinder & Capture Logic
let cameraStream = null;
let currentFacingMode = 'environment'; // default to back camera
let capturedBlob = null;

async function openCameraModal() {
  const alertEl = document.getElementById('uploadAlert');
  if (alertEl) { alertEl.style.display = 'none'; alertEl.innerText = ''; }

  if (selectedUploadFiles.length >= 5) {
    if (alertEl) {
      alertEl.innerText = 'Maximum 5 images allowed. Please delete one before taking another photo.';
      alertEl.style.display = 'block';
    } else {
      alert('Maximum 5 images allowed.');
    }
    return;
  }

  const modal = document.getElementById('cameraModal');
  if (!modal) return;

  modal.classList.add('open');
  retakeSnapshot(); // reset viewfinder state

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    // Fallback directly to native camera input on unsupported browsers
    document.getElementById('nativeCameraInput').click();
    closeCameraModal();
    return;
  }

  await startCameraStream();
}

async function startCameraStream() {
  stopCameraStream();

  const video = document.getElementById('cameraVideo');
  const btnSwitch = document.getElementById('btnSwitchFacing');

  try {
    const constraints = {
      video: {
        facingMode: { ideal: currentFacingMode },
        width: { ideal: 1920 },
        height: { ideal: 1080 }
      },
      audio: false
    };

    cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
    video.srcObject = cameraStream;
    await video.play();

    // Show flip button if multiple cameras detected
    if (navigator.mediaDevices.enumerateDevices) {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const videoDevices = devices.filter(d => d.kind === 'videoinput');
      if (videoDevices.length > 1 && btnSwitch) {
        btnSwitch.style.display = 'flex';
      }
    }
  } catch (err) {
    console.warn('Live camera error or permission denied:', err);
    // Offer native fallback
    const fallback = confirm('Could not access live camera preview (permissions may be blocked). Would you like to use your device\'s default camera app instead?');
    if (fallback) {
      document.getElementById('nativeCameraInput').click();
    }
    closeCameraModal();
  }
}

function stopCameraStream() {
  if (cameraStream) {
    cameraStream.getTracks().forEach(track => track.stop());
    cameraStream = null;
  }
  const video = document.getElementById('cameraVideo');
  if (video) {
    video.srcObject = null;
  }
}

function toggleCameraFacing() {
  currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
  startCameraStream();
}

function takeSnapshot() {
  const video = document.getElementById('cameraVideo');
  const canvas = document.getElementById('cameraCanvas');
  const flash = document.getElementById('cameraFlash');
  const guide = document.getElementById('cameraGuide');
  const ctrlCapture = document.getElementById('cameraControlsCapture');
  const ctrlReview = document.getElementById('cameraControlsReview');

  if (!video || !canvas) return;

  const w = video.videoWidth || 1280;
  const h = video.videoHeight || 720;
  canvas.width = w;
  canvas.height = h;

  const ctx = canvas.getContext('2d');
  // If front camera, flip horizontally so it mirrors user perception
  if (currentFacingMode === 'user') {
    ctx.translate(w, 0);
    ctx.scale(-1, 1);
  }
  ctx.drawImage(video, 0, 0, w, h);

  // Trigger flash visual effect
  if (flash) {
    flash.classList.add('flash');
    setTimeout(() => flash.classList.remove('flash'), 150);
  }

  // Freeze & show review mode
  video.style.display = 'none';
  canvas.style.display = 'block';
  if (guide) guide.style.display = 'none';

  ctrlCapture.style.display = 'none';
  ctrlReview.style.display = 'flex';

  canvas.toBlob(blob => {
    capturedBlob = blob;
  }, 'image/jpeg', 0.92);
}

function retakeSnapshot() {
  const video = document.getElementById('cameraVideo');
  const canvas = document.getElementById('cameraCanvas');
  const guide = document.getElementById('cameraGuide');
  const ctrlCapture = document.getElementById('cameraControlsCapture');
  const ctrlReview = document.getElementById('cameraControlsReview');

  capturedBlob = null;
  if (video) video.style.display = 'block';
  if (canvas) canvas.style.display = 'none';
  if (guide) guide.style.display = 'block';

  if (ctrlCapture) ctrlCapture.style.display = 'flex';
  if (ctrlReview) ctrlReview.style.display = 'none';
}

function useCapturedSnapshot() {
  if (!capturedBlob) {
    takeSnapshot();
  }

  if (capturedBlob) {
    const timestamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14);
    const fileName = `camera_photo_${timestamp}.jpg`;
    const file = new File([capturedBlob], fileName, { type: 'image/jpeg' });

    handleNewUploadFiles([file]);
    closeCameraModal();
  }
}

function closeCameraModal() {
  stopCameraStream();
  const modal = document.getElementById('cameraModal');
  if (modal) modal.classList.remove('open');
  retakeSnapshot();
}

function handleNativeCameraCapture(input) {
  if (input.files && input.files.length) {
    handleNewUploadFiles(Array.from(input.files));
    input.value = '';
  }
}

// Close camera on Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const modal = document.getElementById('cameraModal');
    if (modal && modal.classList.contains('open')) {
      closeCameraModal();
    }
  }
});
</script>

<!-- Live Camera Viewfinder Modal -->
<div id="cameraModal" class="camera-modal-overlay">
  <div class="camera-modal-card" role="dialog" aria-label="Camera viewfinder">
    <div class="camera-modal-head">
      <span><i class="bi bi-camera me-1"></i> Click photo evidence</span>
      <button type="button" class="btn-sm-icon" onclick="closeCameraModal()" aria-label="Close camera">&times;</button>
    </div>
    <div class="camera-view-container">
      <video id="cameraVideo" autoplay playsinline muted></video>
      <canvas id="cameraCanvas" style="display:none;"></canvas>
      <div class="camera-guide-box" id="cameraGuide"></div>
      <div class="camera-shutter-flash" id="cameraFlash"></div>
      <button type="button" class="camera-switch-btn" id="btnSwitchFacing" onclick="toggleCameraFacing()" title="Switch camera" style="display:none;">
        <i class="bi bi-arrow-repeat"></i>
      </button>
    </div>
    <div class="camera-modal-foot" id="cameraControlsCapture">
      <button type="button" class="btn btn-secondary" onclick="closeCameraModal()">Cancel</button>
      <button type="button" class="camera-snap-btn" onclick="takeSnapshot()">
        <i class="bi bi-camera-fill"></i> Snap Picture
      </button>
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('nativeCameraInput').click(); closeCameraModal();" title="Use native phone camera">
        <i class="bi bi-phone"></i> Device App
      </button>
    </div>
    <div class="camera-modal-foot" id="cameraControlsReview" style="display:none;">
      <button type="button" class="btn btn-secondary" onclick="retakeSnapshot()">
        <i class="bi bi-arrow-repeat me-1"></i> Retake
      </button>
      <span class="muted-note" style="font-size:12px;"><i class="bi bi-eye me-1"></i>Photo preview</span>
      <button type="button" class="btn btn-primary" onclick="useCapturedSnapshot()">
        <i class="bi bi-check-circle-fill me-1"></i> Use photo
      </button>
    </div>
  </div>
</div>
</body></html>
