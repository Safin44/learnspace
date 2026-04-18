<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

$success = '';
$error = '';

// Handle contest submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contest'])) {
    $contest_id = intval($_POST['contest_id']);

    // Check if already participated
    $already = $conn->query("SELECT id FROM contest_participants WHERE contest_id=$contest_id AND user_id=$user_id")->num_rows;
    if ($already > 0) {
        $error = "You have already participated in this contest!";
    } else {
        // Check contest is active
        $contest_check = $conn->query("SELECT status FROM contests WHERE id=$contest_id")->fetch_assoc();
        if ($contest_check['status'] !== 'active') {
            $error = "This contest is no longer active.";
        } else {
            // Grade answers
            $questions_q = $conn->query("SELECT * FROM contest_questions WHERE contest_id=$contest_id");
            $total = 0;
            $score = 0;
            while ($q = $questions_q->fetch_assoc()) {
                $total++;
                $given = $_POST['answer_'.$q['id']] ?? '';
                if ($given === $q['correct_answer']) $score++;
            }
            $pct = $total > 0 ? round(($score/$total)*100, 2) : 0;
            $stmt = $conn->prepare("INSERT INTO contest_participants (contest_id, user_id, score, total, percentage) VALUES (?,?,?,?,?)");
            $stmt->bind_param("iiiid", $contest_id, $user_id, $score, $total, $pct);
            $stmt->execute();
            header("Location: contest-result.php?contest_id=$contest_id");
            exit;
        }
    }
}

// Active contest to participate
$take_id = intval($_GET['take'] ?? 0);
$take_contest = null;
$take_questions = [];
$already_participated = false;
if ($take_id) {
    $take_contest = $conn->query("SELECT * FROM contests WHERE id=$take_id AND status='active'")->fetch_assoc();
    if ($take_contest) {
        $already_participated = $conn->query("SELECT id FROM contest_participants WHERE contest_id=$take_id AND user_id=$user_id")->num_rows > 0;
        $qs = $conn->query("SELECT * FROM contest_questions WHERE contest_id=$take_id ORDER BY question_order");
        while ($q = $qs->fetch_assoc()) $take_questions[] = $q;
    }
}

// All contests
$contests_q = $conn->query("SELECT c.*, 
    COUNT(DISTINCT cp.id) as participant_count,
    (SELECT id FROM contest_participants WHERE contest_id=c.id AND user_id=$user_id LIMIT 1) as my_participation
    FROM contests c 
    LEFT JOIN contest_participants cp ON c.id=cp.contest_id
    GROUP BY c.id 
    ORDER BY c.created_at DESC");
$contests = [];
while ($r = $contests_q->fetch_assoc()) $contests[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Contests - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.contest-card { background:white;border-radius:var(--radius);border:1px solid var(--gray-border);padding:20px;margin-bottom:16px;transition:var(--transition); }
.contest-card:hover { box-shadow:var(--shadow); }
.status-active { background:#dcfce7;color:#16a34a;padding:4px 12px;border-radius:50px;font-size:0.78rem;font-weight:700; }
.status-upcoming { background:#fef9c3;color:#ca8a04;padding:4px 12px;border-radius:50px;font-size:0.78rem;font-weight:700; }
.status-ended { background:#f3f4f6;color:#6b7280;padding:4px 12px;border-radius:50px;font-size:0.78rem;font-weight:700; }
.quiz-option { display:flex;align-items:center;gap:10px;padding:12px 16px;border:2px solid var(--gray-border);border-radius:var(--radius-sm);cursor:pointer;transition:var(--transition);margin-bottom:8px; }
.quiz-option:hover { border-color:var(--primary);background:var(--primary-bg); }
.quiz-option input[type=radio] { accent-color:var(--primary); }
.question-card { background:white;border-radius:var(--radius);padding:20px;margin-bottom:16px;border:1px solid var(--gray-border); }
.take-contest-header { background:linear-gradient(135deg,var(--primary),#ff6b6b);color:white;border-radius:var(--radius);padding:24px;margin-bottom:24px; }
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
      <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-trophy" style="color:var(--primary)"></i> Contests</h1>
    </div>
    <div class="page-body">
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <?php if ($take_contest && !$already_participated): ?>
      <!-- TAKE CONTEST -->
      <div class="take-contest-header">
        <h2 style="margin-bottom:6px">🏆 <?= htmlspecialchars($take_contest['title']) ?></h2>
        <?php if ($take_contest['description']): ?>
        <p style="opacity:0.9"><?= htmlspecialchars($take_contest['description']) ?></p>
        <?php endif; ?>
        <p style="margin-top:10px;opacity:0.8"><i class="fas fa-info-circle"></i> Answer all questions. You can only participate once. Good luck!</p>
      </div>
      <form method="POST">
        <input type="hidden" name="contest_id" value="<?= $take_id ?>">
        <?php foreach ($take_questions as $qi => $q): ?>
        <div class="question-card">
          <div style="font-weight:700;margin-bottom:14px;font-size:1rem">
            <span style="background:var(--primary);color:white;padding:2px 8px;border-radius:50px;font-size:0.78rem;margin-right:8px">Q<?= $qi+1 ?></span>
            <?= htmlspecialchars($q['question']) ?>
          </div>
          <?php foreach (['a','b','c','d'] as $opt):
            if (!$q['option_'.$opt]) continue; ?>
          <label class="quiz-option">
            <input type="radio" name="answer_<?= $q['id'] ?>" value="<?= $opt ?>" required>
            <strong><?= strtoupper($opt) ?>.</strong> <?= htmlspecialchars($q['option_'.$opt]) ?>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($take_questions)): ?>
        <div style="text-align:center;padding:40px;color:var(--gray)">No questions available yet.</div>
        <?php else: ?>
        <div style="display:flex;gap:12px;margin-top:8px">
          <button type="submit" name="submit_contest" class="btn btn-primary btn-lg" onclick="return confirm('Submit your answers? You cannot change them after submission!')">
            <i class="fas fa-paper-plane"></i> Submit Answers
          </button>
          <a href="contests.php" class="btn btn-outline btn-lg">Cancel</a>
        </div>
        <?php endif; ?>
      </form>

      <?php elseif ($take_contest && $already_participated): ?>
      <div class="alert alert-success">You have already participated in this contest. <a href="contest-result.php?contest_id=<?= $take_id ?>">View your result →</a></div>

      <?php else: ?>
      <!-- CONTESTS LIST -->
      <?php if (empty($contests)): ?>
      <div style="text-align:center;padding:60px;color:var(--gray)">
        <i class="fas fa-trophy" style="font-size:3rem;display:block;margin-bottom:16px;opacity:0.2"></i>
        <h3>No contests available yet</h3>
        <p>Check back later for upcoming contests!</p>
      </div>
      <?php else: ?>
      <?php foreach ($contests as $c): ?>
      <div class="contest-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
          <div>
            <h3 style="margin-bottom:6px"><?= htmlspecialchars($c['title']) ?></h3>
            <?php if ($c['description']): ?>
            <p style="color:var(--gray);font-size:0.88rem;margin-bottom:8px"><?= htmlspecialchars($c['description']) ?></p>
            <?php endif; ?>
            <div style="display:flex;gap:16px;font-size:0.83rem;color:var(--gray)">
              <span><i class="fas fa-users"></i> <?= $c['participant_count'] ?> participants</span>
              <?php if ($c['started_at']): ?><span><i class="fas fa-play"></i> Started <?= date('M d, H:i', strtotime($c['started_at'])) ?></span><?php endif; ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
            <span class="status-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span>
            <div style="display:flex;gap:8px">
              <?php if ($c['status'] === 'active' && !$c['my_participation']): ?>
              <a href="contests.php?take=<?= $c['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-play"></i> Participate
              </a>
              <?php elseif ($c['my_participation']): ?>
              <?php if ($c['status'] === 'ended'): ?>
              <a href="contest-result.php?contest_id=<?= $c['id'] ?>" class="btn btn-success btn-sm">
                <i class="fas fa-chart-bar"></i> View Result
              </a>
              <?php else: ?>
              <span class="badge badge-approved"><i class="fas fa-check"></i> Submitted</span>
              <?php endif; ?>
              <?php endif; ?>
              <?php if ($c['status'] === 'ended'): ?>
              <a href="contest-result.php?contest_id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-trophy"></i> Leaderboard
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
