<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];

// Check if user has open resolved message
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;

// Fetch coding problems
$problems_q = $conn->query("SELECT p.*, 
    (SELECT status FROM problem_completions WHERE problem_id=p.id AND user_id=$user_id) as user_status,
    (SELECT COUNT(*) FROM problem_completions WHERE problem_id=p.id AND status='solved') as total_solved
    FROM coding_problems p ORDER BY p.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coding Problems - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.problem-item { background:white;border-radius:var(--radius);border:1px solid var(--gray-border);padding:20px;margin-bottom:14px;display:flex;align-items:center;transition:var(--transition);text-decoration:none;color:inherit; }
.problem-item:hover { box-shadow:var(--shadow);transform:translateY(-2px);border-color:var(--primary); }
.problem-icon { width:50px;height:50px;border-radius:50%;background:var(--gray-border);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;color:var(--gray); margin-right:20px; }
.problem-icon.solved { background:#dcfce7;color:#16a34a; }
.problem-info { flex:1; }
.problem-info h3 { font-size:1.1rem;margin-bottom:4px;display:flex;align-items:center;gap:10px; }
.desc { font-size:0.9rem;color:var(--gray);margin-bottom:8px; }
.meta { font-size:0.8rem;color:var(--gray);display:flex;gap:16px; }
.difficulty-easy { color:#16a34a;font-weight:700; }
.difficulty-medium { color:#ca8a04;font-weight:700; }
.difficulty-hard { color:#dc2626;font-weight:700; }
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace"></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="courses.php"><i class="fas fa-search"></i> Browse Courses</a></li>
      <li><a href="contests.php"><i class="fas fa-trophy"></i> Contests</a></li>
      <li><a href="contest-history.php"><i class="fas fa-history"></i> Contest History</a></li>
      <li><a href="coding-problems.php" class="active"><i class="fas fa-code"></i> Coding Problems</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="certificates.php"><i class="fas fa-certificate"></i> My Certificates</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header" style="justify-content: space-between;">
      <h1><i class="fas fa-code"></i> Practice Coding Problems</h1>
      <a href="leaderboard.php" class="btn btn-outline"><i class="fas fa-medal" style="color:#f59e0b"></i> View Leaderboard</a>
    </div>
    <div class="page-body">
      <?php if ($problems_q->num_rows === 0): ?>
      <div style="text-align:center;padding:60px;color:var(--gray)">
        <i class="fas fa-code" style="font-size:3rem;display:block;margin-bottom:16px;opacity:0.2"></i>
        <h3>No coding problems available yet</h3>
        <p>Check back later for new practice problems.</p>
      </div>
      <?php else: ?>
      
      <div class="problems-list">
        <?php while ($p = $problems_q->fetch_assoc()): ?>
        <a href="solve-problem.php?id=<?= $p['id'] ?>" class="problem-item">
          <?php if ($p['user_status'] === 'solved'): ?>
            <div class="problem-icon solved"><i class="fas fa-check"></i></div>
          <?php else: ?>
            <div class="problem-icon"><i class="fas fa-code"></i></div>
          <?php endif; ?>
          <div class="problem-info">
            <h3><?= htmlspecialchars($p['title']) ?></h3>
            <div class="desc"><?= mb_strimwidth(htmlspecialchars($p['description']), 0, 150, "...") ?></div>
            <div class="meta">
              <span class="difficulty-<?= $p['difficulty'] ?>"><?= ucfirst($p['difficulty']) ?></span>
              <span><i class="fas fa-users"></i> <?= $p['total_solved'] ?> Solved</span>
              <?php if ($p['user_status'] === 'solved'): ?>
              <span style="color:#16a34a"><i class="fas fa-check-circle"></i> Completed</span>
              <?php elseif ($p['user_status'] === 'attempted'): ?>
              <span style="color:#ca8a04"><i class="fas fa-times-circle"></i> Attempted</span>
              <?php endif; ?>
            </div>
          </div>
          <div style="padding-left:16px;">
            <button class="btn <?= $p['user_status'] === 'solved' ? 'btn-outline' : 'btn-primary' ?> btn-sm">
              <?= $p['user_status'] === 'solved' ? 'Solve Again' : 'Solve' ?>
            </button>
          </div>
        </a>
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
