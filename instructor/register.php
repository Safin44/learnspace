<?php
session_start();
require_once '../includes/db.php';

if (isset($_SESSION['instructor_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    $expertise = trim($_POST['expertise'] ?? '');

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = $conn->prepare("SELECT id FROM instructors WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email already registered as instructor.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO instructors (full_name, email, password, expertise) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $full_name, $email, $hashed, $expertise);
            if ($stmt->execute()) {
                $success = 'Registration successful! You can now login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instructor Register - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
  background: linear-gradient(135deg, #f8f9fc 0%, #fff5f5 100%);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.auth-page {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
}
.auth-card {
  background: white;
  border-radius: 24px;
  padding: 40px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.1);
  border: 1px solid var(--gray-border);
}
.auth-header { text-align: center; margin-bottom: 28px; }
.auth-icon { font-size: 2.5rem; margin-bottom: 8px; }
.auth-header h2 { font-size: 1.6rem; margin-bottom: 4px; }
.auth-header p { color: var(--gray); font-size: 0.9rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
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
    <a href="login.php" class="btn btn-outline">Login</a>
  </div>
</nav>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-icon">👨‍🏫</div>
      <h2>Become an Instructor</h2>
      <p>Share your knowledge with thousands of learners</p>
    </div>

    <div class="role-tabs">
      <a href="../learner/register.php" class="role-tab">🎓 Learner</a>
      <a href="register.php" class="role-tab active">👨‍🏫 Instructor</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?> <a href="login.php">Login now</a></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Full Name *</label>
        <div class="input-icon-wrap">
          <i class="fas fa-user input-icon"></i>
          <input type="text" name="full_name" class="form-control" placeholder="Your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Email Address *</label>
        <div class="input-icon-wrap">
          <i class="fas fa-envelope input-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Area of Expertise</label>
        <div class="input-icon-wrap">
          <i class="fas fa-lightbulb input-icon"></i>
          <input type="text" name="expertise" class="form-control" placeholder="e.g. Web Development, Data Science" value="<?= htmlspecialchars($_POST['expertise'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
        </div>
        <div class="form-group">
          <label>Confirm Password *</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;font-size:1rem;border-radius:12px">
        <i class="fas fa-chalkboard-teacher"></i> Create Instructor Account
      </button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="login.php">Login here</a>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
