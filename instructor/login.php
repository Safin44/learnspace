<?php
session_start();
require_once '../includes/db.php';

if (isset($_SESSION['instructor_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM instructors WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $ins = $stmt->get_result()->fetch_assoc();
        if ($ins && password_verify($password, $ins['password'])) {
            if ($ins['status'] === 'blocked') {
                $error = 'Your account has been blocked. Contact admin.';
            } else {
                $_SESSION['instructor_id'] = $ins['id'];
                $_SESSION['instructor_name'] = $ins['full_name'];
                $_SESSION['instructor_email'] = $ins['email'];
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instructor Login - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background: linear-gradient(135deg, #f8f9fc 0%, #fff5f5 100%); min-height: 100vh; display: flex; flex-direction: column; }
.auth-page { flex: 1; display: flex; align-items: center; justify-content: center; padding: 60px 20px; }
.auth-card { background: white; border-radius: 24px; padding: 40px; width: 100%; max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); border: 1px solid var(--gray-border); }
.auth-header { text-align: center; margin-bottom: 28px; }
.auth-icon { font-size: 2.5rem; margin-bottom: 8px; }
.auth-header h2 { font-size: 1.6rem; margin-bottom: 4px; }
.auth-header p { color: var(--gray); font-size: 0.9rem; }
.input-icon-wrap { position: relative; }
.input-icon-wrap .form-control { padding-left: 42px; }
.input-icon-wrap .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gray); }
.auth-footer { text-align: center; margin-top: 20px; font-size: 0.9rem; color: var(--gray); }
.auth-footer a { color: var(--primary); font-weight: 700; }
.role-tabs { display: flex; gap: 8px; margin-bottom: 24px; background: var(--gray-light); padding: 4px; border-radius: 12px; }
.role-tab { flex: 1; padding: 10px; border-radius: 10px; text-align: center; font-weight: 700; font-size: 0.88rem; cursor: pointer; text-decoration: none; color: var(--gray); transition: var(--transition); }
.role-tab.active { background: white; color: var(--primary); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
</style>
</head>
<body>
<nav class="navbar">
  <a href="../index.php" class="navbar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></a>
  <ul class="nav-links">
    <li><a href="../index.php">Home</a></li>
    <li><a href="../learner/courses.php">Courses</a></li>
  </ul>
  <div class="nav-actions">
    <a href="register.php" class="btn btn-outline">Register</a>
  </div>
</nav>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-icon">👨‍🏫</div>
      <h2>Instructor Login</h2>
      <p>Welcome back! Access your teaching dashboard</p>
    </div>
    <div class="role-tabs">
      <a href="../learner/login.php" class="role-tab">🎓 Learner</a>
      <a href="login.php" class="role-tab active">👨‍🏫 Instructor</a>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label>Email Address</label>
        <div class="input-icon-wrap">
          <i class="fas fa-envelope input-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="input-icon-wrap">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" class="form-control" placeholder="Your password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;font-size:1rem;border-radius:12px">
        <i class="fas fa-sign-in-alt"></i> Login to Dashboard
      </button>
    </form>
    <div class="auth-footer">
      Don't have an account? <a href="register.php">Register as Instructor</a>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
