<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];

$course_id = intval($_GET['id'] ?? 0);

// Check enrollment
$check = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
$check->bind_param("ii", $user_id, $course_id);
$check->execute();
if ($check->get_result()->num_rows === 0) { header('Location: courses.php'); exit; }

$course = $conn->query("SELECT c.*, i.full_name as instructor_name FROM courses c JOIN instructors i ON c.instructor_id=i.id WHERE c.id=$course_id")->fetch_assoc();
if (!$course) { header('Location: dashboard.php'); exit; }

// Handle manual mark lesson as done (for PDFs)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_lesson'])) {
    $lesson_id = intval($_POST['lesson_id']);
    // Verify lesson belongs to course
    $lcheck = $conn->query("SELECT l.id FROM lessons l JOIN units u ON l.unit_id=u.id WHERE l.id=$lesson_id AND u.course_id=$course_id");
    if ($lcheck->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO lesson_completions (user_id, lesson_id, progress, is_completed) VALUES (?,?, 100, 1) ON DUPLICATE KEY UPDATE progress=100, is_completed=1");
        $stmt->bind_param("ii", $user_id, $lesson_id);
        $stmt->execute();
        
        $u_info = $conn->query("SELECT last_login_date, current_streak FROM users WHERE id=$user_id")->fetch_assoc();
        $today = date('Y-m-d');
        if (($u_info['last_login_date'] ?? null) !== $today) {
            $streak = $u_info['current_streak'] ?? 0;
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $new_streak = (($u_info['last_login_date'] ?? null) === $yesterday) ? ($streak + 1) : 1;
            $conn->query("UPDATE users SET last_login_date='$today', current_streak=$new_streak WHERE id=$user_id");
        }
    }
    header("Location: course-learn.php?id=$course_id&lesson=$lesson_id");
    exit;
}

// Get all units with lessons & calc completions
$units_q = $conn->query("SELECT * FROM units WHERE course_id=$course_id ORDER BY unit_order");
$units_data = [];
$total_lessons = 0;
$completed_lessons = 0;

while ($u = $units_q->fetch_assoc()) {
    $lessons = [];
    $ls = $conn->query("SELECT * FROM lessons WHERE unit_id={$u['id']} ORDER BY lesson_order");
    $all_lessons_done = true;
    $lesson_count = 0;
    while ($l = $ls->fetch_assoc()) {
        $lc = $conn->query("SELECT is_completed, progress FROM lesson_completions WHERE user_id=$user_id AND lesson_id={$l['id']}")->fetch_assoc();
        $l['completed'] = !empty($lc['is_completed']);
        $l['progress'] = $lc['progress'] ?? 0;
        if (!$l['completed']) $all_lessons_done = false;
        
        $lessons[] = $l;
        $lesson_count++;
        $total_lessons++;
        if ($l['completed']) $completed_lessons++;
    }
    $u['completed'] = ($lesson_count > 0 && $all_lessons_done);
    $u['lessons'] = $lessons;
    $units_data[] = $u;
}

$progress = $total_lessons > 0 ? round(($completed_lessons/$total_lessons)*100) : 0;
$all_done = $completed_lessons >= $total_lessons && $total_lessons > 0;

// Fetch quizzes for this course
$quizzes_q = $conn->query("SELECT qz.*,
    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=qz.id) as question_count,
    (SELECT id FROM quiz_attempts WHERE user_id=$user_id AND quiz_id=qz.id ORDER BY attempted_at DESC LIMIT 1) as attempted,
    (SELECT passed FROM quiz_attempts WHERE user_id=$user_id AND quiz_id=qz.id ORDER BY attempted_at DESC LIMIT 1) as last_passed
    FROM quizzes qz WHERE qz.course_id=$course_id ORDER BY qz.created_at");
$course_quizzes = [];
while ($qz = $quizzes_q->fetch_assoc()) $course_quizzes[] = $qz;

// Check certificate
$cert = $conn->query("SELECT id FROM certificates WHERE user_id=$user_id AND course_id=$course_id")->num_rows > 0;

// Active lesson
$active_lesson_id = intval($_GET['lesson'] ?? 0);
if (!$active_lesson_id && !empty($units_data[0]['lessons'])) {
    $active_lesson_id = $units_data[0]['lessons'][0]['id'];
}

$active_lesson = null;
$active_lesson_completed = false;
$active_lesson_progress = 0;
foreach ($units_data as $u) {
    foreach ($u['lessons'] as $l) {
        if ($l['id'] == $active_lesson_id) {
            $active_lesson = $l;
            $active_lesson_completed = $l['completed'];
            $active_lesson_progress = $l['progress'];
            break 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($course['title']) ?> - Learning</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background:#f0f2f5; }
.learn-layout { display:grid; grid-template-columns:320px 1fr; min-height:100vh; }
.learn-sidebar { background:white; border-right:1px solid var(--gray-border); overflow-y:auto; height:100vh; position:sticky; top:0; }
.learn-sidebar-header { padding:18px; border-bottom:1px solid var(--gray-border); }
.learn-sidebar-header h3 { font-size:0.95rem; margin-bottom:6px; }
.progress-mini { background:var(--gray-border); border-radius:50px; height:6px; margin-top:8px; }
.progress-mini-fill { background:<?= $progress >= 100 ? 'var(--success)' : 'var(--primary)' ?>; border-radius:50px; height:6px; }
.unit-section { border-bottom:1px solid var(--gray-border); }
.unit-title { padding:12px 18px; background:var(--gray-light); font-size:0.88rem; font-weight:700; display:flex; justify-content:space-between; align-items:center; }
.unit-title .done-badge { background:var(--success); color:white; font-size:0.72rem; padding:2px 8px; border-radius:50px; }
.lesson-link-item { padding:10px 18px 10px 30px; display:flex; align-items:center; gap:10px; font-size:0.87rem; cursor:pointer; transition:var(--transition); text-decoration:none; color:var(--black); border-left:3px solid transparent; }
.lesson-link-item:hover { background:var(--primary-bg); border-left-color:var(--primary); }
.lesson-link-item.active { background:var(--primary-bg); border-left-color:var(--primary); color:var(--primary); font-weight:700; }
.lesson-link-item i { color:var(--primary); flex-shrink:0; }
.lesson-link-item .lesson-status { margin-left:auto; font-size:0.7rem; color:var(--gray); }
.lesson-link-item .lesson-status .fa-check-circle { color:var(--success); }
.learn-main { padding:32px; }
.video-frame-wrap { background:black; border-radius:var(--radius); overflow:hidden; margin-bottom:24px; position:relative; padding-top:56.25%; }
.video-frame-wrap iframe { position:absolute; top:0; left:0; width:100%; height:100%; border:none; }
.video-link-display { background:#1a1a2e; border-radius:var(--radius); padding:40px; text-align:center; color:white; margin-bottom:24px; }
.video-link-display a { color:#f06292; font-size:1.1rem; word-break:break-all; }
.mark-done-card { background:white; border-radius:var(--radius); padding:20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--gray-border); }
.cert-banner { background:linear-gradient(135deg,#065f46,#10b981); color:white; border-radius:var(--radius); padding:24px; text-align:center; margin-bottom:20px; }
.cert-banner h3 { font-size:1.3rem; margin-bottom:8px; }
.back-btn { display:inline-flex; align-items:center; gap:8px; color:var(--gray); font-size:0.88rem; margin-bottom:16px; font-weight:600; }
.back-btn:hover { color:var(--primary); }
.review-card { background:white; border-radius:var(--radius); padding:24px; border:1px solid var(--gray-border); margin-top:30px; }
.star-rating i { color:var(--gray-border); font-size:1.5rem; cursor:pointer; transition:color 0.2s; }
.star-rating i.active, .star-rating i:hover, .star-rating i:hover ~ i { color:#f59e0b; }
.star-rating { display:flex; flex-direction:row-reverse; justify-content:flex-end; gap:5px; margin-bottom:12px; }
.star-rating i:hover ~ i { color:#f59e0b; }
</style>
</head>
<body>

<div class="learn-layout">

  <!-- SIDEBAR -->
  <div class="learn-sidebar">
    <div class="learn-sidebar-header">
      <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
      <h3><?= htmlspecialchars($course['title']) ?></h3>
      <div style="font-size:0.78rem;color:var(--gray);margin-bottom:4px"><?= $completed_lessons ?>/<?= $total_lessons ?> lessons completed · <?= $progress ?>%</div>
      <div class="progress-mini"><div class="progress-mini-fill" style="width:<?= $progress ?>%"></div></div>
    </div>

    <?php foreach ($units_data as $u): ?>
    <div class="unit-section">
      <div class="unit-title">
        <span><i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px"></i> <?= htmlspecialchars($u['title']) ?></span>
        <?php if($u['completed']): ?><span class="done-badge"><i class="fas fa-check"></i> Done</span><?php endif; ?>
      </div>
      <?php foreach ($u['lessons'] as $l): ?>
      <a href="course-learn.php?id=<?= $course_id ?>&lesson=<?= $l['id'] ?>"
         class="lesson-link-item <?= $l['id'] == $active_lesson_id ? 'active' : '' ?>">
        <i class="fas <?= ($l['lesson_type'] ?? 'video') === 'coding' ? 'fa-code' : (strpos(strtolower($l['lesson_link'] ?? ''), '.pdf') ? 'fa-file-pdf' : 'fa-play-circle') ?>"></i>
        <?= htmlspecialchars($l['title']) ?>
        <span class="lesson-status">
          <?php if($l['completed']): ?><i class="fas fa-check-circle"></i><?php endif; ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($course_quizzes)): ?>
    <div class="unit-section">
      <div class="unit-title">
        <span><i class="fas fa-question-circle" style="color:var(--primary);margin-right:6px"></i> Course Quiz</span>
      </div>
      <?php foreach ($course_quizzes as $qz): ?>
      <?php if (!$all_done): ?>
      <div class="lesson-link-item" style="justify-content:space-between;opacity:0.6;cursor:not-allowed;" title="Complete all lessons to unlock quiz">
        <span style="display:flex;align-items:center;gap:10px">
          <i class="fas fa-lock" style="color:var(--gray)"></i>
          <?= htmlspecialchars($qz['title']) ?>
        </span>
      </div>
      <?php else: ?>
      <a href="quiz.php?id=<?= $qz['id'] ?>&course_id=<?= $course_id ?>"
         class="lesson-link-item" style="justify-content:space-between">
        <span style="display:flex;align-items:center;gap:10px">
          <i class="fas fa-clipboard-question" style="color:var(--primary)"></i>
          <?= htmlspecialchars($qz['title']) ?>
        </span>
        <?php if ($qz['attempted']): ?>
          <?php if ($qz['last_passed']): ?>
          <span style="font-size:.7rem;background:#dcfce7;color:#16a34a;padding:2px 6px;border-radius:50px;font-weight:700">✓ Passed</span>
          <?php else: ?>
          <span style="font-size:.7rem;background:#fee2e2;color:#dc2626;padding:2px 6px;border-radius:50px;font-weight:700">Retry</span>
          <?php endif; ?>
        <?php endif; ?>
      </a>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- MAIN CONTENT -->
  <div class="learn-main">

    <?php if ($cert): ?>
    <div class="cert-banner">
      <div style="font-size:2.5rem;margin-bottom:8px">🏆</div>
      <h3>Course Completed!</h3>
      <p style="opacity:0.9;margin-bottom:16px">Congratulations! You've passed the course quiz.</p>
      <a href="certificate.php?course_id=<?= $course_id ?>" class="btn btn-primary" style="background:white;color:var(--success);font-weight:800">
        <i class="fas fa-certificate"></i> View & Download Certificate
      </a>
    </div>
    <?php endif; ?>

    <?php if ($active_lesson): ?>
    <!-- Lesson Content -->
    <h2 style="margin-bottom:6px"><?= htmlspecialchars($active_lesson['title']) ?></h2>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;color:var(--gray);font-size:0.88rem;">
       <span><i class="fas fa-info-circle"></i> <?= ($active_lesson['lesson_type'] ?? 'video') === 'coding' ? 'Submit correct code to mark as done.' : 'View content to mark as done. Video requires 80% watch time.' ?></span>
       <?php if($active_lesson_completed): ?>
          <span style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#059669,#10b981);color:white;padding:6px 14px;border-radius:50px;font-weight:700;font-size:0.82rem;box-shadow:0 2px 8px rgba(16,185,129,0.3);"><i class="fas fa-check-circle"></i> Completed</span>
       <?php else: ?>
          <span id="progress-text" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#dc2626,#ef4444);color:white;padding:6px 14px;border-radius:50px;font-weight:700;font-size:0.82rem;box-shadow:0 2px 8px rgba(220,38,38,0.3);"><i class="fas fa-spinner fa-pulse"></i> Progress: <?= $active_lesson_progress ?>%</span>
       <?php endif; ?>
    </div>

    <?php
    $l_type = $active_lesson['lesson_type'] ?? 'video';
    $link = $active_lesson['lesson_link'] ?? '';
    
    if ($l_type === 'coding'):
    ?>
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div style="background:#1e1e1e; padding:20px; border-radius:var(--radius); border:1px solid #333;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
                <span style="color:#10b981; font-weight:bold; font-size:0.9rem;"><i class="fas fa-code"></i> Code Editor</span>
            </div>
            <textarea id="code_input" style="width:100%; height:300px; background:#2d2d2d; color:#e0e0e0; font-family:monospace; padding:12px; border:1px solid #444; border-radius:6px; resize:vertical; font-size:14px;"><?= htmlspecialchars($active_lesson['coding_boilerplate'] ?? '') ?></textarea>
            <div style="margin-top:16px;">
                <button id="run_btn" class="btn" style="background:#10b981; color:white; font-weight:bold; padding:10px 24px;">
                    <i class="fas fa-play"></i> Run & Submit Code
                </button>
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:20px;">
            <div style="background:white; padding:20px; border-radius:var(--radius); border:1px solid var(--gray-border);">
                <span style="color:var(--gray); font-size:0.85rem; font-weight:bold; text-transform:uppercase;">Expected Output</span>
                <div style="background:#f8f9fa; padding:12px; margin-top:10px; border-radius:6px; font-family:monospace; white-space:pre-wrap; border:1px solid #e2e8f0;"><?= htmlspecialchars($active_lesson['coding_expected_output'] ?? '') ?></div>
            </div>
            <div style="background:white; padding:20px; border-radius:var(--radius); border:1px solid var(--gray-border); flex-grow:1;">
                <span style="color:var(--gray); font-size:0.85rem; font-weight:bold; text-transform:uppercase;">Console Output</span>
                <div id="console_output" style="background:#1e1e1e; color:#d4d4d4; padding:12px; margin-top:10px; border-radius:6px; font-family:monospace; min-height:100px; white-space:pre-wrap;">...</div>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('run_btn').addEventListener('click', function() {
        const btn = this;
        const code = document.getElementById('code_input').value;
        const consoleOut = document.getElementById('console_output');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Running...';
        consoleOut.innerHTML = 'Executing code on Judge0...';
        consoleOut.style.color = '#d4d4d4';
        
        fetch('../ajax/execute-code.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ lesson_id: <?= $active_lesson['id'] ?>, source_code: code })
        }).then(res => res.json()).then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Run & Submit Code';
            
            if (data.error) {
                consoleOut.innerHTML = data.error;
                consoleOut.style.color = '#ef4444';
            } else {
                consoleOut.innerHTML = data.output || '(No output)';
                if (data.passed) {
                    consoleOut.innerHTML += '\n\n<span style="color:#10b981; font-weight:bold;">[SUCCESS] Passed! You can now move to the next lesson.</span>';
                    // Update UI automatically
                    let pText = document.getElementById('progress-text');
                    if(pText) {
                        pText.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                        pText.style.background = 'linear-gradient(135deg,#059669,#10b981)';
                        pText.style.boxShadow = '0 2px 8px rgba(16,185,129,0.3)';
                    }
                    let sidebarStatus = document.querySelector('.lesson-link-item.active .lesson-status');
                    if(sidebarStatus) {
                        sidebarStatus.innerHTML = '<i class="fas fa-check-circle"></i>';
                    }
                } else {
                    consoleOut.innerHTML += '\n\n<span style="color:#ef4444; font-weight:bold;">[FAILED] Output did not match exactly. Status: ' + data.status + '</span>';
                }
            }
        }).catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Run & Submit Code';
            consoleOut.innerHTML = 'Network error. Try again.';
            consoleOut.style.color = '#ef4444';
        });
    });
    </script>

    <?php else:
    $is_youtube = strpos($link, 'youtube.com') !== false || strpos($link, 'youtu.be') !== false;
    $is_pdf = strpos(strtolower($link), '.pdf') !== false;
    $youtube_id = '';
    if ($is_youtube) {
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $link, $matches);
        $youtube_id = $matches[1] ?? '';
    }
    ?>

    <?php if ($is_youtube && $youtube_id): ?>
    <div class="video-frame-wrap" id="player-wrap">
      <div id="ytplayer"></div>
    </div>
    <script>
      var tag = document.createElement('script');
      tag.src = "https://www.youtube.com/iframe_api";
      var firstScriptTag = document.getElementsByTagName('script')[0];
      firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

      var player;
      function onYouTubeIframeAPIReady() {
        player = new YT.Player('ytplayer', {
          height: '100%',
          width: '100%',
          videoId: '<?= $youtube_id ?>',
          events: {
            'onStateChange': onPlayerStateChange
          }
        });
      }

      var progressInterval;
      var lastReported = <?= $active_lesson_progress ?>;
      
      function onPlayerStateChange(event) {
        if (event.data == YT.PlayerState.PLAYING) {
          progressInterval = setInterval(checkProgress, 5000);
        } else {
          clearInterval(progressInterval);
        }
      }

      function checkProgress() {
        var duration = player.getDuration();
        var current = player.getCurrentTime();
        if(duration > 0) {
           var pct = Math.round((current / duration) * 100);
           if(pct > lastReported) {
              lastReported = pct;
              document.getElementById('progress-text').innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Progress: ' + pct + '%';
              fetch('../ajax/update-progress.php', {
                  method: 'POST',
                  headers: {'Content-Type': 'application/json'},
                  body: JSON.stringify({ lesson_id: <?= $active_lesson['id'] ?>, progress: pct })
              }).then(res => res.json()).then(data => {
                  if(data.is_completed) {
                      let pText = document.getElementById('progress-text');
                      if(pText) {
                          pText.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                          pText.style.background = 'linear-gradient(135deg,#059669,#10b981)';
                          pText.style.boxShadow = '0 2px 8px rgba(16,185,129,0.3)';
                      }
                      let sidebarStatus = document.querySelector('.lesson-link-item.active .lesson-status');
                      if(sidebarStatus) {
                          sidebarStatus.innerHTML = '<i class="fas fa-check-circle"></i>';
                      }
                  }
              });
           }
        }
      }
    </script>

    <?php else: ?>
    <div class="video-link-display">
      <div style="font-size:3rem;margin-bottom:12px"><i class="fas <?= $is_pdf ? 'fa-file-pdf' : 'fa-link' ?>" style="color:#10b981"></i></div>
      <p style="margin-bottom:16px;opacity:0.8"><?= $is_pdf ? 'Read the PDF Document' : 'Click to open lesson content' ?></p>
      <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="btn btn-primary" style="padding:12px 28px;font-size:0.95rem;">
        <i class="fas <?= $is_pdf ? 'fa-file-pdf' : 'fa-play' ?>"></i> Open Content
      </a>
    </div>
    
      <?php if(!$active_lesson_completed): ?>
      <div class="mark-done-card">
        <div>
           <strong><i class="fas fa-check-circle" style="color:var(--primary)"></i> Mark as Done</strong>
           <p style="color:var(--gray);font-size:0.88rem;margin-top:4px">Once you have reviewed this material, mark it complete.</p>
        </div>
        <form method="POST">
          <input type="hidden" name="lesson_id" value="<?= $active_lesson['id'] ?>">
          <button type="submit" name="mark_lesson" class="btn btn-success">
            <i class="fas fa-check"></i> Complete Lesson
          </button>
        </form>
      </div>
      <?php endif; ?>
    <?php endif; ?>
    <?php endif; // End of lesson type check ?>

    <?php else: ?>
    <div style="text-align:center;padding:60px;color:var(--gray)">
      <i class="fas fa-book-open" style="font-size:3rem;display:block;margin-bottom:16px"></i>
      <h3>Select a lesson to begin</h3>
    </div>
    <?php endif; ?>

    <!-- Progress Overview -->
    <div class="card" style="padding:20px; margin-top:20px;">
      <h4 style="margin-bottom:14px"><i class="fas fa-tasks" style="color:var(--primary)"></i> Your Progress</h4>
      <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:var(--gray);margin-bottom:8px">
        <span><?= $completed_lessons ?> of <?= $total_lessons ?> lessons completed</span>
        <span><?= $progress ?>%</span>
      </div>
      <div style="background:var(--gray-border);border-radius:50px;height:10px">
        <div style="background:var(--primary);border-radius:50px;height:10px;width:<?= $progress ?>%;transition:width 0.5s ease"></div>
      </div>
    </div>

    <!-- Rating & Feedback Section -->
    <?php if ($cert): ?>
    <div class="review-card" id="rating-section">
      <h3 style="margin-bottom:10px"><i class="fas fa-star" style="color:#f59e0b"></i> Rate this Course</h3>
      <?php
         $existing_rev = $conn->query("SELECT * FROM ratings WHERE user_id=$user_id AND course_id=$course_id")->fetch_assoc();
      ?>
      <?php if($existing_rev): ?>
         <div style="color:var(--gray);font-size:0.9rem;">
            You rated it: <?php for($i=0;$i<$existing_rev['rating'];$i++) echo '<i class="fas fa-star" style="color:#f59e0b"></i>'; ?>
            <p style="margin-top:10px;font-style:italic">"<?= htmlspecialchars($existing_rev['review']) ?>"</p>
         </div>
      <?php else: ?>
         <p style="color:var(--gray);font-size:0.88rem;margin-bottom:16px;">Help others by sharing your feedback!</p>
         <div style="display:flex; flex-direction:column; max-width:400px; gap:10px;">
           <div class="star-rating" style="justify-content:flex-start; flex-direction:row;">
              <i class="fas fa-star star-btn" data-val="1"></i>
              <i class="fas fa-star star-btn" data-val="2"></i>
              <i class="fas fa-star star-btn" data-val="3"></i>
              <i class="fas fa-star star-btn" data-val="4"></i>
              <i class="fas fa-star star-btn" data-val="5"></i>
           </div>
           <textarea id="review-text" class="form-control" rows="3" placeholder="Write your review..."></textarea>
           <button class="btn btn-primary" id="submit-review-btn">Submit Feedback</button>
           <div id="review-msg" style="font-size:0.85rem; font-weight:bold; margin-top:5px;"></div>
         </div>
         <script>
            let currentRating = 0;
            document.querySelectorAll('.star-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentRating = parseInt(this.getAttribute('data-val'));
                    document.querySelectorAll('.star-btn').forEach(b => {
                        if(parseInt(b.getAttribute('data-val')) <= currentRating) {
                            b.classList.add('active');
                            b.style.color = '#f59e0b';
                        } else {
                            b.classList.remove('active');
                            b.style.color = 'var(--gray-border)';
                        }
                    });
                });
            });

            document.getElementById('submit-review-btn').addEventListener('click', function() {
                if(currentRating === 0) {
                    document.getElementById('review-msg').innerText = 'Please select a star rating.';
                    document.getElementById('review-msg').style.color = 'red';
                    return;
                }
                const review = document.getElementById('review-text').value;
                fetch('../ajax/submit-rating.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ course_id: <?= $course_id ?>, rating: currentRating, review: review })
                }).then(res => res.json()).then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        document.getElementById('review-msg').innerText = data.error || 'Error submitting review.';
                        document.getElementById('review-msg').style.color = 'red';
                    }
                });
            });
         </script>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
