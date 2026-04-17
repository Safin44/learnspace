<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['instructor_id'])) { header('Location: login.php'); exit; }
$ins_id = $_SESSION['instructor_id'];
$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='instructor' AND sender_id=$ins_id AND status='resolved'")->fetch_assoc()['c'] > 0;

$courses = $conn->query("SELECT c.*, COUNT(DISTINCT e.id) as student_count, COALESCE(AVG(r.rating),0) as avg_rating
    FROM courses c
    LEFT JOIN enrollments e ON c.id=e.course_id
    LEFT JOIN ratings r ON c.id=r.course_id
    WHERE c.instructor_id=$ins_id
    GROUP BY c.id
    ORDER BY c.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Courses - Instructor</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="add-course.php"><i class="fas fa-plus-circle"></i> Add Course</a></li>
      <li><a href="my-courses.php" class="active"><i class="fas fa-book"></i> My Courses</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue<?php if($_has_resolved): ?> <span title="One or more issues resolved" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#16a34a;color:white;border-radius:50%;font-size:.7rem;font-weight:900;margin-left:4px">!</span><?php endif; ?></a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-book"></i> My Courses</h1>
      <a href="add-course.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add New Course</a>
    </div>
    <div class="page-body">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Course Title</th><th>Status</th><th>Students</th><th>Rating</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody>
            <?php $i=1; while($c=$courses->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($c['title']) ?></strong></td>
              <td><span class="badge badge-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>
              <td><i class="fas fa-users" style="color:var(--primary)"></i> <?= $c['student_count'] ?></td>
              <td><span class="stars">★</span> <?= number_format($c['avg_rating'],1) ?></td>
              <td><?= date('M d, Y',strtotime($c['created_at'])) ?></td>
              <td><a href="course-view.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
