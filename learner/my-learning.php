<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;

$courses = $conn->query("SELECT c.*, i.full_name as instructor_name, e.enrolled_at,
    (SELECT COUNT(*) FROM units WHERE course_id=c.id) as total_units,
    (SELECT COUNT(*) FROM unit_completions WHERE user_id=$user_id AND unit_id IN (SELECT id FROM units WHERE course_id=c.id)) as completed_units
    FROM enrollments e
    JOIN courses c ON e.course_id=c.id
    JOIN instructors i ON c.instructor_id=i.id
    WHERE e.user_id=$user_id
    ORDER BY e.enrolled_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Learning - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.progress-bar-wrap { background:var(--gray-border); border-radius:50px; height:8px; margin-top:6px; }
.progress-bar-fill { background:var(--primary); border-radius:50px; height:8px; }
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="courses.php"><i class="fas fa-search"></i> Browse Courses</a></li>
      <li><a href="my-learning.php" class="active"><i class="fas fa-book-open"></i> My Learning</a></li>
      <li><a href="contests.php"><i class="fas fa-trophy"></i> Contests</a></li>
      <li><a href="contest-history.php"><i class="fas fa-history"></i> Contest History</a></li>
      <li><a href="coding-problems.php"><i class="fas fa-code"></i> Coding Problems</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span title="One or more issues resolved" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="certificates.php"><i class="fas fa-certificate"></i> My Certificates</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-book-open"></i> My Learning</h1>
      <a href="courses.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Enroll More</a>
    </div>
    <div class="page-body">
      <?php $emojis=['💻','📊','🎨','📈','🔬','📱','🌐','🎯']; $ei=0;
      while($c=$courses->fetch_assoc()):
        $pct = $c['total_units'] > 0 ? round(($c['completed_units']/$c['total_units'])*100) : 0;
      ?>
      <div class="card" style="padding:20px;margin-bottom:16px;display:flex;align-items:center;gap:20px">
        <div style="width:60px;height:60px;background:var(--primary-bg);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0">
          <?= $emojis[$ei++%count($emojis)] ?>
        </div>
        <div style="flex:1">
          <h4 style="margin-bottom:4px"><?= htmlspecialchars($c['title']) ?></h4>
          <p style="font-size:0.83rem;color:var(--gray);margin-bottom:8px"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['instructor_name']) ?> · Enrolled <?= date('M d, Y',strtotime($c['enrolled_at'])) ?></p>
          <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--gray)">
            <span><?= $c['completed_units'] ?>/<?= $c['total_units'] ?> units</span>
            <span><?= $pct ?>%</span>
          </div>
          <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
          <a href="course-learn.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-play"></i> Continue</a>
          <?php if($pct>=100): ?>
          <a href="certificate.php?course_id=<?= $c['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-certificate"></i> Certificate</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
