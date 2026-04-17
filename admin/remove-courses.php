<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $cid = intval($_POST['course_id']);
    $conn->query("DELETE FROM courses WHERE id=$cid");
    $success = "Course removed successfully.";
}

// Search/filter
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$where = [];
if ($search) $where[] = "(c.title LIKE '%" . $conn->real_escape_string($search) . "%' OR i.full_name LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($filter_status) $where[] = "c.status='" . $conn->real_escape_string($filter_status) . "'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$courses_q = $conn->query("SELECT c.*, i.full_name as instructor_name,
    COUNT(DISTINCT e.id) as enrollment_count
    FROM courses c
    JOIN instructors i ON c.instructor_id = i.id
    LEFT JOIN enrollments e ON c.id = e.course_id
    $where_sql
    GROUP BY c.id
    ORDER BY c.created_at DESC");
$courses = [];
while ($row = $courses_q->fetch_assoc()) $courses[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Remove Courses - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.danger-badge { background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:50px;font-size:0.75rem;font-weight:700; }
.filter-bar { display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;align-items:center; }
.filter-bar input, .filter-bar select { padding:9px 14px;border:1px solid var(--gray-border);border-radius:var(--radius-sm);font-size:0.88rem;outline:none; }
.filter-bar input:focus, .filter-bar select:focus { border-color:var(--primary); }
.enrollment-count { background:#f0fdf4;color:#16a34a;padding:3px 8px;border-radius:50px;font-size:0.75rem;font-weight:700; }
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
      <li><a href="remove-courses.php" class="active"><i class="fas fa-trash-alt"></i> Remove Courses</a></li>
      <li><a href="contest.php"><i class="fas fa-trophy"></i> Arrange Contest</a></li>
      <li><a href="contest-result.php"><i class="fas fa-medal"></i> Contest Results</a></li>
      <li><a href="manage-coding-problems.php"><i class="fas fa-code"></i> Manage Coding Problems</a></li>
      <li><a href="view-messages.php"><i class="fas fa-envelope"></i> View Messages</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-trash-alt" style="color:var(--primary)"></i> Remove Courses</h1>
    </div>
    <div class="page-body">
      <?php if (isset($success)): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
      <?php endif; ?>

      <div class="card" style="margin-bottom:24px;padding:20px">
        <form method="GET" class="filter-bar">
          <input type="text" name="search" placeholder="🔍 Search course or instructor..." value="<?= htmlspecialchars($search) ?>">
          <select name="status">
            <option value="">All Status</option>
            <option value="approved" <?= $filter_status==='approved'?'selected':'' ?>>Approved</option>
            <option value="pending" <?= $filter_status==='pending'?'selected':'' ?>>Pending</option>
            <option value="rejected" <?= $filter_status==='rejected'?'selected':'' ?>>Rejected</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
          <a href="remove-courses.php" class="btn btn-outline btn-sm">Reset</a>
        </form>
      </div>

      <div class="card">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Course Title</th>
                <th>Instructor</th>
                <th>Status</th>
                <th>Enrollments</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($courses)): ?>
              <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray)">No courses found.</td></tr>
              <?php else: ?>
              <?php foreach ($courses as $i => $c): ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($c['title']) ?></strong></td>
                <td><?= htmlspecialchars($c['instructor_name']) ?></td>
                <td>
                  <?php
                  $badges = ['approved'=>'badge-approved','pending'=>'badge-pending','rejected'=>'badge-rejected'];
                  $badge = $badges[$c['status']] ?? 'badge-pending';
                  ?>
                  <span class="badge <?= $badge ?>"><?= ucfirst($c['status']) ?></span>
                </td>
                <td><span class="enrollment-count"><i class="fas fa-users"></i> <?= $c['enrollment_count'] ?></span></td>
                <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                <td>
                  <form method="POST" style="display:inline" onsubmit="return confirm('⚠️ Are you sure you want to permanently delete \"<?= htmlspecialchars(addslashes($c['title'])) ?>\"?\n\nThis will also remove all enrollments and related data. This action CANNOT be undone!')">
                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                    <button type="submit" name="delete_course" class="btn btn-danger btn-sm">
                      <i class="fas fa-trash"></i> Remove
                    </button>
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
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
