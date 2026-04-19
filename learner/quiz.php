<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];

$quiz_id   = intval($_GET['id'] ?? 0);
$course_id = intval($_GET['course_id'] ?? 0);

// Fetch quiz
$quiz = $conn->query("SELECT qz.*, c.title as course_title, c.id as cid 
    FROM quizzes qz JOIN courses c ON qz.course_id=c.id 
    WHERE qz.id=$quiz_id")->fetch_assoc();
if (!$quiz) { header('Location: dashboard.php'); exit; }

$course_id = $quiz['cid'];

// Check enrollment
$enrolled = $conn->query("SELECT id FROM enrollments WHERE user_id=$user_id AND course_id=$course_id")->num_rows > 0;
if (!$enrolled) { header('Location: courses.php'); exit; }

// Check previous attempt
$prev_attempt = $conn->query("SELECT * FROM quiz_attempts WHERE user_id=$user_id AND quiz_id=$quiz_id ORDER BY attempted_at DESC LIMIT 1")->fetch_assoc();
if (isset($_GET['retake']) && $_GET['retake'] == '1') {
    $prev_attempt = null;
}

// Fetch questions
$questions_q = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id=$quiz_id ORDER BY id");
$questions = [];
while ($q = $questions_q->fetch_assoc()) $questions[] = $q;

$result = null;
$submitted_answers = [];

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $total = count($questions);
    $score = 0;
    $submitted_answers = $_POST['answers'] ?? [];

    foreach ($questions as $q) {
        $given = $submitted_answers[$q['id']] ?? '';
        if ($given === $q['correct_answer']) $score++;
    }

    $pct    = $total > 0 ? round(($score / $total) * 100, 2) : 0;
    $passed = $pct >= $quiz['pass_percentage'] ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, score, total, percentage, passed) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iiiidi", $user_id, $quiz_id, $score, $total, $pct, $passed);
    $stmt->execute();

    if ($passed) {
        $conn->query("INSERT IGNORE INTO certificates (user_id, course_id) VALUES ($user_id, $course_id)");
    }

    $result = [
        'score'      => $score,
        'total'      => $total,
        'percentage' => $pct,
        'passed'     => $passed,
        'pass_pct'   => $quiz['pass_percentage'],
    ];
    $prev_attempt = null; // Show fresh result
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($quiz['title']) ?> - Quiz</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background:#f0f2f5; }
.quiz-wrap { max-width:760px; margin:0 auto; padding:32px 16px; }
.quiz-header { background:linear-gradient(135deg,var(--primary),#c00); color:white; border-radius:var(--radius); padding:28px; margin-bottom:28px; }
.quiz-header h1 { font-size:1.5rem; margin-bottom:6px; }
.quiz-header p { opacity:.85; font-size:.9rem; }
.q-card { background:white; border-radius:var(--radius); padding:22px; margin-bottom:18px; border:1px solid var(--gray-border); }
.q-num { font-size:.75rem; font-weight:800; color:var(--primary); text-transform:uppercase; margin-bottom:8px; }
.q-text { font-size:1rem; font-weight:700; margin-bottom:16px; }
.opt-label { display:flex; align-items:center; gap:12px; padding:12px 16px; border:2px solid var(--gray-border); border-radius:var(--radius-sm); cursor:pointer; transition:var(--transition); margin-bottom:8px; }
.opt-label:hover { border-color:var(--primary); background:var(--primary-bg); }
.opt-label input { accent-color:var(--primary); }
.opt-label.correct { border-color:#16a34a; background:#f0fdf4; }
.opt-label.wrong   { border-color:#dc2626; background:#fef2f2; }
.opt-label.selected-wrong { border-color:#dc2626; background:#fef2f2; }

/* Result card */
.result-card { border-radius:var(--radius); padding:32px; text-align:center; margin-bottom:28px; }
.result-card.pass { background:linear-gradient(135deg,#065f46,#10b981); color:white; }
.result-card.fail { background:linear-gradient(135deg,#7f1d1d,var(--primary)); color:white; }
.big-score { font-size:4rem; font-weight:900; margin:12px 0; }
.hidden-answers-notice { background:#fff7ed; border:1px solid #fed7aa; border-radius:var(--radius); padding:20px; text-align:center; margin-bottom:24px; }
.hidden-answers-notice i { color:#ea580c; font-size:2rem; display:block; margin-bottom:10px; }
</style>
</head>
<body>
<div class="quiz-wrap">
  <a href="course-learn.php?id=<?= $course_id ?>" style="display:inline-flex;align-items:center;gap:8px;color:var(--gray);font-size:.88rem;margin-bottom:20px;font-weight:600">
    <i class="fas fa-arrow-left"></i> Back to Course
  </a>

  <div class="quiz-header">
    <div style="font-size:.78rem;opacity:.8;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em"><?= htmlspecialchars($quiz['course_title']) ?></div>
    <h1><i class="fas fa-question-circle"></i> <?= htmlspecialchars($quiz['title']) ?></h1>
    <p><?= count($questions) ?> questions &nbsp;·&nbsp; Pass mark: <?= $quiz['pass_percentage'] ?>%</p>
  </div>

  <?php if ($result): ?>
  <!-- ========== RESULT VIEW ========== -->
  <div class="result-card <?= $result['passed'] ? 'pass' : 'fail' ?>">
    <div style="font-size:2.5rem"><?= $result['passed'] ? '🏆' : '📚' ?></div>
    <div class="big-score"><?= $result['score'] ?> / <?= $result['total'] ?></div>
    <div style="font-size:1.3rem;opacity:.9;margin-bottom:8px"><?= number_format($result['percentage'], 1) ?>%</div>
    <div style="opacity:.85"><?= $result['passed'] ? '🎉 Congratulations! You passed!' : '❌ You did not reach the pass mark of '.$result['pass_pct'].'%.' ?></div>
  </div>

  <?php if (!$result['passed']): ?>
  <!-- FAIL: Hide answers, show notice -->
  <div class="hidden-answers-notice">
    <i class="fas fa-lock"></i>
    <strong>Correct answers are hidden</strong>
    <p style="color:var(--gray);margin-top:8px">You need to score at least <strong><?= $result['pass_pct'] ?>%</strong> to see the correct answers. Review the course material and try again!</p>
  </div>

  <!-- Show what the student selected but NO correct answers revealed -->
  <?php foreach ($questions as $qi => $q): ?>
  <div class="q-card">
    <div class="q-num">Question <?= $qi+1 ?></div>
    <div class="q-text"><?= htmlspecialchars($q['question']) ?></div>
    <?php foreach (['a','b','c','d'] as $opt):
      $optval = $q['option_'.$opt];
      if (!$optval) continue;
      $selected = ($submitted_answers[$q['id']] ?? '') === $opt;
    ?>
    <div class="opt-label <?= $selected ? 'selected-wrong' : '' ?>" style="cursor:default">
      <strong style="color:<?= $selected ? '#dc2626' : 'var(--gray)' ?>"><?= strtoupper($opt) ?>.</strong>
      <?= htmlspecialchars($optval) ?>
      <?php if ($selected): ?> <i class="fas fa-times" style="color:#dc2626;margin-left:auto"></i><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <?php else: ?>
  <!-- PASS: Show all answers with correct/wrong highlighted -->
  <?php foreach ($questions as $qi => $q): ?>
  <div class="q-card">
    <div class="q-num">Question <?= $qi+1 ?></div>
    <div class="q-text"><?= htmlspecialchars($q['question']) ?></div>
    <?php foreach (['a','b','c','d'] as $opt):
      $optval = $q['option_'.$opt];
      if (!$optval) continue;
      $is_correct  = $q['correct_answer'] === $opt;
      $was_selected = ($submitted_answers[$q['id']] ?? '') === $opt;
      $cls = '';
      if ($is_correct) $cls = 'correct';
      elseif ($was_selected && !$is_correct) $cls = 'wrong';
    ?>
    <div class="opt-label <?= $cls ?>" style="cursor:default">
      <strong><?= strtoupper($opt) ?>.</strong>
      <?= htmlspecialchars($optval) ?>
      <?php if ($is_correct): ?> <i class="fas fa-check" style="color:#16a34a;margin-left:auto"></i><?php endif; ?>
      <?php if ($was_selected && !$is_correct): ?> <i class="fas fa-times" style="color:#dc2626;margin-left:auto"></i><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div style="display:flex;gap:12px;margin-top:12px">
    <a href="quiz.php?id=<?= $quiz_id ?>&course_id=<?= $course_id ?>" class="btn btn-primary">
      <i class="fas fa-redo"></i> Try Again
    </a>
    <a href="course-learn.php?id=<?= $course_id ?>" class="btn btn-outline">
      <i class="fas fa-arrow-left"></i> Back to Course
    </a>
  </div>

  <?php elseif ($prev_attempt): ?>
  <!-- ========== PREVIOUS ATTEMPT SUMMARY ========== -->
  <div class="result-card <?= $prev_attempt['passed'] ? 'pass' : 'fail' ?>">
    <div style="font-size:2rem"><?= $prev_attempt['passed'] ? '🏆' : '📚' ?></div>
    <div style="font-size:1rem;opacity:.85;margin-bottom:4px">Previous Attempt</div>
    <div class="big-score"><?= $prev_attempt['score'] ?>/<?= $prev_attempt['total'] ?></div>
    <div style="font-size:1.2rem;opacity:.9"><?= number_format($prev_attempt['percentage'],1) ?>%</div>
    <div style="margin-top:8px;opacity:.85">
      <?= $prev_attempt['passed'] ? '✅ You passed this quiz!' : '❌ Below pass mark of '.$quiz['pass_percentage'].'%' ?>
    </div>
  </div>
  <div style="text-align:center;margin-bottom:28px">
    <p style="color:var(--gray);margin-bottom:16px">Want to improve your score?</p>
    <a href="quiz.php?id=<?= $quiz_id ?>&course_id=<?= $course_id ?>&retake=1" class="btn btn-primary btn-lg">
      <i class="fas fa-redo"></i> Retake Quiz
    </a>
  </div>

  <?php else: ?>
  <!-- ========== TAKE QUIZ ========== -->
  <?php if (empty($questions)): ?>
  <div style="text-align:center;padding:60px;color:var(--gray)">
    <i class="fas fa-question-circle" style="font-size:3rem;display:block;margin-bottom:16px;opacity:.2"></i>
    <h3>No questions yet</h3>
    <p>This quiz has no questions added yet.</p>
  </div>
  <?php else: ?>
  <form method="POST">
    <?php foreach ($questions as $qi => $q): ?>
    <div class="q-card">
      <div class="q-num">Question <?= $qi+1 ?> of <?= count($questions) ?></div>
      <div class="q-text"><?= htmlspecialchars($q['question']) ?></div>
      <?php foreach (['a','b','c','d'] as $opt):
        $optval = $q['option_'.$opt];
        if (!$optval) continue;
      ?>
      <label class="opt-label">
        <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt ?>" required>
        <strong><?= strtoupper($opt) ?>.</strong>
        <?= htmlspecialchars($optval) ?>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" name="submit_quiz" class="btn btn-primary btn-lg"
        onclick="return confirm('Submit your answers? This will be recorded.')">
        <i class="fas fa-paper-plane"></i> Submit Quiz
      </button>
      <a href="course-learn.php?id=<?= $course_id ?>" class="btn btn-outline btn-lg">Cancel</a>
    </div>
  </form>
  <?php endif; ?>
  <?php endif; ?>

</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
