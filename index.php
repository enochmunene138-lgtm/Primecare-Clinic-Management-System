<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            logActivity('Login', 'auth', $user['user_id'], 'User logged in');
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
$msg = clean($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — PrimeCare CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
  --primary: #1a5c8a;
  --accent:  #00b4a6;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  display: flex;
  background: #f0f4f8;
}
.login-left {
  flex: 1;
  background: linear-gradient(145deg, #123f61 0%, #1a5c8a 50%, #00b4a6 100%);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 48px;
  color: #fff;
  position: relative;
  overflow: hidden;
}
.login-left::before {
  content: '';
  position: absolute;
  width: 400px; height: 400px;
  background: rgba(255,255,255,.04);
  border-radius: 50%;
  top: -100px; left: -100px;
}
.login-left::after {
  content: '';
  position: absolute;
  width: 300px; height: 300px;
  background: rgba(255,255,255,.04);
  border-radius: 50%;
  bottom: -50px; right: -50px;
}
.login-brand { text-align: center; z-index: 1; }
.brand-icon {
  width: 80px; height: 80px;
  background: rgba(255,255,255,.15);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 40px;
  margin: 0 auto 16px;
  border: 2px solid rgba(255,255,255,.2);
}
.brand-title {
  font-family: 'Playfair Display', serif;
  font-size: 36px;
  font-weight: 700;
}
.brand-sub {
  font-size: 14px;
  opacity: .65;
  margin-top: 6px;
}
.features {
  margin-top: 48px;
  z-index: 1;
  width: 100%;
  max-width: 340px;
}
.feature {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
  background: rgba(255,255,255,.08);
  padding: 14px 18px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,.12);
}
.feature i { font-size: 22px; color: var(--accent); flex-shrink: 0; }
.feature-text strong { display: block; font-size: 13.5px; font-weight: 600; }
.feature-text span   { font-size: 12px; opacity: .65; }
.login-right {
  width: 460px;
  flex-shrink: 0;
  background: #fff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 48px 44px;
}
.login-heading { margin-bottom: 32px; }
.login-heading h2 {
  font-family: 'Playfair Display', serif;
  font-size: 28px;
  color: var(--primary);
}
.login-heading p {
  font-size: 13.5px;
  color: #718096;
  margin-top: 6px;
}
.form-group  { margin-bottom: 18px; }
.form-label  {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 6px;
}
.input-wrap  { position: relative; }
.input-wrap i {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  color: #a0aec0;
  font-size: 17px;
}
.form-input {
  width: 100%;
  padding: 11px 14px 11px 40px;
  border: 1.5px solid #e2e8f0;
  border-radius: 9px;
  font-size: 14px;
  font-family: inherit;
  color: #1a202c;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.form-input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(26,92,138,.1);
}
.alert-error {
  background: #fff5f5;
  color: #c53030;
  padding: 11px 14px;
  border-radius: 8px;
  font-size: 13.5px;
  margin-bottom: 18px;
  border: 1px solid #fed7d7;
  display: flex;
  align-items: center;
  gap: 8px;
}
.alert-info {
  background: #ebf8ff;
  color: #2b6cb0;
  padding: 11px 14px;
  border-radius: 8px;
  font-size: 13.5px;
  margin-bottom: 18px;
  border: 1px solid #bee3f8;
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-login {
  width: 100%;
  padding: 12px;
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: background .2s;
}
.btn-login:hover { background: #123f61; }
.login-footer {
  text-align: center;
  margin-top: 24px;
  font-size: 12.5px;
  color: #a0aec0;
}
.demo-accounts {
  margin-top: 24px;
  padding: 14px;
  background: #f7fafc;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
}
.demo-accounts h4 {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: #718096;
  margin-bottom: 10px;
}
.demo-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #4a5568;
  margin-bottom: 5px;
}
@media (max-width: 768px) {
  .login-left { display: none; }
  .login-right { width: 100%; padding: 32px 24px; }
}
</style>
</head>
<body>

<div class="login-left">
    <div class="login-brand">
        <div class="brand-icon"><i class="bi bi-hospital"></i></div>
        <div class="brand-title">PrimeCare</div>
        <div class="brand-sub">Clinic Management System</div>
    </div>
    <div class="features">
        <div class="feature">
            <i class="bi bi-people-fill"></i>
            <div class="feature-text">
                <strong>Patient Management</strong>
                <span>Complete digital patient records</span>
            </div>
        </div>
        <div class="feature">
            <i class="bi bi-calendar-check-fill"></i>
            <div class="feature-text">
                <strong>Smart Appointments</strong>
                <span>Conflict-free scheduling</span>
            </div>
        </div>
        <div class="feature">
            <i class="bi bi-clipboard-pulse-fill"></i>
            <div class="feature-text">
                <strong>Clinical Records</strong>
                <span>Diagnosis, prescriptions & vitals</span>
            </div>
        </div>
        <div class="feature">
            <i class="bi bi-receipt-cutoff"></i>
            <div class="feature-text">
                <strong>Automated Billing</strong>
                <span>Instant invoices & payment tracking</span>
            </div>
        </div>
    </div>
</div>

<div class="login-right">
    <div class="login-heading">
        <h2>Welcome Back</h2>
        <p>Sign in to your PrimeCare account to continue.</p>
    </div>

    <?php if ($error): ?>
    <div class="alert-error">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= clean($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($msg): ?>
    <div class="alert-info">
        <i class="bi bi-info-circle-fill"></i>
        <?= clean($msg) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Username</label>
            <div class="input-wrap">
                <i class="bi bi-person"></i>
                <input type="text" name="username" class="form-input"
                    placeholder="Enter your username"
                    value="<?= clean($_POST['username'] ?? '') ?>"
                    required autofocus>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock"></i>
                <input type="password" name="password" class="form-input"
                    placeholder="Enter your password" required>
            </div>
        </div>
        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i> Sign In
        </button>
    </form>

    <div class="demo-accounts">
        <h4>Demo Credentials — Password: <strong>password</strong></h4>
        <div class="demo-row">
            <span><strong>admin</strong></span>
            <span>System Administrator</span>
        </div>
        <div class="demo-row">
            <span><strong>dr.amina</strong></span>
            <span>Doctor</span>
        </div>
        <div class="demo-row">
            <span><strong>receptionist</strong></span>
            <span>Receptionist</span>
        </div>
    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> PrimeCare Clinic Management System
    </div>
</div>
</body>
</html>