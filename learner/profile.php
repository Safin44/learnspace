<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    if (empty($full_name)) { $error = 'Full name is required.'; }
    else {
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) { $error = 'Password must be at least 6 chars.'; }
            elseif ($new_password !== $confirm) { $error = 'Passwords do not match.'; }
            else {
                $h = password_hash($new_password, PASSWORD_DEFAULT);
                $s = $conn->prepare("UPDATE users SET full_name=?, bio=?, password=? WHERE id=?");
                $s->bind_param("sssi", $full_name, $bio, $h, $user_id);
                $s->execute();
                $success = 'Profile updated with new password!';
            }
        } else {
            $s = $conn->prepare("UPDATE users SET full_name=?, bio=? WHERE id=?");
            $s->bind_param("ssi", $full_name, $bio, $user_id);
            $s->execute();
            $success = 'Profile updated!';
        }
        if (empty($error)) {
            $_SESSION['user_name'] = $full_name;
            $user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="courses.php"><i class="fas fa-search"></i> Browse Courses</a></li>
      <li><a href="contests.php"><i class="fas fa-trophy"></i> Contests</a></li>
      <li><a href="contest-history.php"><i class="fas fa-history"></i> Contest History</a></li>
      <li><a href="coding-problems.php"><i class="fas fa-code"></i> Coding Problems</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span title="One or more issues resolved" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="certificates.php"><i class="fas fa-certificate"></i> My Certificates</a></li>
      <li><a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header"><h1><i class="fas fa-user-edit"></i> My Profile</h1></div>
    <div class="page-body">
      <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;max-width:900px">
        <div class="card" style="padding:28px;text-align:center;align-self:start">
          <div style="width:90px;height:90px;background:var(--primary-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 16px;border:3px solid var(--primary);font-weight:800;color:var(--primary)">
            <?= strtoupper(substr($user['full_name'],0,1)) ?>
          </div>
          <h3 style="margin-bottom:4px"><?= htmlspecialchars($user['full_name']) ?></h3>
          <p style="color:var(--gray);font-size:0.88rem"><?= htmlspecialchars($user['email']) ?></p>
          <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-border);font-size:0.85rem;color:var(--gray)">
            Member since <?= date('M Y', strtotime($user['created_at'])) ?>
          </div>
        </div>
        <div class="card" style="padding:28px">
          <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
          <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
          <form method="POST">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>
            <div class="form-group">
              <label>Email (read-only)</label>
              <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:var(--gray-light)">
            </div>
            <div class="form-group">
              <label>Bio</label>
              <textarea name="bio" class="form-control" rows="3" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>
            <hr style="margin:20px 0;border-color:var(--gray-border)">
            <p style="font-weight:700;margin-bottom:14px;color:var(--gray)"><i class="fas fa-lock"></i> Change Password (leave blank to keep current)</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Min 6 chars">
              </div>
              <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control">
              </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:13px"><i class="fas fa-save"></i> Save Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
