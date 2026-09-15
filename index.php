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
    if($user&&password_verify($password,$user['password'])){
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
        <p class="lfoot">No account? <a href="register.php">Create one</a></p>
      </form>
    </div>
  </section>
</main>
<script>
const pwd=document.getElementById('lpwd'),eyeBtn=document.getElementById('eyeBtn');
eyeBtn.addEventListener('click',()=>{const h=pwd.type==='password';pwd.type=h?'text':'password';eyeBtn.textContent=h?'Hide':'Show';});
document.getElementById('lf').addEventListener('submit',()=>{const b=document.getElementById('sbtn');b.disabled=true;b.textContent='Signing in…';});
</script>
</body>
</html>
