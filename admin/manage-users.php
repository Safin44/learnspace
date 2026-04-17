<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// Handle block/unblock
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['type'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    $type = $_GET['type'];
    $status = $action === 'block' ? 'blocked' : 'active';
    $table = $type === 'instructor' ? 'instructors' : 'users';
    $stmt = $conn->prepare("UPDATE $table SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    header("Location: manage-users.php?msg=" . ($action === 'block' ? 'blocked' : 'unblocked'));
    exit;
}

$msg = $_GET['msg'] ?? '';
$users = $conn->query("SELECT *, 'learner' as role FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace" ></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="manage-users.php" class="active"><i class="fas fa-users"></i> Manage Users</a></li>
      <li><a href="manage-instructors.php"><i class="fas fa-chalkboard-teacher"></i> Manage Instructors</a></li>
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
    <div class="main-header">
      <h1><i class="fas fa-users"></i> Manage Learners</h1>
    </div>
    <div class="page-body">
      <?php if ($msg): ?>
      <div class="alert alert-success">User <?= htmlspecialchars($msg) ?> successfully.</div>
      <?php endif; ?>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>#</th><th>Name</th><th>Email</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php $i=1; while($u = $users->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="avatar"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
                  <?= htmlspecialchars($u['full_name']) ?>
                </div>
              </td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
              <td><span class="badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
              <td>
                <?php if($u['status']==='active'): ?>
                <a href="manage-users.php?action=block&id=<?= $u['id'] ?>&type=learner" class="btn btn-danger btn-sm" onclick="return confirm('Block this user?')"><i class="fas fa-ban"></i> Block</a>
                <?php else: ?>
                <a href="manage-users.php?action=unblock&id=<?= $u['id'] ?>&type=learner" class="btn btn-success btn-sm" onclick="return confirm('Unblock this user?')"><i class="fas fa-check"></i> Unblock</a>
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
