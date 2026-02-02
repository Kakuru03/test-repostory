<?php
require_once '../includes/auth_check.php';
require_once '../includes/database.php';

if (!HelperFunctions::isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$message_id = $_GET['id'] ?? 0;

if (!$message_id) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Message ID required']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$sql = "SELECT sm.*, u.username, u.first_name, u.last_name, u.email, u.phone,
               admin.first_name as admin_first, admin.last_name as admin_last
        FROM support_messages sm
        JOIN users u ON sm.user_id = u.id
        LEFT JOIN users admin ON sm.replied_by = admin.id
        WHERE sm.id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$message_id]);
$message = $stmt->fetch();

if ($message) {
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
}
?>