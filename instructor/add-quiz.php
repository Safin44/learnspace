<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['instructor_id'])) { header('Location: login.php'); exit; }
$ins_id = $_SESSION['instructor_id'];

$course_id = intval($_GET['course_id'] ?? 0);

// Verify course ownership
$course_check = $conn->query("SELECT id, title FROM courses WHERE id=$course_id AND instructor_id=$ins_id")->fetch_assoc();
if (!$course_check) { header('Location: my-courses.php'); exit; }

$success = '';
$error = '';
if (isset($_GET['new']) && $_GET['new'] === '1') {
    $success = "Course created successfully! Now set up the final quiz.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quiz_title = trim($_POST['quiz_title'] ?? 'Course Quiz');
    $pass_pct = intval($_POST['pass_percentage'] ?? 70);
    $questions = $_POST['questions'] ?? [];

    if (empty($questions)) {
        $error = "Please add at least one question.";
    } else {
        // Check if quiz exists
        $ext_quiz = $conn->query("SELECT id FROM quizzes WHERE course_id=$course_id")->fetch_assoc();
        if ($ext_quiz) {
            $quiz_id = $ext_quiz['id'];
            $conn->query("UPDATE quizzes SET title='$quiz_title', pass_percentage=$pass_pct WHERE id=$quiz_id");
            // Clear old questions
            $conn->query("DELETE FROM quiz_questions WHERE quiz_id=$quiz_id");
        } else {
            $stmt = $conn->prepare("INSERT INTO quizzes (course_id, title, pass_percentage) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $course_id, $quiz_title, $pass_pct);
            $stmt->execute();
            $quiz_id = $conn->insert_id;
        }

        // Insert new questions
        foreach ($questions as $q) {
            $text = trim($q['text'] ?? '');
            $opt_a = trim($q['a'] ?? '');
            $opt_b = trim($q['b'] ?? '');
            $opt_c = trim($q['c'] ?? '');
            $opt_d = trim($q['d'] ?? '');
            $ans = trim($q['ans'] ?? 'a');

            if (empty($text) || empty($opt_a) || empty($opt_b)) continue;

            $qst = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer) VALUES (?,?,?,?,?,?,?)");
            $qst->bind_param("issssss", $quiz_id, $text, $opt_a, $opt_b, $opt_c, $opt_d, $ans);
            $qst->execute();
        }
        $success = "Quiz successfully saved!";
    }
}

// Load existing
$ext_quiz = $conn->query("SELECT * FROM quizzes WHERE course_id=$course_id")->fetch_assoc();
$ext_questions = [];
if ($ext_quiz) {
    $q_res = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id={$ext_quiz['id']} ORDER BY id");
    while ($r = $q_res->fetch_assoc()) {
        $ext_questions[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Quiz - Instructor</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.q-block { background: white; border-radius: var(--radius); padding: 20px; margin-bottom: 16px; border: 1px solid var(--gray-border); position: relative; }
.q-block h4 { font-size: 1rem; color: var(--primary); margin-bottom: 15px; border-bottom: 1px solid var(--gray-light); padding-bottom: 10px; }
.remove-q-btn { position: absolute; top: 15px; right: 15px; background: var(--danger); color: white; border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer; font-size: 0.8rem; }
.add-q-btn { background: none; border: 2px dashed var(--primary); color: var(--primary); border-radius: var(--radius); padding: 14px; cursor: pointer; font-weight: 700; width: 100%; margin-bottom: 20px; }
.add-q-btn:hover { background: var(--primary-bg); }
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="add-course.php"><i class="fas fa-plus-circle"></i> Add Course</a></li>
      <li><a href="my-courses.php" class="active"><i class="fas fa-book"></i> My Courses</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-clipboard-question"></i> Manage Quiz for "<?= htmlspecialchars($course_check['title']) ?>"</h1>
      <a href="course-view.php?id=<?= $course_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to Course</a>
    </div>
    
    <div class="page-body">
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

      <form method="POST">
        <div class="card" style="padding:24px;margin-bottom:24px">
          <div class="form-group">
            <label>Quiz Title</label>
            <input type="text" name="quiz_title" class="form-control" value="<?= htmlspecialchars($ext_quiz['title'] ?? 'Final Course Assessment') ?>" required>
          </div>
          <div class="form-group">
            <label>Passing Percentage (%)</label>
            <input type="number" name="pass_percentage" class="form-control" value="<?= htmlspecialchars($ext_quiz['pass_percentage'] ?? 70) ?>" min="1" max="100" required>
          </div>
        </div>

        <div id="questionsContainer"></div>
        
        <button type="button" class="add-q-btn" onclick="addQuestion()"><i class="fas fa-plus"></i> Add Question</button>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%"><i class="fas fa-save"></i> Save Quiz</button>
      </form>
    </div>
  </div>
</div>

<script>
let qCount = 0;
const existingQuestions = <?= json_encode($ext_questions) ?>;

function addQuestion(data = null) {
    qCount++;
    const idx = qCount;
    const div = document.createElement('div');
    div.className = 'q-block';
    div.id = 'q_' + idx;
    
    const text = data ? data.question : '';
    const a = data ? data.option_a : '';
    const b = data ? data.option_b : '';
    const c = data ? data.option_c : '';
    const d = data ? data.option_d : '';
    const ans = data ? data.correct_answer : 'a';

    let html = `
      <h4>Question ${idx}</h4>
      <button type="button" class="remove-q-btn" onclick="document.getElementById('q_${idx}').remove()">Remove</button>
      <div class="form-group"><label>Question Text *</label><textarea name="questions[${idx}][text]" class="form-control" required>${text}</textarea></div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
        <div class="form-group"><label>Option A *</label><input type="text" name="questions[${idx}][a]" class="form-control" value="${a}" required></div>
        <div class="form-group"><label>Option B *</label><input type="text" name="questions[${idx}][b]" class="form-control" value="${b}" required></div>
        <div class="form-group"><label>Option C</label><input type="text" name="questions[${idx}][c]" class="form-control" value="${c}"></div>
        <div class="form-group"><label>Option D</label><input type="text" name="questions[${idx}][d]" class="form-control" value="${d}"></div>
      </div>
      <div class="form-group">
        <label>Correct Answer *</label>
        <select name="questions[${idx}][ans]" class="form-control">
          <option value="a" ${ans === 'a' ? 'selected' : ''}>Option A</option>
          <option value="b" ${ans === 'b' ? 'selected' : ''}>Option B</option>
          <option value="c" ${ans === 'c' ? 'selected' : ''}>Option C</option>
          <option value="d" ${ans === 'd' ? 'selected' : ''}>Option D</option>
        </select>
      </div>
    `;
    div.innerHTML = html;
    document.getElementById('questionsContainer').appendChild(div);
}

if (existingQuestions.length > 0) {
    existingQuestions.forEach(q => addQuestion(q));
} else {
    addQuestion(); // Add one blank
}
</script>
</body>
</html>
