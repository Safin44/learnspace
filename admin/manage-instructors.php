<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = $_GET['action'] === 'block' ? 'blocked' : 'active';
    $stmt = $conn->prepare("UPDATE instructors SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    header("Location: manage-instructors.php?msg=" . ($_GET['action'] === 'block' ? 'blocked' : 'unblocked'));
    exit;
}

$msg = $_GET['msg'] ?? '';
$instructors = $conn->query("SELECT i.*, COUNT(DISTINCT c.id) as course_count FROM instructors i LEFT JOIN courses c ON i.id=c.instructor_id GROUP BY i.id ORDER BY i.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Instructors - Admin</title>
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
      <li><a href="manage-instructors.php" class="active"><i class="fas fa-chalkboard-teacher"></i> Manage Instructors</a></li>
      <li><a href="courses.php"><i class="fas fa-book"></i> Courses</a></li>
      <li><a href="pending-courses.php"><i class="fas fa-clock"></i> Pending Courses</a></li>
      <li><a href="remove-courses.php"><i class="fas fa-trash-alt"></i> Remove Courses</a></li>
      <li><a href="contest.php"><i class="fas fa-trophy"></i> Arrange Contest</a></li>
      <li><a href="contest-result.php"><i class="fas fa-medal"></i> Contest Results</a></li>
      <li><a href="manage-coding-problems.php"><i class="fas fa-code"></i> Manage Coding Problems</a></li>
      <li><a href="view-messages.php"><i class="fas fa-envelope"></i> View Messages</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header"><h1><i class="fas fa-chalkboard-teacher"></i> Manage Instructors</h1></div>
    <div class="page-body">
      <?php if ($msg): ?><div class="alert alert-success">Instructor <?= htmlspecialchars($msg) ?> successfully.</div><?php endif; ?>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>#</th><th>Name</th><th>Email</th><th>Courses</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php $i=1; while($ins = $instructors->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="avatar" style="background:#dbeafe;color:var(--info)"><?= strtoupper(substr($ins['full_name'],0,1)) ?></div>
                  <?= htmlspecialchars($ins['full_name']) ?>
                </div>
              </td>
              <td><?= htmlspecialchars($ins['email']) ?></td>
              <td><span class="badge badge-approved"><?= $ins['course_count'] ?> courses</span></td>
              <td><?= date('M d, Y', strtotime($ins['created_at'])) ?></td>
              <td><span class="badge badge-<?= $ins['status'] ?>"><?= ucfirst($ins['status']) ?></span></td>
              <td>
                <?php if($ins['status']==='active'): ?>
                <a href="manage-instructors.php?action=block&id=<?= $ins['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Block?')"><i class="fas fa-ban"></i> Block</a>
                <?php else: ?>
                <a href="manage-instructors.php?action=unblock&id=<?= $ins['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Unblock</a>
                <?php endif; ?>
              </td>
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
