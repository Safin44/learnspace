<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$success = '';
$error = '';

// Create quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $course_id = intval($_POST['course_id']);
    $title     = trim($_POST['title']);
    $pass_pct  = intval($_POST['pass_percentage'] ?? 70);
    if (!$title || !$course_id) { $error = "Title and course are required."; }
    else {
        $stmt = $conn->prepare("INSERT INTO quizzes (course_id, title, pass_percentage) VALUES (?,?,?)");
        $stmt->bind_param("isi", $course_id, $title, $pass_pct);
        $stmt->execute();
        $quiz_id = $conn->insert_id;
        foreach (($_POST['questions'] ?? []) as $q) {
            if (empty($q['question'])) continue;
            $stmt2 = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer) VALUES (?,?,?,?,?,?,?)");
            $stmt2->bind_param("issssss", $quiz_id, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'] ?? '', $q['option_d'] ?? '', $q['correct_answer']);
            $stmt2->execute();
        }
        $success = "Quiz created successfully!";
    }
}

// Delete quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz'])) {
    $qid = intval($_POST['quiz_id']);
    $conn->query("DELETE FROM quizzes WHERE id=$qid");
    $success = "Quiz deleted.";
}

// Fetch courses
$courses_q = $conn->query("SELECT id, title FROM courses WHERE status='approved' ORDER BY title");
$courses = [];
while ($r = $courses_q->fetch_assoc()) $courses[] = $r;

// Fetch quizzes
$quizzes_q = $conn->query("SELECT qz.*, c.title as course_title, COUNT(DISTINCT qq.id) as question_count, COUNT(DISTINCT qa.id) as attempt_count
    FROM quizzes qz
    JOIN courses c ON qz.course_id=c.id
    LEFT JOIN quiz_questions qq ON qz.id=qq.quiz_id
    LEFT JOIN quiz_attempts qa ON qz.id=qa.quiz_id
    GROUP BY qz.id ORDER BY qz.created_at DESC");
$quizzes = [];
while ($r = $quizzes_q->fetch_assoc()) $quizzes[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Quizzes - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:white;border-radius:var(--radius);padding:32px;max-width:720px;width:90%;max-height:90vh;overflow-y:auto}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-weight:700;font-size:0.85rem;margin-bottom:5px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:9px 13px;border:1px solid var(--gray-border);border-radius:var(--radius-sm);font-size:0.88rem}
.question-block{background:#f9fafb;border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;border:1px solid var(--gray-border);position:relative}
.remove-q-btn{position:absolute;top:10px;right:10px;background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem}
.opts-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace"></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a></li>
      <li><a href="manage-instructors.php"><i class="fas fa-chalkboard-teacher"></i> Manage Instructors</a></li>
      <li><a href="courses.php"><i class="fas fa-book"></i> Courses</a></li>
      <li><a href="pending-courses.php"><i class="fas fa-clock"></i> Pending Courses</a></li>
      <li><a href="remove-courses.php"><i class="fas fa-trash-alt"></i> Remove Courses</a></li>
      <li><a href="manage-quizzes.php" class="active"><i class="fas fa-question-circle"></i> Manage Quizzes</a></li>
      <li><a href="contest.php"><i class="fas fa-trophy"></i> Arrange Contest</a></li>
      <li><a href="contest-result.php"><i class="fas fa-medal"></i> Contest Results</a></li>
      <li><a href="manage-coding-problems.php"><i class="fas fa-code"></i> Manage Coding Problems</a></li>
      <li><a href="view-messages.php"><i class="fas fa-envelope"></i> View Messages</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-question-circle" style="color:var(--primary)"></i> Manage Quizzes</h1>
      <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')">
        <i class="fas fa-plus"></i> Create Quiz
      </button>
    </div>
    <div class="page-body">
      <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=$success?></div><?php endif; ?>
      <?php if($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=$error?></div><?php endif; ?>

      <div class="card">
        <div class="table-wrapper">
          <table>
            <thead><tr><th>#</th><th>Quiz Title</th><th>Course</th><th>Pass %</th><th>Questions</th><th>Attempts</th><th>Action</th></tr></thead>
            <tbody>
              <?php if(empty($quizzes)): ?>
              <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray)">No quizzes yet. Create one!</td></tr>
              <?php else: ?>
              <?php foreach($quizzes as $i=>$qz): ?>
              <tr>
                <td><?=$i+1?></td>
                <td><strong><?=htmlspecialchars($qz['title'])?></strong></td>
                <td><?=htmlspecialchars($qz['course_title'])?></td>
                <td><span class="badge badge-approved"><?=$qz['pass_percentage']?>%</span></td>
                <td><?=$qz['question_count']?> questions</td>
                <td><?=$qz['attempt_count']?> attempts</td>
                <td>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Delete this quiz and all its data?')">
                    <input type="hidden" name="quiz_id" value="<?=$qz['id']?>">
                    <button type="submit" name="delete_quiz" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- CREATE QUIZ MODAL -->
<div class="modal-overlay" id="createModal">
  <div class="modal-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2><i class="fas fa-question-circle" style="color:var(--primary)"></i> Create Quiz</h2>
      <button onclick="document.getElementById('createModal').classList.remove('open')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--gray)">&times;</button>
    </div>
    <form method="POST">
      <div class="form-group">
        <label>Select Course *</label>
        <select name="course_id" required>
          <option value="">-- Select a Course --</option>
          <?php foreach($courses as $c): ?>
          <option value="<?=$c['id']?>"><?=htmlspecialchars($c['title'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Quiz Title *</label>
        <input type="text" name="title" placeholder="e.g. Unit 1 Quiz" required>
      </div>
      <div class="form-group">
        <label>Pass Percentage (%) — Answers shown only if score ≥ this</label>
        <input type="number" name="pass_percentage" value="70" min="1" max="100" required>
        <small style="color:var(--gray)">Students scoring below this will NOT see correct answers.</small>
      </div>
      <hr style="margin:16px 0">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h3 style="margin:0">Questions</h3>
        <button type="button" class="btn btn-outline btn-sm" onclick="addQ()"><i class="fas fa-plus"></i> Add Question</button>
      </div>
      <div id="qContainer"></div>
      <div style="display:flex;gap:12px;margin-top:16px">
        <button type="submit" name="create_quiz" class="btn btn-primary"><i class="fas fa-save"></i> Create Quiz</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
let n=0;
function addQ(){
  n++;
  const d=document.createElement('div');
  d.className='question-block'; d.id='q'+n;
  d.innerHTML=`<button type="button" class="remove-q-btn" onclick="document.getElementById('q${n}').remove()"><i class="fas fa-times-circle"></i></button>
    <div class="form-group"><label>Question ${n}</label><input type="text" name="questions[${n}][question]" placeholder="Enter question..." required></div>
    <div class="opts-grid">
      <div class="form-group" style="margin:0"><label>Option A *</label><input type="text" name="questions[${n}][option_a]" placeholder="Option A" required></div>
      <div class="form-group" style="margin:0"><label>Option B *</label><input type="text" name="questions[${n}][option_b]" placeholder="Option B" required></div>
      <div class="form-group" style="margin:0"><label>Option C</label><input type="text" name="questions[${n}][option_c]" placeholder="Option C"></div>
      <div class="form-group" style="margin:0"><label>Option D</label><input type="text" name="questions[${n}][option_d]" placeholder="Option D"></div>
    </div>
    <div class="form-group"><label>Correct Answer *</label><select name="questions[${n}][correct_answer]" required><option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option></select></div>`;
  document.getElementById('qContainer').appendChild(d);
}
addQ();
</script>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
