<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$success = '';

// Add/update admin comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_comment'])) {
    $mid     = intval($_POST['msg_id']);
    $comment = $conn->real_escape_string(trim($_POST['admin_comment'] ?? ''));
    $conn->query("UPDATE support_messages SET admin_comment='$comment' WHERE id=$mid");
    $success = "Comment saved.";
}

// Mark resolved (also save comment if present)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_msg'])) {
    $mid     = intval($_POST['msg_id']);
    $comment = $conn->real_escape_string(trim($_POST['admin_comment'] ?? ''));
    $conn->query("UPDATE support_messages SET status='resolved', admin_comment='$comment' WHERE id=$mid");
    $success = "Message marked as resolved.";
}

// Delete message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_msg'])) {
    $mid = intval($_POST['msg_id']);
    $conn->query("DELETE FROM support_messages WHERE id=$mid");
    $success = "Message deleted.";
}

$filter_type   = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search        = trim($_GET['search'] ?? '');

$where = [];
if ($filter_type)   $where[] = "sender_type='".$conn->real_escape_string($filter_type)."'";
if ($filter_status) $where[] = "status='".$conn->real_escape_string($filter_status)."'";
if ($search)        $where[] = "(sender_name LIKE '%".$conn->real_escape_string($search)."%' OR subject LIKE '%".$conn->real_escape_string($search)."%')";
$where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$msgs_q = $conn->query("SELECT * FROM support_messages $where_sql ORDER BY created_at DESC");
$msgs = [];
while ($r = $msgs_q->fetch_assoc()) $msgs[] = $r;

$open_count = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE status='open'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Messages - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.msg-card{background:white;border-radius:var(--radius);border:1px solid var(--gray-border);padding:20px;margin-bottom:14px;transition:var(--transition)}
.msg-card.open{border-left:4px solid var(--primary)}
.msg-card.resolved{border-left:4px solid #16a34a;opacity:.8}
.sender-badge-learner{background:#ede9fe;color:#7c3aed;padding:3px 10px;border-radius:50px;font-size:.75rem;font-weight:700}
.sender-badge-instructor{background:#dbeafe;color:#1d4ed8;padding:3px 10px;border-radius:50px;font-size:.75rem;font-weight:700}
.status-open{background:#fef9c3;color:#ca8a04;padding:3px 10px;border-radius:50px;font-size:.75rem;font-weight:700}
.status-resolved{background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:50px;font-size:.75rem;font-weight:700}
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:20px}
.filter-bar input,.filter-bar select{padding:9px 13px;border:1px solid var(--gray-border);border-radius:var(--radius-sm);font-size:.88rem;outline:none}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--primary)}
.msg-body{background:#f9fafb;border-radius:var(--radius-sm);padding:14px;margin:12px 0;font-size:.88rem;line-height:1.7;color:var(--black)}
.admin-comment-box{background:#fffbeb;border:1px solid #fcd34d;border-radius:var(--radius-sm);padding:14px;margin-top:12px}
.admin-comment-box label{display:block;font-weight:700;font-size:.8rem;color:#92400e;margin-bottom:6px}
.admin-comment-box textarea{width:100%;padding:9px 12px;border:1px solid #fcd34d;border-radius:var(--radius-sm);font-size:.85rem;font-family:inherit;resize:vertical;background:#fffef0;box-sizing:border-box}
.admin-comment-box textarea:focus{border-color:var(--primary);outline:none}
.admin-comment-saved{background:#fffbeb;border-left:3px solid #f59e0b;border-radius:var(--radius-sm);padding:10px 14px;margin-top:8px;font-size:.85rem;color:#78350f;font-style:italic}
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
      <li><a href="remove-courses.php"><i class="fas fa-trash-alt"></i> Remove Courses</a></li>
      <li><a href="contest.php"><i class="fas fa-trophy"></i> Arrange Contest</a></li>
      <li><a href="view-messages.php" class="active"><i class="fas fa-envelope"></i> View Messages
        <?php if($open_count>0): ?><span style="background:var(--primary);color:white;border-radius:50px;padding:2px 8px;font-size:.72rem;margin-left:4px"><?=$open_count?></span><?php endif; ?>
      </a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <div class="main-header">
      <h1><i class="fas fa-envelope" style="color:var(--primary)"></i> Support Messages
        <?php if($open_count>0): ?><span style="font-size:1rem;font-weight:700;background:#fee2e2;color:var(--primary);padding:4px 12px;border-radius:50px;margin-left:12px"><?=$open_count?> open</span><?php endif; ?>
      </h1>
    </div>
    <div class="page-body">
      <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=$success?></div><?php endif; ?>

      <!-- Filter bar -->
      <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="🔍 Search name or subject..." value="<?=htmlspecialchars($search)?>">
        <select name="type">
          <option value="">All Senders</option>
          <option value="learner" <?=$filter_type==='learner'?'selected':''?>>Learners</option>
          <option value="instructor" <?=$filter_type==='instructor'?'selected':''?>>Instructors</option>
        </select>
        <select name="status">
          <option value="">All Status</option>
          <option value="open" <?=$filter_status==='open'?'selected':''?>>Open</option>
          <option value="resolved" <?=$filter_status==='resolved'?'selected':''?>>Resolved</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        <a href="view-messages.php" class="btn btn-outline btn-sm">Reset</a>
      </form>

      <?php if(empty($msgs)): ?>
      <div style="text-align:center;padding:60px;color:var(--gray)">
        <i class="fas fa-inbox" style="font-size:3rem;display:block;margin-bottom:16px;opacity:.2"></i>
        <h3>No messages found</h3>
        <p>Support messages from learners and instructors will appear here.</p>
      </div>
      <?php else: ?>
      <?php foreach($msgs as $m): ?>
      <div class="msg-card <?=$m['status']?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:10px">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="avatar" style="background:var(--primary-bg);color:var(--primary)"><?=strtoupper(substr($m['sender_name'],0,1))?></div>
            <div>
              <div style="font-weight:700;font-size:.95rem"><?=htmlspecialchars($m['sender_name'])?></div>
              <div style="font-size:.78rem;color:var(--gray)"><?=htmlspecialchars($m['sender_email'])?> &nbsp;·&nbsp; <?=date('M d, Y H:i',strtotime($m['created_at']))?></div>
            </div>
            <span class="sender-badge-<?=$m['sender_type']?>"><?=ucfirst($m['sender_type'])?></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <span class="status-<?=$m['status']?>"><?=ucfirst($m['status'])?></span>
          </div>
        </div>

        <div style="font-weight:700;font-size:1rem;margin-bottom:4px"><?=htmlspecialchars($m['subject'])?></div>
        <div class="msg-body"><?=nl2br(htmlspecialchars($m['message']))?></div>

        <!-- Admin comment box -->
        <div class="admin-comment-box">
          <label><i class="fas fa-comment-dots"></i> Admin Feedback / Comment</label>
          <?php if(!empty($m['admin_comment'])): ?>
            <div class="admin-comment-saved"><i class="fas fa-quote-left" style="opacity:.4;margin-right:6px"></i><?=nl2br(htmlspecialchars($m['admin_comment']))?></div>
          <?php endif; ?>
          <form method="POST" style="margin-top:8px">
            <input type="hidden" name="msg_id" value="<?=$m['id']?>">
            <textarea name="admin_comment" rows="3" placeholder="Write your feedback or comment to the sender..."><?=htmlspecialchars($m['admin_comment']??'')?></textarea>
            <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
              <button type="submit" name="save_comment" class="btn btn-outline btn-sm"><i class="fas fa-save"></i> Save Comment</button>
              <?php if($m['status']==='open'): ?>
              <button type="submit" name="resolve_msg" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Save & Mark Resolved</button>
              <?php endif; ?>
            </div>
          </form>
        </div>
        <div style="margin-top:8px">
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this message?')">
            <input type="hidden" name="msg_id" value="<?=$m['id']?>">
            <button type="submit" name="delete_msg" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
