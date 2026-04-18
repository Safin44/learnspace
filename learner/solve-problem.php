<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];

$problem_id = intval($_GET['id'] ?? 0);

$problem = $conn->query("SELECT * FROM coding_problems WHERE id=$problem_id")->fetch_assoc();
if (!$problem) { header('Location: coding-problems.php'); exit; }

$completion = $conn->query("SELECT status FROM problem_completions WHERE problem_id=$problem_id AND user_id=$user_id")->fetch_assoc();
$is_solved = ($completion['status'] ?? '') === 'solved';

$_has_resolved = $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE sender_type='learner' AND sender_id=$user_id AND status='resolved'")->fetch_assoc()['c'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($problem['title']) ?> - Solve</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background:#f0f2f5; }
.back-btn { display:inline-flex; align-items:center; gap:8px; color:var(--gray); font-size:0.88rem; margin-bottom:16px; font-weight:600; text-decoration:none; }
.back-btn:hover { color:var(--primary); }
</style>
</head>
<body>

<div class="dashboard-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-brand"><img src="../assets/images/logo.png" alt="LearnSpace"></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="courses.php"><i class="fas fa-search"></i> Browse Courses</a></li>
      <li><a href="contests.php"><i class="fas fa-trophy"></i> Contests</a></li>
      <li><a href="contest-history.php"><i class="fas fa-history"></i> Contest History</a></li>
      <li><a href="coding-problems.php" class="active"><i class="fas fa-code"></i> Coding Problems</a></li>
      <li><a href="report-issue.php"><i class="fas fa-flag"></i> Report an Issue</a></li>
      <li><a href="certificates.php"><i class="fas fa-certificate"></i> My Certificates</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="logout.php" style="margin-top:20px"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- MAIN CONTENT -->
  <div class="main-content" style="padding:32px; display:flex; flex-direction:column;">
    <div>
        <a href="coding-problems.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Problems</a>
    </div>
    
    <div style="background:white; border-radius:var(--radius); border:1px solid var(--gray-border); padding:24px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 style="margin-bottom:8px; font-size:1.8rem;"><?= htmlspecialchars($problem['title']) ?></h1>
            <div style="display:flex; gap:16px; font-size:0.9rem; color:var(--gray);">
                <span><i class="fas fa-layer-group"></i> Difficulty: <strong style="text-transform:capitalize"><?= $problem['difficulty'] ?></strong></span>
                <span><i class="fas fa-code"></i> Language: <strong><?= $problem['coding_language'] === '62' ? 'Java' : ($problem['coding_language'] === '71' ? 'Python' : ($problem['coding_language'] === '54' ? 'C++' : 'Other')) ?></strong></span>
            </div>
            
            <div style="margin-top:20px; line-height:1.6; color:#333;">
                <h4 style="margin-bottom:6px">Problem Description</h4>
                <div style="white-space:pre-wrap; background:#f9fafb; padding:16px; border-radius:8px; border:1px solid #e5e7eb;"><?= htmlspecialchars($problem['description']) ?></div>
            </div>
        </div>
        <div id="status-badge">
            <?php if ($is_solved): ?>
                <span style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#059669,#10b981);color:white;padding:8px 18px;border-radius:50px;font-weight:700;font-size:0.9rem;box-shadow:0 2px 8px rgba(16,185,129,0.3);"><i class="fas fa-check-circle"></i> Solved</span>
            <?php else: ?>
                <span style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#ca8a04,#eab308);color:white;padding:8px 18px;border-radius:50px;font-weight:700;font-size:0.9rem;box-shadow:0 2px 8px rgba(234,179,8,0.3);"><i class="fas fa-code"></i> Unsolved</span>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div style="background:#1e1e1e; padding:20px; border-radius:var(--radius); border:1px solid #333; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
                <span style="color:#10b981; font-weight:bold; font-size:0.9rem;"><i class="fas fa-code"></i> Code Editor</span>
            </div>
            <textarea id="code_input" style="width:100%; height:400px; background:#2d2d2d; color:#e0e0e0; font-family:monospace; padding:12px; border:1px solid #444; border-radius:6px; resize:vertical; font-size:14px;"><?= htmlspecialchars($problem['coding_boilerplate'] ?? '') ?></textarea>
            <div style="margin-top:16px;">
                <button id="run_btn" class="btn" style="background:#10b981; color:white; font-weight:bold; padding:10px 24px;">
                    <i class="fas fa-play"></i> Run & Submit
                </button>
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:20px;">
            <?php if (!empty($problem['coding_expected_input'])): ?>
            <div style="background:white; padding:20px; border-radius:var(--radius); border:1px solid var(--gray-border);">
                <span style="color:var(--gray); font-size:0.85rem; font-weight:bold; text-transform:uppercase;">Expected Standard Input (stdin)</span>
                <div style="background:#f8f9fa; padding:12px; margin-top:10px; border-radius:6px; font-family:monospace; white-space:pre-wrap; border:1px solid #e2e8f0;"><?= htmlspecialchars($problem['coding_expected_input']) ?></div>
            </div>
            <?php endif; ?>
            <div style="background:white; padding:20px; border-radius:var(--radius); border:1px solid var(--gray-border);">
                <span style="color:var(--gray); font-size:0.85rem; font-weight:bold; text-transform:uppercase;">Expected Final Output</span>
                <div style="background:#f8f9fa; padding:12px; margin-top:10px; border-radius:6px; font-family:monospace; white-space:pre-wrap; border:1px solid #e2e8f0;"><?= htmlspecialchars($problem['coding_expected_output']) ?></div>
            </div>
            <div style="background:white; padding:20px; border-radius:var(--radius); border:1px solid var(--gray-border); flex-grow:1;">
                <span style="color:var(--gray); font-size:0.85rem; font-weight:bold; text-transform:uppercase;">Console execution</span>
                <div id="console_output" style="background:#1e1e1e; color:#d4d4d4; padding:12px; margin-top:10px; border-radius:6px; font-family:monospace; min-height:150px; white-space:pre-wrap;">Ready to execute.</div>
            </div>
        </div>
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
    
    fetch('../ajax/execute-problem.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ problem_id: <?= $problem['id'] ?>, source_code: code })
    }).then(res => res.json()).then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run & Submit';
        
        if (data.error) {
            consoleOut.textContent = data.error;
            consoleOut.style.color = '#ef4444';
        } else {
            consoleOut.textContent = data.output || '(No output)';
            if (data.passed) {
                consoleOut.innerHTML += '\n\n<span style="color:#10b981; font-weight:bold;">[SUCCESS] Passed! Great job!</span>';
                
                let sBadge = document.getElementById('status-badge');
                if(sBadge) {
                    sBadge.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#059669,#10b981);color:white;padding:8px 18px;border-radius:50px;font-weight:700;font-size:0.9rem;box-shadow:0 2px 8px rgba(16,185,129,0.3);"><i class="fas fa-check-circle"></i> Solved</span>';
                }
            } else {
                consoleOut.innerHTML += '\n\n<span style="color:#ef4444; font-weight:bold;">[FAILED] Output did not match exactly. Status: ' + data.status + '</span>';
            }
        }
    }).catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run & Submit';
        consoleOut.innerHTML = 'Network error. Try again.';
        consoleOut.style.color = '#ef4444';
    });
});
</script>
<script src='https://cdn.jotfor.ms/agent/embedjs/019d85b564bd7b53bf17ecb93621ce83ef1b/embed.js'>
</script>
</body>
</html>
