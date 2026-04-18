<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;

$contests_q = $conn->query("SELECT c.*,
    cp.score, cp.total, cp.percentage, cp.submitted_at,
    COUNT(DISTINCT cp2.id) as total_participants,
    (SELECT COUNT(*)+1 FROM contest_participants cp3 WHERE cp3.contest_id=c.id AND cp3.score > IFNULL(cp.score,0)) as my_rank
    FROM contests c
    LEFT JOIN contest_participants cp ON c.id=cp.contest_id AND cp.user_id=$user_id
    LEFT JOIN contest_participants cp2 ON c.id=cp2.contest_id
    WHERE c.status='ended' OR cp.id IS NOT NULL
    GROUP BY c.id
    ORDER BY c.created_at DESC");
$contests = [];
while ($r = $contests_q->fetch_assoc()) $contests[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Contest History - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.history-card { background:white;border-radius:var(--radius);border:1px solid var(--gray-border);padding:20px;margin-bottom:16px;display:flex;gap:16px;align-items:center;flex-wrap:wrap; }
.history-icon { width:56px;height:56px;background:var(--primary-bg);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0; }
.score-circle { width:60px;height:60px;border-radius:50%;border:3px solid var(--primary);display:flex;align-items:center;justify-content:center;flex-direction:column;flex-shrink:0; }
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace"></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="courses.php"><i class="fas fa-book"></i> Browse Courses</a></li>
      <li><a href="contests.php"><i class="fas fa-trophy"></i> Contests</a></li>
      <li><a href="contest-history.php" class="active"><i class="fas fa-history"></i> Contest History</a></li>
      <li><a href="coding-problems.php"><i class="fas fa-code"></i> Coding Problems</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span title="One or more issues resolved" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificates</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-history" style="color:var(--primary)"></i> Contest History</h1>
    </div>
    <div class="page-body">
      <?php if (empty($contests)): ?>
      <div style="text-align:center;padding:60px;color:var(--gray)">
        <i class="fas fa-history" style="font-size:3rem;display:block;margin-bottom:16px;opacity:0.2"></i>
        <h3>No contest history yet</h3>
        <p>Participate in contests to see your results here!</p>
        <a href="contests.php" class="btn btn-primary" style="margin-top:16px">View Active Contests</a>
      </div>
      <?php else: ?>
      <?php foreach ($contests as $c): ?>
      <div class="history-card">
        <div class="history-icon">🏆</div>
        <div style="flex:1;min-width:0">
          <h3 style="margin-bottom:4px"><?= htmlspecialchars($c['title']) ?></h3>
          <div style="font-size:0.83rem;color:var(--gray)">
            <?php if ($c['ended_at']): ?>Ended <?= date('M d, Y', strtotime($c['ended_at'])) ?><?php endif; ?>
            · <?= $c['total_participants'] ?> participants
          </div>
          <?php if ($c['submitted_at']): ?>
          <div style="font-size:0.82rem;color:var(--gray);margin-top:2px">Submitted: <?= date('M d, Y H:i', strtotime($c['submitted_at'])) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($c['score'] !== null): ?>
        <div style="text-align:center">
          <?php if ($c['status'] === 'ended'): ?>
          <div class="score-circle">
            <span style="font-weight:900;font-size:0.9rem;color:var(--primary)"><?= $c['score'] ?>/<?= $c['total'] ?></span>
            <span style="font-size:0.65rem;color:var(--gray)"><?= number_format($c['percentage'],0) ?>%</span>
          </div>
          <div style="font-size:0.75rem;color:var(--gray);margin-top:4px">Rank #<?= $c['my_rank'] ?></div>
          <?php else: ?>
          <div class="score-circle" style="border-color:var(--gray-border)">
            <i class="fas fa-lock" style="font-size:1rem;color:var(--gray)"></i>
          </div>
          <div style="font-size:0.75rem;color:var(--gray);margin-top:4px">Awaiting Results</div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="color:var(--gray);font-size:0.85rem">Not participated</div>
        <?php endif; ?>
        <?php if ($c['status'] === 'ended'): ?>
        <a href="contest-result.php?contest_id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">
          <i class="fas fa-chart-bar"></i> Leaderboard
        </a>
        <?php else: ?>
        <span style="font-size:0.8rem;color:var(--gray);font-style:italic"><i class="fas fa-lock"></i> Locked</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
