<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$problem_id = intval($data['problem_id'] ?? 0);
$source_code = $data['source_code'] ?? '';

if (!$problem_id || empty($source_code)) {
    echo json_encode(['error' => 'Missing data']);
    exit;
}

// Fetch problem details
$stmt = $conn->prepare("SELECT coding_language, coding_expected_input, coding_expected_output FROM coding_problems WHERE id = ?");
$stmt->bind_param("i", $problem_id);
$stmt->execute();
$problem = $stmt->get_result()->fetch_assoc();

if (!$problem) {
    echo json_encode(['error' => 'Coding problem not found']);
    exit;
}

$language_id = intval($problem['coding_language']);
$expected_input = trim($problem['coding_expected_input'] ?? '');
$expected_output = trim($problem['coding_expected_output']);

// Log attempt
$conn->query("INSERT IGNORE INTO problem_completions (user_id, problem_id, status) VALUES ($user_id, $problem_id, 'attempted')");

// Call Judge0 API
$url = 'https://ce.judge0.com/submissions?base64_encoded=false&wait=true';
$payload = json_encode([
    'source_code' => $source_code,
    'language_id' => $language_id,
    'stdin' => $expected_input
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['error' => 'Code execution server error. ' . $error]);
    exit;
}

$result = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($result['status'])) {
    echo json_encode(['error' => 'Invalid response from execution server.']);
    exit;
}

$status_id = $result['status']['id'] ?? 0;
$output = '';

if (isset($result['compile_output']) && !empty($result['compile_output'])) {
    $output = trim($result['compile_output']);
} elseif (isset($result['stderr']) && !empty($result['stderr'])) {
    $output = trim($result['stderr']);
} elseif (isset($result['stdout']) && !empty($result['stdout'])) {
    $output = trim($result['stdout']);
} elseif ($status_id === 3) {
    $output = trim($result['stdout'] ?? '');
} else {
    $output = 'Execution failed or timed out.';
}

$passed = false;
// Exact match ignoring trailing whitespaces and carriage returns
$norm_output = str_replace("\r", "", $output);
$norm_expected = str_replace("\r", "", $expected_output);

if ($status_id === 3 && $norm_output === $norm_expected) {
    $passed = true;
}

if ($passed) {
    // Mark as solved
    $conn->query("UPDATE problem_completions SET status='solved', completed_at=NOW() WHERE user_id=$user_id AND problem_id=$problem_id");
    
    // Update streak optionally
    $u_info = $conn->query("SELECT last_login_date, current_streak FROM users WHERE id=$user_id")->fetch_assoc();
    $today = date('Y-m-d');
    if (($u_info['last_login_date'] ?? null) !== $today) {
        $streak = $u_info['current_streak'] ?? 0;
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $new_streak = (($u_info['last_login_date'] ?? null) === $yesterday) ? ($streak + 1) : 1;
        $conn->query("UPDATE users SET last_login_date='$today', current_streak=$new_streak WHERE id=$user_id");
    }
}

echo json_encode([
    'success' => true,
    'passed' => $passed,
    'output' => $output,
    'expected' => $expected_output,
    'status' => $result['status']['description'] ?? 'Unknown'
]);
