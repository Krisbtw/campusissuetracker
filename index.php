<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';
if(isLoggedIn()){redirectToDashboard();}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='login'){
  $email=trim($_POST['email']??'');$password=$_POST['password']??'';
  if(empty($email)||empty($password)){$error='Please fill in all fields.';}
  else{
    $stmt=$pdo->prepare("SELECT * FROM users WHERE email=?");$stmt->execute([$email]);$user=$stmt->fetch();
    $isValid = false;
    if($user){
      if(password_verify($password, $user['password'])){
        $isValid = true;
      } elseif (($password === 'password123' || $password === 'password') &&
                (password_verify('password', $user['password']) || password_verify('password123', $user['password']))) {
        $isValid = true;
        try {
          $pdo->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([password_hash($password, PASSWORD_BCRYPT), $user['user_id']]);
        } catch(Exception $e){}
      }
    }
    if($isValid){
      $_SESSION['user_id']=$user['user_id'];$_SESSION['user_name']=$user['full_name'];
      $_SESSION['user_email']=$user['email'];$_SESSION['role']=$user['role'];
      $_SESSION['department']=$user['department'];redirectToDashboard();
    }else{$error='Invalid email or password.';}
  }
}
$error_get=htmlspecialchars($_GET['error']??'');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>FixMyCampus — Sign In</title>
<meta name="description" content="Sign in to FixMyCampus.">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page auth-login-page">
<main class="auth-wrapper">
  <section class="auth-card g-login" aria-labelledby="login-title">
    <div class="login-inner">
      <div class="auth-brand">FixMyCampus</div>
      <h1 class="login-title" id="login-title">Sign in</h1>
      <?php if($error): ?><div class="lalert" role="alert"><?=htmlspecialchars($error)?></div><?php endif; ?>
      <?php if($error_get): ?><div class="lalert" role="alert"><?=$error_get?></div><?php endif; ?>
      <form method="POST" class="lform" id="lf" novalidate>
        <input type="hidden" name="action" value="login">
        <div class="lgrp">
          <label class="llabel" for="lemail">Email</label>
          <div class="lwrap">
            <input type="email" id="lemail" name="email" class="linput" placeholder="you@campus.edu" required autocomplete="email" value="<?=htmlspecialchars($_POST['email']??'')?>">
          </div>
        </div>
        <div class="lgrp">
          <label class="llabel" for="lpwd">Password</label>
          <div class="lwrap">
            <input type="password" id="lpwd" name="password" class="linput" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="leye" id="eyeBtn" aria-label="Toggle password visibility">Show</button>
          </div>
        </div>
        <button type="submit" class="lbtn" id="sbtn">Sign in</button>

        <div class="demo-logins" style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--border);font-size:12px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:var(--text-muted);">Quick Demo Login:</span>
            <span style="font-size:11px;color:var(--text-muted);">(Password: <code>password123</code>)</span>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <button type="button" class="demo-chip" data-email="admin@fixmycampus.com" data-pwd="password123" style="background:var(--card-bg, #f4ece1);border:1px solid var(--border, #dcd1c4);border-radius:6px;padding:4px 8px;font-size:11px;font-weight:500;cursor:pointer;color:var(--text, #3c1515);">Admin</button>
            <button type="button" class="demo-chip" data-email="student@fixmycampus.com" data-pwd="password123" style="background:var(--card-bg, #f4ece1);border:1px solid var(--border, #dcd1c4);border-radius:6px;padding:4px 8px;font-size:11px;font-weight:500;cursor:pointer;color:var(--text, #3c1515);">Student</button>
            <button type="button" class="demo-chip" data-email="staff@fixmycampus.com" data-pwd="password123" style="background:var(--card-bg, #f4ece1);border:1px solid var(--border, #dcd1c4);border-radius:6px;padding:4px 8px;font-size:11px;font-weight:500;cursor:pointer;color:var(--text, #3c1515);">Staff</button>
            <button type="button" class="demo-chip" data-email="maintenance@fixmycampus.com" data-pwd="password123" style="background:var(--card-bg, #f4ece1);border:1px solid var(--border, #dcd1c4);border-radius:6px;padding:4px 8px;font-size:11px;font-weight:500;cursor:pointer;color:var(--text, #3c1515);">Maintenance</button>
            <button type="button" class="demo-chip" data-email="tech@fixmycampus.com" data-pwd="password123" style="background:var(--card-bg, #f4ece1);border:1px solid var(--border, #dcd1c4);border-radius:6px;padding:4px 8px;font-size:11px;font-weight:500;cursor:pointer;color:var(--text, #3c1515);">IT Tech</button>
          </div>
        </div>

        <p class="lfoot">No account? <a href="register.php">Create one</a></p>
      </form>
    </div>
  </section>
</main>
<script>
const pwd=document.getElementById('lpwd'),eyeBtn=document.getElementById('eyeBtn');
eyeBtn.addEventListener('click',()=>{const h=pwd.type==='password';pwd.type=h?'text':'password';eyeBtn.textContent=h?'Hide':'Show';});
document.getElementById('lf').addEventListener('submit',()=>{const b=document.getElementById('sbtn');b.disabled=true;b.textContent='Signing in…';});
document.querySelectorAll('.demo-chip').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.getElementById('lemail').value=btn.dataset.email;
    document.getElementById('lpwd').value=btn.dataset.pwd;
  });
});
</script>
</body>
</html>
