<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];

$course_id = intval($_GET['course_id'] ?? 0);

// Check certificate exists
$cert = $conn->query("SELECT * FROM certificates WHERE user_id=$user_id AND course_id=$course_id")->fetch_assoc();
if (!$cert) {
    // Check if all units done
    $total = $conn->query("SELECT COUNT(*) as c FROM units WHERE course_id=$course_id")->fetch_assoc()['c'];
    $done  = $conn->query("SELECT COUNT(*) as c FROM unit_completions WHERE user_id=$user_id AND unit_id IN (SELECT id FROM units WHERE course_id=$course_id)")->fetch_assoc()['c'];
    if ($done >= $total && $total > 0) {
        $conn->query("INSERT IGNORE INTO certificates (user_id, course_id) VALUES ($user_id, $course_id)");
        $cert = $conn->query("SELECT * FROM certificates WHERE user_id=$user_id AND course_id=$course_id")->fetch_assoc();
    } else {
        header('Location: course-learn.php?id='.$course_id);
        exit;
    }
}

$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$course = $conn->query("SELECT c.*, i.full_name as instructor_name FROM courses c JOIN instructors i ON c.instructor_id=i.id WHERE c.id=$course_id")->fetch_assoc();

$issue_date = date('F d, Y', strtotime($cert['issued_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background: linear-gradient(135deg, #1a0000, #3d0000); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; font-family: 'Nunito', sans-serif; }

.cert-actions { display:flex; gap:12px; margin-bottom:28px; }

.certificate {
  width: 900px;
  max-width: 100%;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 30px 80px rgba(0,0,0,0.5);
  position: relative;
}

.cert-border {
  padding: 8px;
  background: linear-gradient(135deg, #cc0000, #ff6666, #cc0000, #990000);
}

.cert-inner {
  background: white;
  padding: 50px 60px;
  position: relative;
  overflow: hidden;
}

.cert-watermark {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-30deg);
  width: 420px;
  height: 180px;
  opacity: 0.06;
  pointer-events: none;
}

.cert-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--primary-bg);
}

.cert-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-family: 'Poppins', sans-serif;
  font-size: 1.4rem;
  font-weight: 800;
}

.cert-logo .logo-icon {
  width: 44px;
  height: 44px;
  background: var(--primary);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}

.cert-logo span { color: var(--primary); }

.cert-seal {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #cc0000, #990000);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.6rem;
  font-weight: 800;
  text-align: center;
  box-shadow: 0 4px 16px rgba(204,0,0,0.4);
  line-height: 1.3;
}

.cert-seal i { font-size: 1.4rem; margin-bottom: 4px; }

.cert-title {
  text-align: center;
  margin-bottom: 24px;
}

.cert-title .label {
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: var(--gray);
  margin-bottom: 6px;
}

.cert-title h1 {
  font-size: 2.2rem;
  color: var(--primary);
  font-family: 'Poppins', sans-serif;
  margin-bottom: 4px;
}

.cert-title .subtitle {
  font-size: 1rem;
  color: var(--gray);
}

.cert-recipient {
  text-align: center;
  margin: 20px 0;
}

.cert-recipient .presented-to {
  font-size: 0.88rem;
  color: var(--gray);
  text-transform: uppercase;
  letter-spacing: 0.15em;
  margin-bottom: 10px;
}

.cert-recipient .name {
  font-size: 2.8rem;
  font-family: 'Poppins', sans-serif;
  font-weight: 800;
  color: var(--black);
  border-bottom: 3px solid var(--primary);
  display: inline-block;
  padding-bottom: 6px;
  margin-bottom: 14px;
}

.cert-recipient .completed-text {
  font-size: 0.95rem;
  color: var(--gray);
  margin-bottom: 12px;
}

.cert-recipient .course-name {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--primary-dark);
  margin-bottom: 6px;
}

.cert-recipient .instructor {
  font-size: 0.88rem;
  color: var(--gray);
}

.cert-footer {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 36px;
  padding-top: 20px;
  border-top: 1px solid var(--gray-border);
}

.cert-signature {
  text-align: center;
}

.sig-line {
  width: 180px;
  border-bottom: 2px solid var(--black);
  margin-bottom: 6px;
  padding-bottom: 4px;
  font-style: italic;
  font-size: 1.3rem;
  font-family: 'Poppins', sans-serif;
  color: var(--primary-dark);
}

.cert-signature .sig-label {
  font-size: 0.78rem;
  color: var(--gray);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.cert-date {
  text-align: right;
}

.cert-date .date-label {
  font-size: 0.78rem;
  color: var(--gray);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 4px;
}

.cert-date .date-value {
  font-weight: 700;
  font-size: 0.95rem;
}

.cert-id {
  text-align: center;
  font-size: 0.75rem;
  color: var(--gray);
}

@media print {
  body { background: white; padding: 0; }
  .cert-actions { display: none; }
  .certificate { box-shadow: none; width: 100%; }
}
</style>
</head>
<body>

<div class="cert-actions">
  <a href="dashboard.php" class="btn btn-outline" style="border-color:white;color:white"><i class="fas fa-arrow-left"></i> Dashboard</a>
  <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print / Save as PDF</button>
  <a href="course-learn.php?id=<?= $course_id ?>" class="btn btn-outline" style="border-color:white;color:white"><i class="fas fa-book-open"></i> Back to Course</a>
</div>

<div class="certificate" id="certificate">
  <div class="cert-border">
    <div class="cert-inner">
      <div class="cert-watermark"><img src="../assets/images/logo.png" alt="" style="width:100%;height:100%;object-fit:contain;opacity:1;"></div>

      <!-- Header -->
      <div class="cert-header">
        <div class="cert-logo">
          <img src="../assets/images/logo.png" alt="LearnSpace" style="height:80px;object-fit:contain;">
        </div>
        <div class="cert-seal">
          <i class="fas fa-award"></i>
          CERTIFIED
        </div>
      </div>

      <!-- Title -->
      <div class="cert-title">
        <div class="label">Certificate of Completion</div>
        <h1>🏆 Achievement Unlocked</h1>
        <div class="subtitle">This certificate is proudly presented to</div>
      </div>

      <!-- Recipient -->
      <div class="cert-recipient">
        <div class="presented-to">This is to certify that</div>
        <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="completed-text">has successfully completed the course</div>
        <div class="course-name"><?= htmlspecialchars($course['title']) ?></div>
        <div class="instructor">Taught by <?= htmlspecialchars($course['instructor_name']) ?></div>
      </div>

      <!-- Footer -->
      <div class="cert-footer">
        <div class="cert-id">
          Certificate ID: LS-<?= str_pad($user_id,4,'0',STR_PAD_LEFT) ?>-<?= str_pad($course_id,4,'0',STR_PAD_LEFT) ?><br>
          <span style="color:var(--success)"><i class="fas fa-shield-alt"></i> Verified Certificate</span>
        </div>
        <div class="cert-date">
          <div class="date-label">Date of Completion</div>
          <div class="date-value"><?= $issue_date ?></div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
