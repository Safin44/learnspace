<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['instructor_id'])) { header('Location: login.php'); exit; }
$ins_id = $_SESSION['instructor_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='instructor' AND sender_id=$ins_id AND status='resolved'")->fetch_assoc()['c'] > 0;

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $units = $_POST['units'] ?? [];
    $thumbnail = '';

    // Handle thumbnail
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_name = uniqid('thumb_') . '.' . $ext;
            $dest = '../assets/images/' . $new_name;
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dest)) {
                $thumbnail = $new_name;
            }
        }
    }

    if (empty($title) || empty($description)) {
        $error = 'Course title and description are required.';
    } elseif (empty($units)) {
        $error = 'Please add at least one unit.';
    } else {
        // Insert course
        $stmt = $conn->prepare("INSERT INTO courses (instructor_id, title, description, thumbnail) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $ins_id, $title, $description, $thumbnail);
        $stmt->execute();
        $course_id = $conn->insert_id;

        // Insert units and lessons
        foreach ($units as $u_idx => $unit) {
            $unit_title = trim($unit['title'] ?? '');
            if (empty($unit_title)) continue;
            $u_stmt = $conn->prepare("INSERT INTO units (course_id, title, unit_order) VALUES (?,?,?)");
            $u_stmt->bind_param("isi", $course_id, $unit_title, $u_idx);
            $u_stmt->execute();
            $unit_id = $conn->insert_id;

            $lessons = $unit['lessons'] ?? [];
            foreach ($lessons as $l_idx => $lesson) {
                $l_title = trim($lesson['title'] ?? '');
                $l_link = trim($lesson['link'] ?? '');
                if (empty($l_title) || empty($l_link)) continue;
                $l_stmt = $conn->prepare("INSERT INTO lessons (unit_id, title, lesson_link, lesson_order) VALUES (?,?,?,?)");
                $l_stmt->bind_param("issi", $unit_id, $l_title, $l_link, $l_idx);
                $l_stmt->execute();
            }
        }
        // Process Quiz if added
        if (isset($_POST['quiz']) && !empty($_POST['quiz']['title'])) {
            $q_title = trim($_POST['quiz']['title']);
            $q_pass = intval($_POST['quiz']['pass_percentage'] ?? 70);
            
            $q_stmt = $conn->prepare("INSERT INTO quizzes (course_id, title, pass_percentage) VALUES (?, ?, ?)");
            $q_stmt->bind_param("isi", $course_id, $q_title, $q_pass);
            $q_stmt->execute();
            $quiz_id = $conn->insert_id;

            $questions = $_POST['quiz']['questions'] ?? [];
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
        }

        $success = 'Course and modules created successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Course - Instructor</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.unit-block {
  background: var(--gray-light);
  border-radius: var(--radius);
  padding: 20px;
  margin-bottom: 16px;
  border: 1px solid var(--gray-border);
}
.unit-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}
.unit-header h4 { flex: 1; font-size: 1rem; color: var(--primary); }
.lesson-block {
  background: white;
  border-radius: var(--radius-sm);
  padding: 14px;
  margin-bottom: 10px;
  border: 1px solid var(--gray-border);
}
.lesson-row {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 10px;
  align-items: start;
}
.remove-btn {
  background: var(--danger);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 0.85rem;
  margin-top: 24px;
}
.add-lesson-btn {
  background: none;
  border: 2px dashed var(--primary);
  color: var(--primary);
  border-radius: var(--radius-sm);
  padding: 8px 16px;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.85rem;
  width: 100%;
  margin-top: 8px;
  transition: var(--transition);
}
.add-lesson-btn:hover { background: var(--primary-bg); }
.add-unit-btn {
  background: none;
  border: 2px dashed var(--primary);
  color: var(--primary);
  border-radius: var(--radius);
  padding: 14px;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.95rem;
  width: 100%;
  margin-bottom: 16px;
  transition: var(--transition);
}
.add-unit-btn:hover { background: var(--primary-bg); }
</style>
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="add-course.php" class="active"><i class="fas fa-plus-circle"></i> Add Course</a></li>
      <li><a href="my-courses.php"><i class="fas fa-book"></i> My Courses</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span title="One or more issues resolved" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header"><h1><i class="fas fa-plus-circle"></i> Add New Course</h1></div>
    <div class="page-body">
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?> <a href="my-courses.php">View My Courses</a></div><?php endif; ?>

      <form method="POST" id="courseForm" enctype="multipart/form-data">
        <!-- Basic Info -->
        <div class="card" style="padding:24px;margin-bottom:24px">
          <div class="section-title">📋 Course Information</div>
          <div class="form-group">
            <label>Course Title *</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Complete Web Development Bootcamp" required>
          </div>
          <div class="form-group">
            <label>Course Description *</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Describe what students will learn in this course..." required></textarea>
          </div>
          <div class="form-group">
            <label>Course Thumbnail (Optional)</label>
            <input type="file" name="thumbnail" class="form-control" accept="image/*">
          </div>
        </div>

        <!-- Units -->
        <div class="card" style="padding:24px;margin-bottom:24px">
          <div class="section-title">📚 Course Content (Units & Lessons)</div>
          <div id="unitsContainer"></div>
          <button type="button" class="add-unit-btn" onclick="addUnit()">
            <i class="fas fa-plus"></i> Add Unit
          </button>
        </div>

        <!-- Quiz Section -->
        <div class="card" style="padding:24px;margin-bottom:24px" id="quizWrapper">
          <div class="section-title">📝 Final Course Quiz (Optional)</div>
          <div id="quizContainer" style="display:none;">
            <div class="form-group">
              <label>Quiz Title *</label>
              <input type="text" name="quiz[title]" class="form-control" placeholder="e.g. Final Course Assessment" id="quizTitleInput">
            </div>
            <div class="form-group">
              <label>Passing Percentage (%) *</label>
              <input type="number" name="quiz[pass_percentage]" class="form-control" value="70" min="1" max="100">
            </div>
            <div id="quizQuestionsContainer"></div>
            <button type="button" class="add-lesson-btn" onclick="addQuizQuestion()" style="border-color:var(--primary);color:var(--primary)"><i class="fas fa-plus"></i> Add Question</button>
            <div style="margin-top:16px;text-align:right;">
               <button type="button" onclick="removeQuiz()" style="background:none;color:var(--danger);border:1px solid var(--danger);border-radius:6px;padding:8px 16px;cursor:pointer;font-size:0.85rem"><i class="fas fa-trash"></i> Cancel Quiz</button>
            </div>
          </div>
          <button type="button" class="add-unit-btn" id="addQuizBtn" onclick="enableQuiz()" style="border-color:var(--primary);color:var(--primary)">
            <i class="fas fa-clipboard-question"></i> Add Final Course Quiz
          </button>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
          <i class="fas fa-paper-plane"></i> Submit Course for Approval
        </button>
      </form>
    </div>
  </div>
</div>

<script>
let unitCount = 0;

function addUnit() {
  unitCount++;
  const idx = unitCount;
  const container = document.getElementById('unitsContainer');
  const div = document.createElement('div');
  div.className = 'unit-block';
  div.id = `unit_${idx}`;
  div.innerHTML = `
    <div class="unit-header">
      <h4><i class="fas fa-layer-group"></i> Unit ${idx}</h4>
      <button type="button" onclick="removeUnit(${idx})" style="background:var(--danger);color:white;border:none;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:0.82rem">
        <i class="fas fa-trash"></i> Remove Unit
      </button>
    </div>
    <div class="form-group">
      <label>Unit Title *</label>
      <input type="text" name="units[${idx}][title]" class="form-control" placeholder="e.g. Introduction to HTML" required>
    </div>
    <div class="lessons-container" id="lessons_${idx}"></div>
    <button type="button" class="add-lesson-btn" onclick="addLesson(${idx})">
      <i class="fas fa-plus"></i> Add Lesson to this Unit
    </button>
  `;
  container.appendChild(div);
  addLesson(idx);
}

let lessonCounts = {};

function addLesson(unitIdx) {
  if (!lessonCounts[unitIdx]) lessonCounts[unitIdx] = 0;
  lessonCounts[unitIdx]++;
  const lIdx = lessonCounts[unitIdx];
  const container = document.getElementById(`lessons_${unitIdx}`);
  const div = document.createElement('div');
  div.className = 'lesson-block';
  div.id = `lesson_${unitIdx}_${lIdx}`;
  div.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <strong style="font-size:0.88rem;color:var(--gray)">Lesson ${lIdx}</strong>
      <button type="button" onclick="removeLesson(${unitIdx},${lIdx})" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.85rem"><i class="fas fa-times"></i> Remove</button>
    </div>
    <div class="lesson-row">
      <div class="form-group" style="margin-bottom:0">
        <label style="font-size:0.83rem">Lesson Name *</label>
        <input type="text" name="units[${unitIdx}][lessons][${lIdx}][title]" class="form-control" placeholder="e.g. What is HTML?" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label style="font-size:0.83rem">Lesson Link *</label>
        <input type="url" name="units[${unitIdx}][lessons][${lIdx}][link]" class="form-control" placeholder="https://youtube.com/watch?v=..." required>
      </div>
    </div>
  `;
  container.appendChild(div);
}

function removeUnit(idx) {
  document.getElementById(`unit_${idx}`)?.remove();
}

function removeLesson(unitIdx, lIdx) {
  document.getElementById(`lesson_${unitIdx}_${lIdx}`)?.remove();
}

let quizQCount = 0;

function enableQuiz() {
    document.getElementById('quizContainer').style.display = 'block';
    document.getElementById('addQuizBtn').style.display = 'none';
    document.getElementById('quizTitleInput').required = true;
    if (quizQCount === 0) addQuizQuestion();
}

function removeQuiz() {
    document.getElementById('quizContainer').style.display = 'none';
    document.getElementById('addQuizBtn').style.display = 'block';
    document.getElementById('quizTitleInput').required = false;
    document.getElementById('quizTitleInput').value = '';
    document.getElementById('quizQuestionsContainer').innerHTML = '';
    quizQCount = 0;
}

function addQuizQuestion() {
    quizQCount++;
    const idx = quizQCount;
    const container = document.getElementById('quizQuestionsContainer');
    const div = document.createElement('div');
    div.className = 'lesson-block';
    div.id = `q_${idx}`;
    div.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <strong style="font-size:0.88rem;color:var(--primary)">Question ${idx}</strong>
        <button type="button" onclick="document.getElementById('q_${idx}').remove()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.85rem"><i class="fas fa-times"></i> Remove</button>
      </div>
      <div class="form-group"><label>Question Text *</label><textarea name="quiz[questions][${idx}][text]" class="form-control" required></textarea></div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
        <div class="form-group"><label>Option A *</label><input type="text" name="quiz[questions][${idx}][a]" class="form-control" required></div>
        <div class="form-group"><label>Option B *</label><input type="text" name="quiz[questions][${idx}][b]" class="form-control" required></div>
        <div class="form-group"><label>Option C</label><input type="text" name="quiz[questions][${idx}][c]" class="form-control"></div>
        <div class="form-group"><label>Option D</label><input type="text" name="quiz[questions][${idx}][d]" class="form-control"></div>
      </div>
      <div class="form-group">
        <label>Correct Answer *</label>
        <select name="quiz[questions][${idx}][ans]" class="form-control">
          <option value="a">Option A</option>
          <option value="b">Option B</option>
          <option value="c">Option C</option>
          <option value="d">Option D</option>
        </select>
      </div>
    `;
    container.appendChild(div);
}

// Add first unit on load
addUnit();
</script>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
