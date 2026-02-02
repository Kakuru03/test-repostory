<?php
session_start();
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!HelperFunctions::isLoggedIn() || !HelperFunctions::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$staff_id = intval($_POST['staff_id']);
$staff_id_value = Database::sanitize($_POST['staff_id_value']);
$department = Database::sanitize($_POST['department']);
$position = Database::sanitize($_POST['position']);
$employment_date = Database::sanitize($_POST['employment_date']);
$salary_grade = Database::sanitize($_POST['salary_grade']);
$supervisor_id = !empty($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : null;

// Validate inputs
if (empty($staff_id_value) || empty($department) || empty($position)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

// Check if staff ID already exists (excluding current staff)
$check_sql = "SELECT id FROM staff WHERE staff_id = ? AND id != ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->execute([$staff_id_value, $staff_id]);

if ($check_stmt->rowCount() > 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Staff ID already exists. Please use a different ID.']);
    exit;
}

// Update staff record
$sql = "UPDATE staff SET staff_id = ?, department = ?, position = ?, employment_date = ?, salary_grade = ?, supervisor_id = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt->execute([$staff_id_value, $department, $position, $employment_date, $salary_grade, $supervisor_id, $staff_id])) {
    HelperFunctions::logActivity($_SESSION['user_id'], 'staff_updated', 'Updated staff member: ' . $staff_id_value);
    echo json_encode(['success' => true, 'message' => 'Staff member updated successfully!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update staff member. Please try again.']);
}
?>
