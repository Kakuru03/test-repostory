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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit;
}

$staff_id = intval($_GET['id']);

$database = new Database();
$conn = $database->getConnection();

$sql = "SELECT s.*, u.username, u.first_name, u.last_name, u.email, u.phone,
               CONCAT(sup.first_name, ' ', sup.last_name) as supervisor_name
        FROM staff s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN staff sup_s ON s.supervisor_id = sup_s.id
        LEFT JOIN users sup ON sup_s.user_id = sup.id
        WHERE s.id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$staff_id]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if ($staff) {
    echo json_encode(['success' => true, 'staff' => $staff]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Staff member not found']);
}
?>
