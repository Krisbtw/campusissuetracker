<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';

if (isLoggedIn()) { redirectToDashboard(); }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $role       = $_POST['role'] ?? 'student';
    $department = trim($_POST['department'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($department)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, ['student','staff'])) {
        $error = 'Invalid role selected.';
    } else {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, department, phone) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name, $email, $hashed, $role, $department, $phone]);
            $success = 'Account created successfully! You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account – FixMyCampus</title>
<meta name="description" content="Create a FixMyCampus account.">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page auth-register-page">
<main class="auth-wrapper">
  <div class="auth-card auth-register-card">
    <div class="auth-brand">FixMyCampus</div>
    <h1 class="auth-card-title">Create account</h1>

    <?php if ($error): ?>
      <div class="alert-banner alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert-banner alert-success" role="alert"><?= htmlspecialchars($success) ?>
        <a href="index.php" class="auth-link">Sign in</a>
      </div>
    <?php endif; ?>

    <form method="POST" id="regForm" class="auth-form">
      <div class="field-group">
        <label class="form-label" for="full_name">Full name *</label>
        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Jane Doe" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
      </div>
      <div class="field-group">
        <label class="form-label" for="reg_email">Email *</label>
        <input type="email" id="reg_email" name="email" class="form-control" placeholder="you@campus.edu" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="row">
        <div class="col-md-6">
          <label class="form-label" for="role">Role *</label>
          <select id="role" name="role" class="form-control">
            <option value="student" <?= ($_POST['role']??'student')==='student'?'selected':'' ?>>Student</option>
            <option value="staff"   <?= ($_POST['role']??'')==='staff'?'selected':'' ?>>Staff</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="department">Department *</label>
          <input type="text" id="department" name="department" class="form-control" placeholder="e.g. Computer Science" required value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
        </div>
      </div>
      <div class="field-group">
        <label class="form-label" for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" class="form-control" placeholder="9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
      <div class="row">
        <div class="col-md-6">
          <label class="form-label" for="password">Password *</label>
          <div class="auth-password-wrap">
            <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
            <button type="button" class="eye-toggle-btn" data-target="password" aria-label="Toggle password visibility">
              <i class="bi bi-eye eye-open"></i>
              <i class="bi bi-eye-slash eye-closed" style="display:none;"></i>
            </button>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="confirm_password">Confirm password *</label>
          <div class="auth-password-wrap">
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
            <button type="button" class="eye-toggle-btn" data-target="confirm_password" aria-label="Toggle confirm password visibility">
              <i class="bi bi-eye eye-open"></i>
              <i class="bi bi-eye-slash eye-closed" style="display:none;"></i>
            </button>
          </div>
        </div>
      </div>
      <button type="submit" class="auth-submit">Create account</button>
    </form>

    <p class="auth-signin" style="margin-top:16px;">
      Already have an account? <a href="index.php" class="auth-link">Sign in</a>
    </p>
  </div>
</main>
<script>
document.querySelectorAll('.eye-toggle-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const targetId = btn.getAttribute('data-target');
    const input = document.getElementById(targetId);
    if (input) {
      const isPwd = input.type === 'password';
      input.type = isPwd ? 'text' : 'password';
      const openIcon = btn.querySelector('.eye-open');
      const closedIcon = btn.querySelector('.eye-closed');
      if (openIcon && closedIcon) {
        openIcon.style.display = isPwd ? 'none' : 'inline-block';
        closedIcon.style.display = isPwd ? 'inline-block' : 'none';
      }
    }
  });
});
</script>
</body>
</html>
