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
      
      sendNotification($pdo, $u['id'], $issue_id, "Your report has been linked to existing Parent Incident #{$mergedParentId}.", 'info');
    }

    $admins=$pdo->query("SELECT user_id FROM users WHERE role='admin'")->fetchAll();
    foreach($admins as $admin){sendNotification($pdo,$admin['user_id'],$issue_id,"New issue #{$issue_id} submitted by {$u['name']}: {$title}",'warning');}
    
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
            <div class="voice-toolbar">
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
              </div>
              <div id="voiceStatus" class="status-text"><i class="bi bi-translate me-1"></i>Speak in your language — auto-translated and filled into the form</div>
            </div>

            <div class="panel">
              <div class="panel-header">Issue details</div>
              <div class="panel-body">
                <div class="field-group"><label class="form-label" for="title">Issue title *</label><input type="text" id="title" name="title" class="form-control" placeholder="e.g. Broken light in Lab 3" maxlength="200" required value="<?=htmlspecialchars($_POST['title']??'')?>"></div>
                <div class="field-group"><label class="form-label" for="description">Description *</label><textarea id="description" name="description" class="form-control" rows="5" placeholder="Describe the issue in detail — what's wrong, how long it has been happening, and its impact…" required><?=htmlspecialchars($_POST['description']??'')?></textarea><div class="muted-note" style="margin-top:4px;">Be as descriptive as possible for faster resolution.</div></div>
                <div class="field-group" style="margin-bottom:0"><label class="form-label" for="location">Campus location *</label><input type="text" id="location" name="location" class="form-control" placeholder="e.g. Block A – Computer Lab 3, 2nd Floor" required value="<?=htmlspecialchars($_POST['location']??'')?>"></div>
              </div>
            </div>
            <div class="panel">
              <div class="panel-header">Photo evidence</div>
              <div class="panel-body">
                <button type="button" class="upload-zone" id="uploadZone" onclick="document.getElementById('imgInput').click()">
                  <i class="bi bi-cloud-upload"></i>
                  <p style="font-weight:500;margin-bottom:2px;">Click to upload images</p>
                  <p>JPG, PNG, WEBP — max 5 MB each, up to 5 images</p>
                </button>
                <input type="file" id="imgInput" name="images[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previewImages(this)">
                <div id="imgPreview" class="img-gallery" style="margin-top:10px;"></div>
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
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: msg })
    });
    const resData = await response.json();
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
        <div class="chat-bubble is-error">Something went wrong reaching the AI assistant. Please try again.</div>
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

function initSpeechRecognition() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) {
    alert('Web Speech API is not supported in your browser. Please use Chrome, Edge, or Safari.');
    return null;
  }
  const rec = new SpeechRecognition();
  rec.continuous = false;
  rec.interimResults = false;

  rec.onstart = function() {
    isRecording = true;
    const btn = document.getElementById('btnVoiceRecord');
    const icon = document.getElementById('micIcon');
    const text = document.getElementById('micText');
    const status = document.getElementById('voiceStatus');

    btn.classList.add('is-recording');
    icon.className = 'bi bi-record-fill';
    text.innerText = 'Listening…';
    status.className = 'status-text is-recording';
    status.innerHTML = '<i class="bi bi-soundwave me-1"></i> Recording live audio…';
  };

  rec.onresult = async function(event) {
    const transcript = event.results[0][0].transcript;
    const langSelect = document.getElementById('voiceLangSelect').value;

    const status = document.getElementById('voiceStatus');
    status.className = 'status-text is-busy';
    status.innerHTML = '<i class="bi bi-cpu me-1"></i> Translating to English &amp; extracting fields…';

    try {
      const response = await fetch('../api/translate_voice_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ raw_text: transcript, language: langSelect })
      });
      const resData = await response.json();

      if (resData.success && resData.data) {
        const data = resData.data;

        document.getElementById('title').value = data.title || transcript;
        document.getElementById('description').value = data.description || data.translated_text || transcript;
        document.getElementById('location').value = data.location || '';

        if (data.category_id) {
          document.getElementById('category_id').value = data.category_id;
        }
        if (data.priority) {
          document.getElementById('priority').value = data.priority.toLowerCase();
        }

        status.className = 'status-text is-success';
        status.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Auto-filled form! Translated: "${escapeHtml(data.title)}"`;
      } else {
        status.className = 'status-text is-error';
        status.innerText = `Translation failed: ${resData.error || 'Unknown error'}`;
      }
    } catch (err) {
      console.error(err);
      status.className = 'status-text is-error';
      status.innerText = 'Error processing translation request.';
    } finally {
      resetVoiceBtn();
    }
  };

  rec.onerror = function(event) {
    console.error('Speech recognition error:', event.error);
    const status = document.getElementById('voiceStatus');
    status.className = 'status-text is-error';
    if (event.error === 'network') {
      status.innerHTML = '<i class="bi bi-wifi-off me-1"></i> Speech service network error. <button type="button" onclick="promptManualSpeechText()">Click to paste/type voice transcript</button>';
    } else if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
      status.innerHTML = '<i class="bi bi-mic-mute me-1"></i> Mic access denied. Please allow microphone permissions in your browser.';
    } else if (event.error === 'no-speech') {
      status.className = 'status-text is-busy';
      status.innerHTML = '<i class="bi bi-volume-mute me-1"></i> No speech detected. Speak clearly into your mic and try again.';
    } else {
      status.innerText = `Mic error: ${event.error}`;
    }
    resetVoiceBtn();
  };

  rec.onend = function() {
    if (isRecording) {
      resetVoiceBtn();
    }
  };

  return rec;
}

function promptManualSpeechText() {
  const userText = prompt("Enter or paste your issue speech description (Hindi, Marathi, Konkani, English):");
  if (!userText || !userText.trim()) return;

  const langSelect = document.getElementById('voiceLangSelect').value;
  const status = document.getElementById('voiceStatus');
  status.className = 'status-text is-busy';
  status.innerHTML = '<i class="bi bi-cpu me-1"></i> Translating to English &amp; extracting fields…';

  fetch('../api/translate_voice_report.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ raw_text: userText.trim(), language: langSelect })
  })
  .then(res => res.json())
  .then(resData => {
    if (resData.success && resData.data) {
      const data = resData.data;
      document.getElementById('title').value = data.title || userText;
      document.getElementById('description').value = data.description || data.translated_text || userText;
      document.getElementById('location').value = data.location || '';
      if (data.category_id) document.getElementById('category_id').value = data.category_id;
      if (data.priority) document.getElementById('priority').value = data.priority.toLowerCase();

      status.className = 'status-text is-success';
      status.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Auto-filled form! Translated: "${escapeHtml(data.title)}"`;
    } else {
      status.className = 'status-text is-error';
      status.innerText = `Translation failed: ${resData.error || 'Unknown error'}`;
    }
  })
  .catch(err => {
    console.error(err);
    status.className = 'status-text is-error';
    status.innerText = 'Error processing translation request.';
  });
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

function toggleVoiceRecording() {
  if (!recognition) {
    recognition = initSpeechRecognition();
  }
  if (!recognition) return;

  if (isRecording) {
    recognition.stop();
  } else {
    const selectedVal = document.getElementById('voiceLangSelect').value;
    let speechLocale = 'en-IN';
    if (selectedVal === 'hi-IN') speechLocale = 'hi-IN';
    else if (selectedVal === 'mr-IN') speechLocale = 'mr-IN';
    else if (selectedVal === 'kok-IN') speechLocale = 'hi-IN';

    recognition.lang = speechLocale;
    recognition.start();
  }
}

function previewImages(input){
  const preview=document.getElementById('imgPreview');preview.innerHTML='';
  Array.from(input.files).slice(0,5).forEach(file=>{
    const reader=new FileReader();
    reader.onload=e=>{const img=document.createElement('img');img.src=e.target.result;preview.appendChild(img);};
    reader.readAsDataURL(file);
  });
}
</script>
</body></html>
