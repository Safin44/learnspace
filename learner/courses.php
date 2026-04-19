<?php
session_start();
require_once '../includes/db.php';

$search = trim($_GET['search'] ?? '');
$user_id = $_SESSION['user_id'] ?? 0;

$where = "WHERE c.status='approved'";
$params = [];
$types = '';

if (!empty($search)) {
    $where .= " AND (c.title LIKE ? OR c.description LIKE ? OR i.full_name LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
    $types = 'sss';
}

if (!empty($params)) {
    $stmt = $conn->prepare("SELECT c.*, i.full_name as instructor_name,
        COALESCE(AVG(r.rating),0) as avg_rating,
        COUNT(DISTINCT e.id) as student_count,
        (SELECT COUNT(*) FROM enrollments WHERE user_id=? AND course_id=c.id) as is_enrolled
        FROM courses c
        JOIN instructors i ON c.instructor_id=i.id
        LEFT JOIN ratings r ON c.id=r.course_id
        LEFT JOIN enrollments e ON c.id=e.course_id
        $where
        GROUP BY c.id
        ORDER BY c.created_at DESC");
    $stmt->bind_param("i$types", $user_id, ...$params);
    $stmt->execute();
    $courses_result = $stmt->get_result();
} else {
    $stmt = $conn->prepare("SELECT c.*, i.full_name as instructor_name,
        COALESCE(AVG(r.rating),0) as avg_rating,
        COUNT(DISTINCT e.id) as student_count,
        (SELECT COUNT(*) FROM enrollments WHERE user_id=? AND course_id=c.id) as is_enrolled
        FROM courses c
        JOIN instructors i ON c.instructor_id=i.id
        LEFT JOIN ratings r ON c.id=r.course_id
        LEFT JOIN enrollments e ON c.id=e.course_id
        $where
        GROUP BY c.id
        ORDER BY c.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $courses_result = $stmt->get_result();
}

$courses = [];
while ($row = $courses_result->fetch_assoc()) $courses[] = $row;
$icons = ['💻','📊','🎨','📈','🔬','📱','🌐','🎯','🔧','📐','🧪','🏆'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Browse Courses - LearnSpace</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.search-bar-wrap {
  max-width: 600px;
  margin: 0 auto 40px;
  position: relative;
}
.search-bar-wrap input {
  width: 100%;
  padding: 14px 20px 14px 50px;
  border-radius: 50px;
  border: 2px solid var(--gray-border);
  font-size: 1rem;
  font-family: 'Nunito', sans-serif;
  transition: var(--transition);
  box-shadow: 0 4px 16px rgba(0,0,0,0.07);
}
.search-bar-wrap input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 4px 20px rgba(204,0,0,0.15);
}
.search-bar-wrap i {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray);
  font-size: 1.1rem;
}
.search-bar-wrap button {
  position: absolute;
  right: 6px;
  top: 6px;
  bottom: 6px;
  padding: 0 20px;
  border-radius: 50px;
  border: none;
  background: var(--primary);
  color: white;
  font-weight: 700;
  cursor: pointer;
  font-family: 'Nunito', sans-serif;
}
.hero-search {
  background: linear-gradient(135deg, #cc0000 0%, #990000 100%);
  padding: 50px 60px;
  text-align: center;
  color: white;
}
.hero-search h1 { font-size: 2.2rem; margin-bottom: 10px; }
.hero-search p { opacity: 0.85; margin-bottom: 28px; font-size: 1.05rem; }
.courses-section { padding: 40px 60px; }
.enrolled-tag { background: var(--success); color: white; font-size: 0.75rem; padding: 3px 10px; border-radius: 50px; font-weight: 700; }
</style>
</head>
<body>

<nav class="navbar">
  <a href="../index.php" class="navbar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></a>
  <ul class="nav-links">
    <li><a href="../index.php">Home</a></li>
    <li><a href="courses.php" class="active">Courses</a></li>
    <?php if(isset($_SESSION['user_id'])): ?>
    <li><a href="dashboard.php">My Learning</a></li>
    <?php endif; ?>
  </ul>
  <div class="nav-actions">
    <?php if(isset($_SESSION['user_id'])): ?>
      <a href="dashboard.php" class="btn btn-primary btn-sm">Dashboard</a>
      <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-outline">Login</a>
      <a href="register.php" class="btn btn-primary">Join Free</a>
    <?php endif; ?>
  </div>
</nav>

<div class="hero-search">
  <h1>🚀 Explore All Courses</h1>
  <p>Learn from expert instructors across technology, business, and creativity</p>
  <form method="GET" class="search-bar-wrap">
    <i class="fas fa-search"></i>
    <input type="text" name="search" placeholder="Search courses, instructors, topics..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Search</button>
  </form>
</div>

<div class="courses-section">
  <?php if (!empty($search)): ?>
  <p style="margin-bottom:20px;color:var(--gray)">
    <?= count($courses) ?> result(s) for "<strong><?= htmlspecialchars($search) ?></strong>"
    <a href="courses.php" style="color:var(--primary);margin-left:10px"><i class="fas fa-times"></i> Clear</a>
  </p>
  <?php endif; ?>

  <?php if (empty($courses)): ?>
  <div style="text-align:center;padding:80px;color:var(--gray)">
    <i class="fas fa-search" style="font-size:3rem;display:block;margin-bottom:16px;color:var(--gray-border)"></i>
    <h3>No courses found</h3>
    <p><?= !empty($search) ? 'Try a different search term.' : 'No approved courses available yet.' ?></p>
  </div>
  <?php else: ?>
  <div class="courses-grid">
    <?php foreach($courses as $i => $c): ?>
    <div class="course-card">
      <div class="course-card-thumb">
        <?php if($c['thumbnail']): ?>
          <img src="../assets/images/<?= htmlspecialchars($c['thumbnail']) ?>" alt="">
        <?php else: ?>
          <?= $icons[$i % count($icons)] ?>
        <?php endif; ?>
        <?php if($c['is_enrolled']): ?>
        <div style="position:absolute;top:10px;right:10px">
          <span class="enrolled-tag"><i class="fas fa-check"></i> Enrolled</span>
        </div>
        <?php endif; ?>
      </div>
      <div class="course-card-body">
        <div class="course-card-title"><?= htmlspecialchars($c['title']) ?></div>
        <div class="course-card-instructor"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['instructor_name']) ?></div>
        <p style="font-size:0.82rem;color:var(--gray);margin-bottom:10px;line-height:1.6">
          <?= htmlspecialchars(substr($c['description'],0,80)) ?>...
        </p>
        <div class="course-card-meta">
          <span class="stars">
            <?php $r=round($c['avg_rating']); for($s=1;$s<=5;$s++) echo $s<=$r?'★':'☆'; ?>
            <small style="color:var(--gray)">(<?= number_format($c['avg_rating'],1) ?>)</small>
          </span>
          <small style="color:var(--gray)"><i class="fas fa-users"></i> <?= $c['student_count'] ?></small>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px">
          <a href="course-detail.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center">Details</a>
          <?php if($c['is_enrolled']): ?>
            <a href="course-learn.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center"><i class="fas fa-play"></i> Continue</a>
          <?php else: ?>
            <a href="course-detail.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center"><i class="fas fa-rocket"></i> Enroll Free</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
