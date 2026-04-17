<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$lesson_id = intval($data['lesson_id'] ?? 0);
$progress = intval($data['progress'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($lesson_id <= 0 || $progress < 0 || $progress > 100) {
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$is_completed = ($progress >= 80) ? 1 : 0;

$stmt = $conn->prepare("INSERT INTO lesson_completions (user_id, lesson_id, progress, is_completed) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE progress=GREATEST(progress, ?), is_completed=GREATEST(is_completed, ?)");
$stmt->bind_param("iiiiii", $user_id, $lesson_id, $progress, $is_completed, $progress, $is_completed);
$stmt->execute();

if ($is_completed) {
    $u_info = $conn->query("SELECT last_login_date, current_streak FROM users WHERE id=$user_id")->fetch_assoc();
    $today = date('Y-m-d');
    if (($u_info['last_login_date'] ?? null) !== $today) {
        $streak = $u_info['current_streak'] ?? 0;
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $new_streak = (($u_info['last_login_date'] ?? null) === $yesterday) ? ($streak + 1) : 1;
        $conn->query("UPDATE users SET last_login_date='$today', current_streak=$new_streak WHERE id=$user_id");
    }
}

echo json_encode(['success' => true, 'is_completed' => $is_completed]);
?>
