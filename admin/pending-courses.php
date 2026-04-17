<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$pending = $conn->query("SELECT c.*, i.full_name as instructor_name,
    COUNT(DISTINCT u.id) as unit_count
    FROM courses c
    JOIN instructors i ON c.instructor_id = i.id
    LEFT JOIN units u ON c.id = u.course_id
    WHERE c.status='pending'
    GROUP BY c.id
    ORDER BY c.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pending Courses - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a></li>
      <li><a href="manage-instructors.php"><i class="fas fa-chalkboard-teacher"></i> Manage Instructors</a></li>
      <li><a href="courses.php"><i class="fas fa-book"></i> Courses</a></li>
      <li><a href="pending-courses.php" class="active"><i class="fas fa-clock"></i> Pending Courses</a></li>
      <li><a href="remove-courses.php"><i class="fas fa-trash-alt"></i> Remove Courses</a></li>
      <li><a href="contest.php"><i class="fas fa-trophy"></i> Arrange Contest</a></li>
      <li><a href="contest-result.php"><i class="fas fa-medal"></i> Contest Results</a></li>
      <li><a href="manage-coding-problems.php"><i class="fas fa-code"></i> Manage Coding Problems</a></li>
      <li><a href="view-messages.php"><i class="fas fa-envelope"></i> View Messages</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header"><h1><i class="fas fa-clock"></i> Pending Course Approvals</h1></div>
    <div class="page-body">
      <?php if (isset($_GET['msg'])): ?>
      <div class="alert alert-success">Course <?= htmlspecialchars($_GET['msg']) ?> successfully.</div>
      <?php endif; ?>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>#</th><th>Course Title</th><th>Instructor</th><th>Units</th><th>Submitted</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php $i=1; while($c = $pending->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($c['title']) ?></strong><br><small style="color:var(--gray)"><?= substr(htmlspecialchars($c['description']),0,60) ?>...</small></td>
              <td><?= htmlspecialchars($c['instructor_name']) ?></td>
              <td><?= $c['unit_count'] ?> units</td>
              <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <a href="course-detail.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> Review</a>
                <a href="course-action.php?id=<?= $c['id'] ?>&action=approve" class="btn btn-success btn-sm" onclick="return confirm('Approve this course?')"><i class="fas fa-check"></i> Approve</a>
                <a href="course-action.php?id=<?= $c['id'] ?>&action=reject" class="btn btn-danger btn-sm" onclick="return confirm('Reject this course?')"><i class="fas fa-times"></i> Reject</a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if($i===1): ?>
            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gray)">
              <i class="fas fa-check-circle" style="font-size:2rem;color:var(--success);display:block;margin-bottom:12px"></i>
              No pending courses! All caught up.
            </td></tr>
            <?php endif; ?>
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
