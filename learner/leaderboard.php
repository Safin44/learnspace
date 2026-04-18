<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;

$leaderboard_query = "
    SELECT 
        u.id, 
        u.full_name, 
        u.profile_pic,
        COUNT(pc.id) as total_solved,
        SUM(CASE 
            WHEN cp.difficulty = 'easy' THEN 1 
            WHEN cp.difficulty = 'medium' THEN 3 
            WHEN cp.difficulty = 'hard' THEN 5 
            ELSE 0 
        END) as total_score
    FROM users u
    JOIN problem_completions pc ON u.id = pc.user_id AND pc.status = 'solved'
    JOIN coding_problems cp ON pc.problem_id = cp.id
    GROUP BY u.id
    ORDER BY total_solved DESC, total_score DESC, u.full_name ASC
";
$leaders = $conn->query($leaderboard_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leaderboard - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.leaderboard-table { width:100%; border-collapse:collapse; background:white; border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow); }
.leaderboard-table th, .leaderboard-table td { padding:16px; text-align:left; border-bottom:1px solid var(--gray-border); }
.leaderboard-table th { background:var(--primary-bg); color:var(--primary); font-weight:700; font-size:0.9rem; text-transform:uppercase; }
.leaderboard-table tr:last-child td { border-bottom:none; }
.rank-1 { color:#eab308; font-weight:900; font-size:1.5rem; text-shadow:0 0 5px rgba(234,179,8,0.4); }
.rank-2 { color:#94a3b8; font-weight:800; font-size:1.3rem; }
.rank-3 { color:#b45309; font-weight:800; font-size:1.2rem; }
.user-info { display:flex; align-items:center; gap:12px; }
.user-avatar { width:40px; height:40px; border-radius:50%; background:var(--primary-bg); color:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:bold; object-fit:cover; }
.highlight-row { background:#f0fdf4 !important; border-left: 4px solid #10b981; }
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
      <h1><i class="fas fa-medal" style="color:#f59e0b"></i> Global Leaderboard</h1>
      <a href="coding-problems.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Problems</a>
    </div>
    <div class="page-body">
      
      <?php if ($leaders->num_rows === 0): ?>
      <div style="text-align:center;padding:60px;color:var(--gray)">
        <i class="fas fa-medal" style="font-size:3rem;display:block;margin-bottom:16px;opacity:0.2"></i>
        <h3>No rankings available yet</h3>
        <p>Start solving coding problems to secure the first place on the leaderboard!</p>
        <a href="coding-problems.php" class="btn btn-primary" style="margin-top:20px;">Solve Problems</a>
      </div>
      <?php else: ?>
      <table class="leaderboard-table">
          <thead>
              <tr>
                  <th style="width: 80px;">Rank</th>
                  <th>Learner</th>
                  <th>Problems Solved</th>
                  <th>Total Score</th>
              </tr>
          </thead>
          <tbody>
              <?php 
              $rank = 1;
              while ($row = $leaders->fetch_assoc()): 
              ?>
              <tr <?= ($row['id'] == $user_id) ? 'class="highlight-row"' : '' ?>>
                  <td>
                      <?php if($rank == 1): ?>
                          <span class="rank-1"><i class="fas fa-trophy"></i> 1</span>
                      <?php elseif($rank == 2): ?>
                          <span class="rank-2">2</span>
                      <?php elseif($rank == 3): ?>
                          <span class="rank-3">3</span>
                      <?php else: ?>
                          <span style="font-weight:bold;color:var(--gray)"><?= $rank ?></span>
                      <?php endif; ?>
                  </td>
                  <td>
                      <div class="user-info">
                          <?php if(!empty($row['profile_pic'])): ?>
                              <img src="../assets/images/<?= htmlspecialchars($row['profile_pic']) ?>" class="user-avatar" alt="Avatar">
                          <?php else: ?>
                              <div class="user-avatar"><?= strtoupper(substr($row['full_name'],0,1)) ?></div>
                          <?php endif; ?>
                          <strong><?= htmlspecialchars($row['full_name']) ?> <?= ($row['id'] == $user_id) ? '(You)' : '' ?></strong>
                      </div>
                  </td>
                  <td style="font-weight:700;"><i class="fas fa-check-circle" style="color:#10b981"></i> <?= $row['total_solved'] ?></td>
                  <td style="font-weight:700; color:var(--primary)"><?= $row['total_score'] ?> pts</td>
              </tr>
              <?php 
              $rank++;
              endwhile; 
              ?>
          </tbody>
      </table>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
