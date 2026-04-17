<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['instructor_id'])) { header('Location: login.php'); exit; }
$ins_id = $_SESSION['instructor_id'];
$instructor = $conn->query("SELECT * FROM instructors WHERE id=$ins_id")->fetch_assoc();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (!$subject || !$message) {
        $error = "Subject and message are required.";
    } else {
        $type  = 'instructor';
        $name  = $instructor['full_name'];
        $email = $instructor['email'];
        $stmt  = $conn->prepare("INSERT INTO support_messages (sender_type, sender_id, sender_name, sender_email, subject, message) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("sissss", $type, $ins_id, $name, $email, $subject, $message);
        $stmt->execute();
        $success = "Your message has been sent to admin. We'll respond shortly!";
    }
}

$my_messages_q = $conn->query("SELECT * FROM support_messages WHERE sender_type='instructor' AND sender_id=$ins_id ORDER BY created_at DESC");
// Mark resolved messages as seen (closed) now that user has opened this page
$conn->query("UPDATE support_messages SET status='closed' WHERE sender_type='instructor' AND sender_id=$ins_id AND status='resolved'");
$_has_resolved = false;
$my_messages = [];
while ($r = $my_messages_q->fetch_assoc()) $my_messages[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Report an Issue - Instructor</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.report-form{background:white;border-radius:var(--radius);padding:28px;border:1px solid var(--gray-border)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-weight:700;font-size:.88rem;margin-bottom:6px}
.form-group input,.form-group textarea{width:100%;padding:10px 14px;border:1px solid var(--gray-border);border-radius:var(--radius-sm);font-size:.9rem;font-family:inherit;resize:vertical}
.form-group input:focus,.form-group textarea:focus{border-color:var(--primary);outline:none}
.msg-card{background:white;border-radius:var(--radius-sm);padding:16px;margin-bottom:12px;border:1px solid var(--gray-border)}
.status-open{background:#fef9c3;color:#ca8a04;padding:3px 10px;border-radius:50px;font-size:.75rem;font-weight:700}
.status-resolved,.status-closed{background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:50px;font-size:.75rem;font-weight:700}
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace"></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="add-course.php"><i class="fas fa-plus-circle"></i> Add Course</a></li>
      <li><a href="my-courses.php"><i class="fas fa-book"></i> My Courses</a></li>
      <li><a href="report-issue.php" class="active"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span title="One or more issues resolved" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-flag" style="color:var(--primary)"></i> Report an Issue</h1>
    </div>
    <div class="page-body">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px">
        <div>
          <div class="section-title"><i class="fas fa-paper-plane" style="color:var(--primary)"></i> Send a Message to Admin</div>
          <div class="report-form">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:14px;background:var(--primary-bg);border-radius:var(--radius-sm)">
              <div class="avatar"><?= strtoupper(substr($instructor['full_name'],0,1)) ?></div>
              <div>
                <div style="font-weight:700"><?= htmlspecialchars($instructor['full_name']) ?></div>
                <div style="font-size:.8rem;color:var(--gray)"><?= htmlspecialchars($instructor['email']) ?> &nbsp;·&nbsp; Instructor</div>
              </div>
            </div>
            <form method="POST">
              <div class="form-group">
                <label><i class="fas fa-tag"></i> Subject *</label>
                <input type="text" name="subject" placeholder="e.g. Course approval issue, student complaint..." required>
              </div>
              <div class="form-group">
                <label><i class="fas fa-comment-alt"></i> Message *</label>
                <textarea name="message" rows="6" placeholder="Describe the issue in detail..." required></textarea>
              </div>
              <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send to Admin</button>
            </form>
          </div>
        </div>

        <div>
          <div class="section-title"><i class="fas fa-history" style="color:var(--primary)"></i> My Previous Messages</div>
          <?php if (empty($my_messages)): ?>
          <div style="text-align:center;padding:40px;color:var(--gray)">
            <i class="fas fa-inbox" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.2"></i>
            <p>No messages sent yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($my_messages as $m): ?>
          <div class="msg-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
              <strong style="font-size:.92rem"><?= htmlspecialchars($m['subject']) ?></strong>
              <span class="status-<?= $m['status'] ?>"><?= $m['status']==='closed' ? 'Resolved' : ucfirst($m['status']) ?></span>
            </div>
            <p style="font-size:.85rem;color:var(--gray);margin-bottom:6px;line-height:1.6"><?= nl2br(htmlspecialchars(substr($m['message'],0,120))) ?><?= strlen($m['message'])>120?'...':'' ?></p>
            <?php if(!empty($m['admin_comment'])): ?>
            <div style="background:#f0fdf4;border-left:3px solid #16a34a;border-radius:4px;padding:9px 12px;margin:8px 0;font-size:.82rem;color:#166534">
              <strong><i class="fas fa-reply"></i> Admin Reply:</strong><br><?= nl2br(htmlspecialchars($m['admin_comment'])) ?>
            </div>
            <?php endif; ?>
            <div style="font-size:.75rem;color:var(--gray)"><i class="fas fa-clock"></i> <?= date('M d, Y H:i', strtotime($m['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
