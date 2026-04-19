<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;

$enrolled_courses = $conn->query("SELECT c.*, i.full_name as instructor_name,
    e.enrolled_at,
    (SELECT COUNT(*) FROM lessons WHERE unit_id IN (SELECT id FROM units WHERE course_id=c.id)) as total_lessons,
    (SELECT COUNT(*) FROM lesson_completions lc JOIN lessons l ON lc.lesson_id=l.id JOIN units u ON l.unit_id=u.id WHERE lc.user_id=$user_id AND lc.is_completed=1 AND u.course_id=c.id) as completed_lessons
    FROM enrollments e
    JOIN courses c ON e.course_id=c.id
    JOIN instructors i ON c.instructor_id=i.id
    WHERE e.user_id=$user_id
    ORDER BY e.enrolled_at DESC");

$total_enrolled = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE user_id=$user_id")->fetch_assoc()['c'];
$total_completed = $conn->query("SELECT COUNT(*) as c FROM certificates WHERE user_id=$user_id")->fetch_assoc()['c'];

$user_info = $conn->query("SELECT current_streak FROM users WHERE id=$user_id")->fetch_assoc();
$current_streak = $user_info['current_streak'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Dashboard - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.progress-bar-wrap { background:var(--gray-border); border-radius:50px; height:8px; margin-top:6px; }
.progress-bar-fill { background:var(--primary); border-radius:50px; height:8px; transition:width 0.5s ease; }
.enrolled-card { background:white; border-radius:var(--radius); border:1px solid var(--gray-border); padding:18px; margin-bottom:14px; display:flex; align-items:center; gap:16px; transition:var(--transition); }
.enrolled-card:hover { box-shadow:var(--shadow); transform:translateY(-2px); }
.course-emoji { width:54px; height:54px; background:var(--primary-bg); border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:1.6rem; flex-shrink:0; }
.enrolled-info { flex:1; }
.enrolled-info h4 { font-size:1rem; margin-bottom:4px; }
.enrolled-info .meta { font-size:0.82rem; color:var(--gray); }
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="courses.php"><i class="fas fa-search"></i> Browse Courses</a></li>
      <li><a href="contests.php"><i class="fas fa-trophy"></i> Contests</a></li>
      <li><a href="contest-history.php"><i class="fas fa-history"></i> Contest History</a></li>
      <li><a href="coding-problems.php"><i class="fas fa-code"></i> Coding Problems</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span title="One or more issues resolved" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="certificates.php"><i class="fas fa-certificate"></i> My Certificates</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1>👋 Hello, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
      <div style="display:flex;align-items:center;gap:10px">
        <div class="avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
        <span style="font-weight:700"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
      </div>
    </div>
    <div class="page-body">

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon red"><i class="fas fa-book-open"></i></div>
          <div class="stat-info"><h3><?= $total_enrolled ?></h3><p>Enrolled Courses</p></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-certificate"></i></div>
          <div class="stat-info"><h3><?= $total_completed ?></h3><p>Certificates Earned</p></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="color:white;background:linear-gradient(135deg,#f97316,#ea580c);"><i class="fas fa-fire"></i></div>
          <div class="stat-info"><h3><?= $current_streak ?></h3><p>Day Streak</p></div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div class="section-title" style="margin-bottom:0">📚 My Enrolled Courses</div>
        <a href="courses.php" class="btn btn-outline btn-sm"><i class="fas fa-plus"></i> Browse More</a>
      </div>

      <?php if ($enrolled_courses->num_rows === 0): ?>
      <div style="text-align:center;padding:60px;color:var(--gray)">
        <i class="fas fa-graduation-cap" style="font-size:3rem;display:block;margin-bottom:16px;color:var(--gray-border)"></i>
        <h3>No courses yet</h3>
        <p>Enroll in a course to start learning!</p>
        <a href="courses.php" class="btn btn-primary" style="margin-top:16px"><i class="fas fa-rocket"></i> Explore Courses</a>
      </div>
      <?php else: ?>
      <?php $emojis = ['💻','📊','🎨','📈','🔬','📱','🌐','🎯']; $ei=0;
      while($c = $enrolled_courses->fetch_assoc()):
        $pct = $c['total_lessons'] > 0 ? round(($c['completed_lessons']/$c['total_lessons'])*100) : 0;
      ?>
      <div class="enrolled-card">
        <?php if (!empty($c['thumbnail'])): ?>
          <img src="../assets/images/<?= htmlspecialchars($c['thumbnail']) ?>" alt="Course Thumbnail" style="width:100px; height:70px; object-fit:cover; border-radius:var(--radius-sm); border:1px solid var(--gray-border); flex-shrink:0;">
        <?php else: ?>
          <div class="course-emoji"><?= $emojis[$ei++ % count($emojis)] ?></div>
        <?php endif; ?>
        <div class="enrolled-info">
          <h4><?= htmlspecialchars($c['title']) ?></h4>
          <div class="meta"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['instructor_name']) ?> · Enrolled <?= date('M d, Y',strtotime($c['enrolled_at'])) ?></div>
          <div style="margin-top:8px">
            <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--gray)">
              <span>Progress</span>
              <span><?= $c['completed_lessons'] ?>/<?= $c['total_lessons'] ?> lessons · <?= $pct ?>%</span>
            </div>
            <div class="progress-bar-wrap">
              <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
          <?php if($pct >= 100): ?>
            <span class="badge badge-approved"><i class="fas fa-check"></i> Completed</span>
            <a href="certificate.php?course_id=<?= $c['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-certificate"></i> Certificate</a>
          <?php endif; ?>
          <a href="course-learn.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-play"></i> Continue</a>
        </div>
      </div>
      <?php endwhile; ?>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
