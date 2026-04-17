<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$course_id = intval($data['course_id'] ?? 0);
$rating = intval($data['rating'] ?? 0);
$review = trim($data['review'] ?? '');
$user_id = $_SESSION['user_id'];

if ($course_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO ratings (user_id, course_id, rating, review) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating=?, review=?");
$stmt->bind_param("iiisis", $user_id, $course_id, $rating, $review, $rating, $review);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>
