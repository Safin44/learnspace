<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;

$certs = $conn->query("SELECT cert.*, c.title, i.full_name as instructor_name FROM certificates cert
    JOIN courses c ON cert.course_id=c.id
    JOIN instructors i ON c.instructor_id=i.id
    WHERE cert.user_id=$user_id
    ORDER BY cert.issued_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Certificates - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.cert-card { background:linear-gradient(135deg,#fff5f5,white); border:2px solid var(--primary-bg); border-radius:var(--radius); padding:24px; display:flex; align-items:center; gap:20px; transition:var(--transition); }
.cert-card:hover { border-color:var(--primary); box-shadow:var(--shadow); transform:translateY(-3px); }
.cert-icon { width:70px; height:70px; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; flex-shrink:0; box-shadow:0 4px 16px rgba(204,0,0,0.3); }
.cert-info { flex:1; }
.cert-info h3 { font-size:1.1rem; margin-bottom:4px; }
.cert-info p { color:var(--gray); font-size:0.88rem; margin-bottom:8px; }
</style>
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
      <li><a href="certificates.php" class="active"><i class="fas fa-certificate"></i> My Certificates</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header"><h1><i class="fas fa-certificate" style="color:var(--warning)"></i> My Certificates</h1></div>
    <div class="page-body">
      <?php if ($certs->num_rows === 0): ?>
      <div style="text-align:center;padding:80px;color:var(--gray)">
        <i class="fas fa-certificate" style="font-size:3rem;display:block;margin-bottom:16px;color:var(--gray-border)"></i>
        <h3>No certificates yet</h3>
        <p>Complete a course to earn your first certificate!</p>
        <a href="courses.php" class="btn btn-primary" style="margin-top:16px"><i class="fas fa-rocket"></i> Browse Courses</a>
      </div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:20px">
        <?php while($cert=$certs->fetch_assoc()): ?>
        <div class="cert-card">
          <div class="cert-icon">🏆</div>
          <div class="cert-info">
            <h3><?= htmlspecialchars($cert['title']) ?></h3>
            <p><i class="fas fa-user-tie"></i> <?= htmlspecialchars($cert['instructor_name']) ?></p>
            <p><i class="fas fa-calendar"></i> Completed: <?= date('F d, Y', strtotime($cert['issued_at'])) ?></p>
          </div>
          <a href="certificate.php?course_id=<?= $cert['course_id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> View</a>
        </div>
        <?php endwhile; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
