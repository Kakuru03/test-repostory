<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

$type = $_GET['type'] ?? 'users';
$filename = "mbarara_council_{$type}_" . date('Y-m-d') . ".csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

switch ($type) {
    case 'users':
        exportUsers($conn, $output);
        break;
    case 'staff':
        exportStaff($conn, $output);
        break;
    case 'applications':
        exportApplications($conn, $output);
        break;
    case 'complaints':
        exportComplaints($conn, $output);
        break;
    default:
        fputcsv($output, ['Error', 'Invalid export type']);
}

fclose($output);
exit;

function exportUsers($conn, $output) {
    fputcsv($output, [
        'ID', 'Username', 'First Name', 'Last Name', 'Email', 'Phone', 
        'Profession', 'User Type', 'Status', 'Registered Date'
    ]);

    $sql = "SELECT * FROM users WHERE user_type = 'public' ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['username'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'] ?? 'N/A',
            $row['profession'] ?? 'N/A',
            $row['user_type'],
            $row['status'],
            $row['created_at']
        ]);
    }
}

function exportStaff($conn, $output) {
    fputcsv($output, [
        'Staff ID', 'Name', 'Username', 'Email', 'Department', 'Position',
        'Employment Date', 'Salary Grade', 'Status'
    ]);

    $sql = "SELECT s.*, u.username, u.first_name, u.last_name, u.email 
            FROM staff s 
            JOIN users u ON s.user_id = u.id 
            ORDER BY s.is_active DESC, u.first_name, u.last_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['staff_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['username'],
            $row['email'],
            $row['department'],
            $row['position'],
            $row['employment_date'] ?? 'N/A',
            $row['salary_grade'] ?? 'N/A',
            $row['is_active'] ? 'Active' : 'Inactive'
        ]);
    }
}

function exportApplications($conn, $output) {
    $app_type = $_GET['app_type'] ?? 'internship';
    
    if ($app_type === 'internship') {
        fputcsv($output, [
            'ID', 'Applicant Name', 'Department', 'Duration (Months)', 
            'Start Date', 'Skills', 'Status', 'Applied Date'
        ]);

        $sql = "SELECT ia.*, u.first_name, u.last_name 
                FROM internship_applications ia 
                JOIN users u ON ia.user_id = u.id 
                ORDER BY ia.applied_at DESC";
    } else {
        fputcsv($output, [
            'ID', 'Applicant Name', 'Job Title', 'Department', 
            'Expected Salary', 'Years Experience', 'Status', 'Applied Date'
        ]);

        $sql = "SELECT ja.*, u.first_name, u.last_name 
                FROM job_applications ja 
                JOIN users u ON ja.user_id = u.id 
                ORDER BY ja.applied_at DESC";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($app_type === 'internship') {
            fputcsv($output, [
                $row['id'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['department'],
                $row['duration_months'],
                $row['start_date'],
                $row['skills'] ?? 'N/A',
                $row['status'],
                $row['applied_at']
            ]);
        } else {
            fputcsv($output, [
                $row['id'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['job_title'],
                $row['department'],
                $row['expected_salary'] ?? 'N/A',
                $row['years_experience'],
                $row['status'],
                $row['applied_at']
            ]);
        }
    }
}

function exportComplaints($conn, $output) {
    fputcsv($output, [
        'ID', 'Title', 'Category', 'Priority', 'Status', 
        'Complainant Name', 'Location', 'Submitted Date', 'Assigned To'
    ]);

    $sql = "SELECT c.*, u.first_name, u.last_name, s.first_name as assigned_first, s.last_name as assigned_last
            FROM complaints c 
            JOIN users u ON c.user_id = u.id 
            LEFT JOIN staff st ON c.assigned_to = st.id 
            LEFT JOIN users s ON st.user_id = s.id 
            ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $assigned_to = $row['assigned_first'] ? 
            $row['assigned_first'] . ' ' . $row['assigned_last'] : 'Not Assigned';
            
        fputcsv($output, [
            $row['id'],
            $row['title'],
            $row['category'],
            $row['priority'],
            $row['status'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['location'] ?? 'N/A',
            $row['created_at'],
            $assigned_to
        ]);
    }
}
?>