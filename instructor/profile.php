<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['instructor_id'])) { header('Location: login.php'); exit; }
$ins_id = $_SESSION['instructor_id'];

$instructor = $conn->query("SELECT * FROM instructors WHERE id=$ins_id")->fetch_assoc();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $expertise = trim($_POST['expertise'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (empty($full_name)) {
        $error = 'Full name is required.';
    } else {
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif ($new_password !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE instructors SET full_name=?, bio=?, expertise=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $full_name, $bio, $expertise, $hashed, $ins_id);
                $stmt->execute();
                $success = 'Profile updated with new password!';
            }
        } else {
            $stmt = $conn->prepare("UPDATE instructors SET full_name=?, bio=?, expertise=? WHERE id=?");
            $stmt->bind_param("sssi", $full_name, $bio, $expertise, $ins_id);
            $stmt->execute();
            $success = 'Profile updated successfully!';
        }
        if (empty($error)) {
            $_SESSION['instructor_name'] = $full_name;
            $instructor = $conn->query("SELECT * FROM instructors WHERE id=$ins_id")->fetch_assoc();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile - Instructor</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="add-course.php"><i class="fas fa-plus-circle"></i> Add Course</a></li>
      <li><a href="my-courses.php"><i class="fas fa-book"></i> My Courses</a></li>
      <li><a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header"><h1><i class="fas fa-user-edit"></i> Update Profile</h1></div>
    <div class="page-body">
      <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;max-width:900px">

        <!-- Profile Card -->
        <div class="card" style="padding:28px;text-align:center;align-self:start">
          <div style="width:90px;height:90px;background:var(--primary-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 16px;border:3px solid var(--primary)">
            👨‍🏫
          </div>
          <h3 style="margin-bottom:4px"><?= htmlspecialchars($instructor['full_name']) ?></h3>
          <p style="color:var(--gray);font-size:0.88rem;margin-bottom:8px"><?= htmlspecialchars($instructor['email']) ?></p>
          <?php if($instructor['expertise']): ?>
          <span class="badge badge-approved"><?= htmlspecialchars($instructor['expertise']) ?></span>
          <?php endif; ?>
          <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-border);font-size:0.85rem;color:var(--gray)">
            Member since <?= date('M Y', strtotime($instructor['created_at'])) ?>
          </div>
        </div>

        <!-- Edit Form -->
        <div class="card" style="padding:28px">
          <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
          <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>

          <form method="POST">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($instructor['full_name']) ?>" required>
            </div>
            <div class="form-group">
              <label>Email (read-only)</label>
              <input type="email" class="form-control" value="<?= htmlspecialchars($instructor['email']) ?>" disabled style="background:var(--gray-light)">
            </div>
            <div class="form-group">
              <label>Area of Expertise</label>
              <input type="text" name="expertise" class="form-control" value="<?= htmlspecialchars($instructor['expertise'] ?? '') ?>" placeholder="e.g. Web Development, Data Science">
            </div>
            <div class="form-group">
              <label>Bio</label>
              <textarea name="bio" class="form-control" rows="4" placeholder="Tell students about yourself..."><?= htmlspecialchars($instructor['bio'] ?? '') ?></textarea>
            </div>
            <hr style="margin:20px 0;border-color:var(--gray-border)">
            <p style="font-weight:700;margin-bottom:14px;color:var(--gray)"><i class="fas fa-lock"></i> Change Password (leave blank to keep current)</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters">
              </div>
              <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password">
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
