<?php
session_start();
require_once '../includes/db.php';
$user_id = $_SESSION['user_id'] ?? 0;

$id = intval($_GET['id'] ?? 0);
$course = $conn->query("SELECT c.*, i.full_name as instructor_name, i.bio as instructor_bio, i.expertise,
    COALESCE(AVG(r.rating),0) as avg_rating,
    COUNT(DISTINCT e.id) as student_count
    FROM courses c
    JOIN instructors i ON c.instructor_id=i.id
    LEFT JOIN ratings r ON c.id=r.course_id
    LEFT JOIN enrollments e ON c.id=e.course_id
    WHERE c.id=$id AND c.status='approved'
    GROUP BY c.id")->fetch_assoc();

if (!$course) { header('Location: courses.php'); exit; }

// Check enrollment
$is_enrolled = false;
if ($user_id) {
    $check = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
    $check->bind_param("ii", $user_id, $id);
    $check->execute();
    $is_enrolled = $check->get_result()->num_rows > 0;
}

// Handle enroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    if (!$user_id) {
        $_SESSION['redirect_after_login'] = "course-detail.php?id=$id";
        header('Location: login.php');
        exit;
    }
    if (!$is_enrolled) {
        $stmt = $conn->prepare("INSERT INTO enrollments (user_id, course_id) VALUES (?,?)");
        $stmt->bind_param("ii", $user_id, $id);
        $stmt->execute();
        $is_enrolled = true;
    }
    header("Location: course-learn.php?id=$id");
    exit;
}

$units = $conn->query("SELECT u.*, COUNT(l.id) as lesson_count FROM units u LEFT JOIN lessons l ON u.id=l.unit_id WHERE u.course_id=$id GROUP BY u.id ORDER BY u.unit_order");
$reviews = $conn->query("SELECT r.*, u.full_name FROM ratings r JOIN users u ON r.user_id=u.id WHERE r.course_id=$id ORDER BY r.created_at DESC LIMIT 5");

// Count total lessons
$total_lessons = $conn->query("SELECT COUNT(*) as c FROM lessons l JOIN units u ON l.unit_id=u.id WHERE u.course_id=$id")->fetch_assoc()['c'];
$total_units = $conn->query("SELECT COUNT(*) as c FROM units WHERE course_id=$id")->fetch_assoc()['c'];
$icons = ['💻','📊','🎨','📈','🔬','📱','🌐','🎯'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($course['title']) ?> - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.course-hero { background:linear-gradient(135deg,#1a0000,#3d0000); color:white; padding:50px 60px; }
.course-hero h1 { font-size:2rem; margin-bottom:12px; }
.course-hero p { opacity:0.85; max-width:600px; line-height:1.8; margin-bottom:20px; }
.course-hero-meta { display:flex; gap:20px; flex-wrap:wrap; font-size:0.9rem; opacity:0.9; }
.course-hero-meta span { display:flex; align-items:center; gap:6px; }
.course-body { display:grid; grid-template-columns:1fr 340px; gap:32px; padding:40px 60px; max-width:1300px; margin:0 auto; }
.enroll-card { background:white; border-radius:var(--radius-lg); padding:28px; box-shadow:0 8px 32px rgba(0,0,0,0.12); border:1px solid var(--gray-border); position:sticky; top:90px; }
.enroll-price { font-size:2rem; font-weight:900; color:var(--success); margin-bottom:4px; }
.enroll-free { font-size:0.85rem; color:var(--gray); margin-bottom:20px; }
.enroll-features { list-style:none; margin-bottom:20px; }
.enroll-features li { padding:8px 0; border-bottom:1px solid var(--gray-border); font-size:0.9rem; display:flex; align-items:center; gap:8px; }
.enroll-features li i { color:var(--success); width:18px; }
.accordion-unit { border:1px solid var(--gray-border); border-radius:var(--radius-sm); margin-bottom:8px; overflow:hidden; }
.accordion-header { background:var(--gray-light); padding:14px 16px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:0.95rem; }
.accordion-header:hover { background:#eee; }
.accordion-body { display:none; padding:0; }
.accordion-body.open { display:block; }
.lesson-row-detail { padding:10px 16px; display:flex; align-items:center; gap:10px; border-top:1px solid var(--gray-border); font-size:0.88rem; }
.lesson-row-detail i { color:var(--primary); width:16px; }
</style>
</head>
<body>
<nav class="navbar">
  <a href="../index.php" class="navbar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></a>
  <ul class="nav-links">
    <li><a href="../index.php">Home</a></li>
    <li><a href="courses.php">Courses</a></li>
    <?php if($user_id): ?><li><a href="dashboard.php">Dashboard</a></li><?php endif; ?>
  </ul>
  <div class="nav-actions">
    <?php if($user_id): ?>
      <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-outline">Login</a>
      <a href="register.php" class="btn btn-primary">Join Free</a>
    <?php endif; ?>
  </div>
</nav>

<div class="course-hero">
  <?php if (!empty($course['thumbnail'])): ?>
  <img src="../assets/images/<?= htmlspecialchars($course['thumbnail']) ?>" alt="Course Thumbnail" style="width:160px;height:110px;object-fit:cover;border-radius:var(--radius);border:2px solid rgba(255,255,255,0.2);margin-bottom:12px;">
  <?php else: ?>
  <div style="font-size:3rem;margin-bottom:12px"><?= $icons[$id % count($icons)] ?></div>
  <?php endif; ?>
  <h1><?= htmlspecialchars($course['title']) ?></h1>
  <p><?= htmlspecialchars($course['description']) ?></p>
  <div class="course-hero-meta">
    <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($course['instructor_name']) ?></span>
    <span><i class="fas fa-star" style="color:#fbbf24"></i> <?= number_format($course['avg_rating'],1) ?> rating</span>
    <span><i class="fas fa-users"></i> <?= $course['student_count'] ?> students</span>
    <span><i class="fas fa-layer-group"></i> <?= $total_units ?> units</span>
    <span><i class="fas fa-play-circle"></i> <?= $total_lessons ?> lessons</span>
  </div>
</div>

<div class="course-body">
  <!-- LEFT -->
  <div>
    <!-- Curriculum -->
    <h2 style="margin-bottom:16px"><i class="fas fa-list-ul" style="color:var(--primary)"></i> Course Curriculum</h2>
    <?php
    // Reset units query
    $units2 = $conn->query("SELECT u.*, COUNT(l.id) as lesson_count FROM units u LEFT JOIN lessons l ON u.id=l.unit_id WHERE u.course_id=$id GROUP BY u.id ORDER BY u.unit_order");
    $ui = 1;
    while($unit = $units2->fetch_assoc()):
    ?>
    <div class="accordion-unit">
      <div class="accordion-header" onclick="toggleAccordion(this)">
        <span><i class="fas fa-layer-group" style="color:var(--primary);margin-right:8px"></i> Unit <?= $ui++ ?>: <?= htmlspecialchars($unit['title']) ?></span>
        <div style="display:flex;align-items:center;gap:12px">
          <small style="color:var(--gray)"><?= $unit['lesson_count'] ?> lessons</small>
          <i class="fas fa-chevron-down"></i>
        </div>
      </div>
      <div class="accordion-body">
        <?php $lessons = $conn->query("SELECT * FROM lessons WHERE unit_id={$unit['id']} ORDER BY lesson_order");
        while($l = $lessons->fetch_assoc()): ?>
        <div class="lesson-row-detail">
          <i class="fas fa-play-circle"></i>
          <span><?= htmlspecialchars($l['title']) ?></span>
          <?php if($is_enrolled): ?>
          <a href="<?= htmlspecialchars($l['lesson_link']) ?>" target="_blank" style="margin-left:auto;font-size:0.78rem;color:var(--primary)">Watch →</a>
          <?php else: ?>
          <span style="margin-left:auto;font-size:0.78rem;color:var(--gray)"><i class="fas fa-lock"></i> Enroll to access</span>
          <?php endif; ?>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
    <?php endwhile; ?>

    <!-- Instructor -->
    <div style="margin-top:32px">
      <h2 style="margin-bottom:16px"><i class="fas fa-user-tie" style="color:var(--primary)"></i> About the Instructor</h2>
      <div class="card" style="padding:20px;display:flex;gap:16px;align-items:flex-start">
        <div class="avatar" style="width:60px;height:60px;font-size:1.5rem;flex-shrink:0;background:var(--primary-bg)">
          <?= strtoupper(substr($course['instructor_name'],0,1)) ?>
        </div>
        <div>
          <h3 style="font-size:1.05rem"><?= htmlspecialchars($course['instructor_name']) ?></h3>
          <?php if($course['expertise']): ?><p style="color:var(--primary);font-size:0.85rem;font-weight:700"><?= htmlspecialchars($course['expertise']) ?></p><?php endif; ?>
          <?php if($course['instructor_bio']): ?><p style="color:var(--gray);font-size:0.88rem;margin-top:6px;line-height:1.7"><?= htmlspecialchars($course['instructor_bio']) ?></p><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Reviews -->
    <div style="margin-top:32px">
      <h2 style="margin-bottom:16px"><i class="fas fa-star" style="color:#fbbf24"></i> Student Reviews</h2>
      <?php $rcount=0; while($rev=$reviews->fetch_assoc()): $rcount++; ?>
      <div class="card" style="padding:16px;margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <strong><?= htmlspecialchars($rev['full_name']) ?></strong>
          <span class="stars"><?= str_repeat('★',$rev['rating']).str_repeat('☆',5-$rev['rating']) ?></span>
        </div>
        <?php if($rev['review']): ?><p style="font-size:0.88rem;color:var(--gray)"><?= htmlspecialchars($rev['review']) ?></p><?php endif; ?>
      </div>
      <?php endwhile; ?>
      <?php if($rcount===0): ?><p style="color:var(--gray)">No reviews yet. Be the first!</p><?php endif; ?>
    </div>
  </div>

  <!-- ENROLL CARD -->
  <div>
    <div class="enroll-card">
      <?php if (!empty($course['thumbnail'])): ?>
      <img src="../assets/images/<?= htmlspecialchars($course['thumbnail']) ?>" alt="Course Thumbnail" style="width:100%;height:180px;object-fit:cover;border-radius:var(--radius-sm);margin-bottom:16px;border:1px solid var(--gray-border);">
      <?php else: ?>
      <div style="text-align:center;margin-bottom:16px;font-size:3rem"><?= $icons[$id % count($icons)] ?></div>
      <?php endif; ?>
      <div class="enroll-price">FREE</div>
      <div class="enroll-free">This course is completely free!</div>
      <?php if($is_enrolled): ?>
        <a href="course-learn.php?id=<?= $id ?>" class="btn btn-primary btn-lg" style="width:100%;text-align:center;border-radius:12px;margin-bottom:12px">
          <i class="fas fa-play"></i> Continue Learning
        </a>
        <p style="text-align:center;color:var(--success);font-weight:700"><i class="fas fa-check-circle"></i> You are enrolled!</p>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="enroll" value="1">
          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;border-radius:12px">
            <i class="fas fa-rocket"></i> Enroll for Free
          </button>
        </form>
      <?php endif; ?>
      <ul class="enroll-features" style="margin-top:20px">
        <li><i class="fas fa-infinity"></i> Full lifetime access</li>
        <li><i class="fas fa-layer-group"></i> <?= $total_units ?> units</li>
        <li><i class="fas fa-play-circle"></i> <?= $total_lessons ?> lessons</li>
        <li><i class="fas fa-certificate"></i> Certificate of completion</li>
        <li><i class="fas fa-mobile-alt"></i> Access on any device</li>
      </ul>
    </div>
  </div>
</div>

<script>
function toggleAccordion(el) {
  const body = el.nextElementSibling;
  const icon = el.querySelector('.fa-chevron-down');
  body.classList.toggle('open');
  icon.style.transform = body.classList.contains('open') ? 'rotate(180deg)' : '';
}
// Open first accordion
document.querySelectorAll('.accordion-header')[0]?.click();
</script>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
