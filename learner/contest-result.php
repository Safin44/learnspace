<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;

$contest_id = intval($_GET['contest_id'] ?? 0);
$contest = $conn->query("SELECT * FROM contests WHERE id=$contest_id")->fetch_assoc();
if (!$contest) { header('Location: contests.php'); exit; }

// My result
$my_result = $conn->query("SELECT * FROM contest_participants WHERE contest_id=$contest_id AND user_id=$user_id")->fetch_assoc();

// Prizes set by admin
$prizes_q = $conn->query("SELECT * FROM contest_prizes WHERE contest_id=$contest_id ORDER BY position ASC");
$prizes = [];
while ($p = $prizes_q->fetch_assoc()) $prizes[$p['position']] = $p['prize_label'];

// Full leaderboard with name + email
$leaders_q = $conn->query("SELECT cp.*, u.full_name, u.email
    FROM contest_participants cp
    JOIN users u ON cp.user_id = u.id
    WHERE cp.contest_id=$contest_id
    ORDER BY cp.score DESC, cp.submitted_at ASC");
$leaders = [];
$my_rank = null;
$pos = 0;
while ($r = $leaders_q->fetch_assoc()) {
    $pos++;
    $r['rank'] = $pos;
    $r['prize'] = $prizes[$pos] ?? null;
    if ($r['user_id'] == $user_id) $my_rank = $pos;
    $leaders[] = $r;
}
$my_prize = ($my_rank && isset($prizes[$my_rank])) ? $prizes[$my_rank] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Contest Result — LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.result-hero{border-radius:var(--radius);padding:32px 28px;text-align:center;margin-bottom:24px;color:white;position:relative;overflow:hidden}
.result-hero.win{background:linear-gradient(135deg,#064e3b,#10b981)}
.result-hero.lose{background:linear-gradient(135deg,#7f1d1d,#cc0000)}
.result-hero.view{background:linear-gradient(135deg,#1e3a5f,#3b82f6)}
.big-score{font-size:3.8rem;font-weight:900;line-height:1;margin:10px 0}
.prize-won{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.18);border-radius:50px;padding:8px 18px;margin-top:14px;font-size:.95rem;font-weight:700}
.prizes-strip{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px}
.prize-card{flex:1;min-width:140px;border-radius:var(--radius-sm);padding:14px 16px;border:1px solid}
.prize-card.p1{background:#fffbeb;border-color:#fde68a}
.prize-card.p2{background:#f9fafb;border-color:#e5e7eb}
.prize-card.p3{background:#fff7ed;border-color:#fed7aa}
.lb-row{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:var(--radius-sm);margin-bottom:8px;background:white;border:1px solid var(--gray-border);transition:var(--transition)}
.lb-row:hover{box-shadow:var(--shadow)}
.lb-row.me{border-color:var(--primary);background:var(--primary-bg)}
.rank-cell{width:42px;text-align:center;font-size:1.3rem;flex-shrink:0}
.lb-info{flex:1;min-width:0}
.lb-bar-wrap{background:#f3f4f6;border-radius:50px;height:7px;flex:1;min-width:60px;overflow:hidden;margin-top:5px}
.lb-bar-fill{height:7px;border-radius:50px;background:linear-gradient(90deg,var(--primary),#ff6b6b)}
.prize-tag{font-size:.72rem;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:50px;border:1px solid #fde68a;white-space:nowrap}
.you-badge{font-size:.7rem;background:var(--primary);color:white;padding:2px 8px;border-radius:50px}
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace"></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="courses.php"><i class="fas fa-book"></i> Browse Courses</a></li>
      <li><a href="contests.php" class="active"><i class="fas fa-trophy"></i> Contests</a></li>
      <li><a href="contest-history.php"><i class="fas fa-history"></i> Contest History</a></li>
      <li><a href="coding-problems.php"><i class="fas fa-code"></i> Coding Problems</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span title="One or more issues resolved" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificates</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <div>
        <a href="contests.php" style="color:var(--gray);font-size:.88rem;display:flex;align-items:center;gap:6px;margin-bottom:8px">
          <i class="fas fa-arrow-left"></i> Back to Contests
        </a>
        <h1><i class="fas fa-trophy" style="color:var(--primary)"></i> <?= htmlspecialchars($contest['title']) ?></h1>
      </div>
    </div>
    <div class="page-body">

      <!-- MY RESULT HERO -->
      <?php if ($my_result): ?>
        <?php if ($contest['status'] === 'ended'): ?>
          <?php $won = $my_result['percentage'] >= 50; ?>
          <div class="result-hero <?= $won ? 'win' : 'lose' ?>">
            <div style="font-size:2.2rem"><?= $won ? '🏆' : '📚' ?></div>
            <div class="big-score"><?= $my_result['score'] ?> <span style="font-size:1.8rem;opacity:.7">/ <?= $my_result['total'] ?></span></div>
            <div style="font-size:1.1rem;font-weight:700;opacity:.9"><?= number_format($my_result['percentage'],1) ?>% correct</div>
            <?php if ($my_rank): ?>
            <div style="margin-top:8px;opacity:.85">
              Your Position: <strong><?= $my_rank <= 3 ? ['🥇','🥈','🥉'][$my_rank-1] : '#'.$my_rank ?></strong>
              of <?= count($leaders) ?> participants
            </div>
            <?php endif; ?>
            <?php if ($my_prize): ?>
            <div class="prize-won"><i class="fas fa-gift"></i> You won: <strong><?= htmlspecialchars($my_prize) ?></strong></div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="result-hero view">
            <div style="font-size:2.2rem">⏳</div>
            <div class="big-score" style="font-size:2.5rem;">Submitted!</div>
            <div style="margin-top:14px;opacity:.9;font-size:1.1rem;font-weight:bold;">Result will be published soon.</div>
            <div style="margin-top:8px;opacity:.85;font-size:.92rem"><i class="fas fa-clock"></i> Leaderboard will be revealed after the contest ends</div>
          </div>
        <?php endif; ?>
      <?php else: ?>
      <div class="result-hero view">
        <div style="font-size:2rem">📊</div>
        <div style="font-size:1.4rem;font-weight:800;margin-top:8px">Contest Leaderboard</div>
        <?php if ($contest['status'] === 'ended'): ?>
        <div style="opacity:.85;margin-top:6px">You did not participate in this contest</div>
        <?php else: ?>
        <div style="opacity:.85;margin-top:6px">Results will be available after the contest ends</div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($contest['status'] === 'ended'): ?>
      <!-- PRIZES STRIP -->
      <?php if (!empty($prizes)): ?>
      <div class="section-title" style="margin-bottom:14px"><i class="fas fa-gift" style="color:#f59e0b"></i> Prizes</div>
      <div class="prizes-strip">
        <?php
        $prize_meta = [1=>['icon'=>'🥇','cls'=>'p1','label'=>'1st Place'],2=>['icon'=>'🥈','cls'=>'p2','label'=>'2nd Place'],3=>['icon'=>'🥉','cls'=>'p3','label'=>'3rd Place']];
        foreach ($prizes as $ppos => $plabel):
          $m = $prize_meta[$ppos] ?? ['icon'=>'🏅','cls'=>'p3','label'=>'#'.$ppos.' Place'];
          $winner = $leaders[$ppos-1] ?? null;
        ?>
        <div class="prize-card <?= $m['cls'] ?>">
          <div style="font-size:1.6rem;margin-bottom:6px"><?= $m['icon'] ?></div>
          <div style="font-size:.72rem;color:var(--gray);text-transform:uppercase;letter-spacing:.05em"><?= $m['label'] ?></div>
          <div style="font-weight:800;font-size:.9rem;margin-top:2px"><?= htmlspecialchars($plabel) ?></div>
          <?php if ($winner): ?>
          <div style="font-size:.78rem;color:var(--gray);margin-top:6px"><i class="fas fa-user"></i> <?= htmlspecialchars($winner['full_name']) ?></div>
          <?php else: ?>
          <div style="font-size:.75rem;color:var(--gray);margin-top:6px;font-style:italic">No winner yet</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- FULL LEADERBOARD -->
      <div class="section-title" style="margin-bottom:14px">
        <i class="fas fa-list-ol" style="color:var(--primary)"></i> Full Rankings
        <span style="font-weight:400;font-size:.85rem;color:var(--gray);margin-left:8px"><?= count($leaders) ?> participant<?= count($leaders)!=1?'s':'' ?></span>
      </div>

      <?php if (empty($leaders)): ?>
      <div style="text-align:center;padding:48px;color:var(--gray)">
        <i class="fas fa-users" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.2"></i>
        <p>No participants yet.</p>
      </div>
      <?php else: ?>
      <?php foreach ($leaders as $l): ?>
      <div class="lb-row <?= $l['user_id']==$user_id ? 'me' : '' ?>">
        <div class="rank-cell">
          <?php if($l['rank']===1) echo '🥇'; elseif($l['rank']===2) echo '🥈'; elseif($l['rank']===3) echo '🥉'; else echo '<strong style="color:var(--gray);font-size:.95rem">#'.$l['rank'].'</strong>'; ?>
        </div>
        <div class="lb-info">
          <div style="font-weight:700;font-size:.93rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <?= htmlspecialchars($l['full_name']) ?>
            <?php if($l['user_id']==$user_id): ?><span class="you-badge">You</span><?php endif; ?>
            <?php if($l['prize']): ?><span class="prize-tag"><i class="fas fa-gift"></i> <?= htmlspecialchars($l['prize']) ?></span><?php endif; ?>
          </div>
          <div style="font-size:.78rem;color:var(--gray);margin-top:2px"><i class="fas fa-envelope" style="opacity:.5"></i> <?= htmlspecialchars($l['email']) ?></div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:5px">
            <div class="lb-bar-wrap"><div class="lb-bar-fill" style="width:<?= $l['percentage'] ?>%"></div></div>
            <span style="font-size:.75rem;color:var(--gray);white-space:nowrap"><?= $l['score'] ?>/<?= $l['total'] ?></span>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0;min-width:64px">
          <div style="font-weight:800;font-size:1rem;color:<?= $l['percentage']>=70?'#16a34a':($l['percentage']>=40?'#ca8a04':'var(--primary)') ?>"><?= number_format($l['percentage'],1) ?>%</div>
          <div style="font-size:.72rem;color:var(--gray)"><?= date('M d, H:i', strtotime($l['submitted_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php else: ?>
      <!-- CONTEST NOT ENDED YET -->
      <div class="card" style="text-align:center;padding:48px;">
        <i class="fas fa-lock" style="font-size:3rem;color:var(--gray-border);display:block;margin-bottom:16px"></i>
        <h3 style="margin-bottom:8px;color:var(--dark)">Leaderboard Locked</h3>
        <p style="color:var(--gray);font-size:.92rem">The full leaderboard, rankings, and prizes will be revealed once the admin ends this contest.</p>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
