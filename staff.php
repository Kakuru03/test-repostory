<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}
$page_title = "Manage Staff";
include '../includes/header.php';

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';

// Handle staff actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $staff_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    switch ($action) {
        case 'activate':
            $sql = "UPDATE staff SET is_active = TRUE WHERE id = ?";
            $message = "Staff activated successfully.";
            break;
        case 'deactivate':
            $sql = "UPDATE staff SET is_active = FALSE WHERE id = ?";
            $message = "Staff deactivated successfully.";
            break;
        case 'delete':
            // Get user_id first
            $user_sql = "SELECT user_id FROM staff WHERE id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->execute([$staff_id]);
            $staff_user = $user_stmt->fetch();
            
            if ($staff_user) {
                // Delete from staff table
                $sql = "DELETE FROM staff WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt->execute([$staff_id])) {
                    // Also update user type
                    $update_user_sql = "UPDATE users SET user_type = 'public' WHERE id = ?";
                    $update_user_stmt = $conn->prepare($update_user_sql);
                    $update_user_stmt->execute([$staff_user['user_id']]);
                    $message = "Staff member removed successfully.";
                }
            }
            break;
        default:
            $message = "Invalid action.";
    }
    
    if (isset($sql) && $action !== 'delete') {
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$staff_id])) {
            $success = $message;
            HelperFunctions::logActivity($_SESSION['user_id'], 'staff_management', $message . ' Staff ID: ' . $staff_id);
        } else {
            $error = "Failed to perform action.";
        }
    } elseif ($action === 'delete' && isset($message)) {
        $success = $message;
        HelperFunctions::logActivity($_SESSION['user_id'], 'staff_management', $message . ' Staff ID: ' . $staff_id);
    }
}

// Handle add staff form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $user_id = intval($_POST['user_id']);
    $staff_id = Database::sanitize($_POST['staff_id']);
    $department = Database::sanitize($_POST['department']);
    $position = Database::sanitize($_POST['position']);
    $employment_date = Database::sanitize($_POST['employment_date']);
    $salary_grade = Database::sanitize($_POST['salary_grade']);
    $supervisor_id = !empty($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : null;

    // Validate inputs
    if (empty($staff_id) || empty($department) || empty($position)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if staff ID already exists
        $check_sql = "SELECT id FROM staff WHERE staff_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$staff_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = 'Staff ID already exists. Please use a different ID.';
        } else {
            // Check if user is already staff
            $check_user_sql = "SELECT id FROM staff WHERE user_id = ?";
            $check_user_stmt = $conn->prepare($check_user_sql);
            $check_user_stmt->execute([$user_id]);
            
            if ($check_user_stmt->rowCount() > 0) {
                $error = 'This user is already registered as staff.';
            } else {
                // Insert staff record
                $sql = "INSERT INTO staff (user_id, staff_id, department, position, employment_date, salary_grade, supervisor_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                if ($stmt->execute([$user_id, $staff_id, $department, $position, $employment_date, $salary_grade, $supervisor_id])) {
                    // Update user type to staff
                    $update_sql = "UPDATE users SET user_type = 'staff' WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->execute([$user_id]);
                    
                    $success = 'Staff member added successfully!';
                    HelperFunctions::logActivity($_SESSION['user_id'], 'staff_added', 'Added staff member: ' . $staff_id);
                    
                    // Clear form
                    $_POST = array();
                } else {
                    $error = 'Failed to add staff member. Please try again.';
                }
            }
        }
    }
}

// Get all staff with user details - FIXED QUERY
$staff_sql = "SELECT s.*, u.username, u.first_name, u.last_name, u.email, u.phone,
                     sup.first_name as sup_first_name, sup.last_name as sup_last_name
              FROM staff s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN staff sup_s ON s.supervisor_id = sup_s.id
              LEFT JOIN users sup ON sup_s.user_id = sup.id
              ORDER BY s.is_active DESC, u.created_at DESC";
$staff_stmt = $conn->prepare($staff_sql);
$staff_stmt->execute();
$staff_members = $staff_stmt->fetchAll();

// Get departments for dropdown
$dept_sql = "SELECT name FROM departments WHERE is_active = TRUE";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll();

// Get available users for staff enrollment
$users_sql = "SELECT id, username, first_name, last_name, email 
              FROM users 
              WHERE user_type = 'public' AND status = 'active' 
              ORDER BY first_name, last_name";
$users_stmt = $conn->prepare($users_sql);
$users_stmt->execute();
$available_users = $users_stmt->fetchAll();

// Get supervisors for dropdown
$supervisors_sql = "SELECT s.id, u.first_name, u.last_name, u.username 
                    FROM staff s 
                    JOIN users u ON s.user_id = u.id 
                    WHERE s.is_active = TRUE 
                    ORDER BY u.first_name, u.last_name";
$supervisors_stmt = $conn->prepare($supervisors_sql);
$supervisors_stmt->execute();
$supervisors = $supervisors_stmt->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-user-tie me-2"></i>Manage Staff Members</h4>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="fas fa-user-plus me-1"></i>Add Staff
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Staff Members Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Employment Date</th>
                                    <th>Supervisor</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($staff_members) > 0): ?>
                                    <?php foreach ($staff_members as $staff): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($staff['staff_id']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($staff['username']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($staff['department']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($staff['position']); ?></td>
                                        <td>
                                            <?php echo $staff['employment_date'] ? date('M j, Y', strtotime($staff['employment_date'])) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php if ($staff['sup_first_name']): ?>
                                                <?php echo htmlspecialchars($staff['sup_first_name'] . ' ' . $staff['sup_last_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $staff['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewStaff(<?php echo $staff['id']; ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($staff['is_active']): ?>
                                                    <a href="?action=deactivate&id=<?php echo $staff['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Deactivate">
                                                        <i class="fas fa-pause"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?action=activate&id=<?php echo $staff['id']; ?>" 
                                                       class="btn btn-outline-success" title="Activate">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-outline-info" 
                                                        onclick="editStaff(<?php echo $staff['id']; ?>)"
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <a href="?action=delete&id=<?php echo $staff['id']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to remove this staff member? This action cannot be undone.')"
                                                   title="Remove">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Staff Members</h5>
                                            <p class="text-muted">No staff members have been enrolled yet.</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                                <i class="fas fa-user-plus me-1"></i>Add First Staff Member
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Statistics -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo count($staff_members); ?></h4>
                            <p class="mb-0">Total Staff</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-tie fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <?php
                            $active_staff_sql = "SELECT COUNT(*) as count FROM staff WHERE is_active = TRUE";
                            $active_staff_stmt = $conn->prepare($active_staff_sql);
                            $active_staff_stmt->execute();
                            $active_staff = $active_staff_stmt->fetch()['count'];
                            ?>
                            <h4><?php echo $active_staff; ?></h4>
                            <p class="mb-0">Active Staff</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <?php
                            $inactive_staff_sql = "SELECT COUNT(*) as count FROM staff WHERE is_active = FALSE";
                            $inactive_staff_stmt = $conn->prepare($inactive_staff_sql);
                            $inactive_staff_stmt->execute();
                            $inactive_staff = $inactive_staff_stmt->fetch()['count'];
                            ?>
                            <h4><?php echo $inactive_staff; ?></h4>
                            <p class="mb-0">Inactive Staff</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-slash fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <?php
                            $departments_count_sql = "SELECT COUNT(DISTINCT department) as count FROM staff WHERE is_active = TRUE";
                            $departments_count_stmt = $conn->prepare($departments_count_sql);
                            $departments_count_stmt->execute();
                            $departments_count = $departments_count_stmt->fetch()['count'];
                            ?>
                            <h4><?php echo $departments_count; ?></h4>
                            <p class="mb-0">Departments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-building fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Staff Modal -->
<div class="modal fade" id="viewStaffModal" tabindex="-1" aria-labelledby="viewStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewStaffModalLabel">
                    <i class="fas fa-eye me-2"></i>Staff Member Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Personal Information</h6>
                        <div class="mb-2"><strong>Staff ID:</strong> <span id="viewStaffId"></span></div>
                        <div class="mb-2"><strong>Name:</strong> <span id="viewStaffName"></span></div>
                        <div class="mb-2"><strong>Username:</strong> <span id="viewStaffUsername"></span></div>
                        <div class="mb-2"><strong>Email:</strong> <span id="viewStaffEmail"></span></div>
                        <div class="mb-2"><strong>Phone:</strong> <span id="viewStaffPhone"></span></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Employment Information</h6>
                        <div class="mb-2"><strong>Department:</strong> <span id="viewStaffDepartment"></span></div>
                        <div class="mb-2"><strong>Position:</strong> <span id="viewStaffPosition"></span></div>
                        <div class="mb-2"><strong>Employment Date:</strong> <span id="viewStaffEmploymentDate"></span></div>
                        <div class="mb-2"><strong>Salary Grade:</strong> <span id="viewStaffSalaryGrade"></span></div>
                        <div class="mb-2"><strong>Supervisor:</strong> <span id="viewStaffSupervisor"></span></div>
                        <div class="mb-2"><strong>Status:</strong> <span id="viewStaffStatus" class="badge"></span></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editStaffModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Staff Member
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editStaffForm">
                <div class="modal-body">
                    <input type="hidden" id="editStaffId" name="staff_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editStaffStaffId" class="form-label">Staff ID *</label>
                            <input type="text" class="form-control" id="editStaffStaffId" name="staff_id_value" required>
                            <small class="form-text text-muted">Unique identifier for the staff member.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editStaffDepartment" class="form-label">Department *</label>
                            <select class="form-select" id="editStaffDepartment" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['name']); ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editStaffPosition" class="form-label">Position *</label>
                            <input type="text" class="form-control" id="editStaffPosition" name="position" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editStaffEmploymentDate" class="form-label">Employment Date</label>
                            <input type="date" class="form-control" id="editStaffEmploymentDate" name="employment_date">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editStaffSalaryGrade" class="form-label">Salary Grade</label>
                            <input type="text" class="form-control" id="editStaffSalaryGrade" name="salary_grade">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editStaffSupervisor" class="form-label">Supervisor (Optional)</label>
                            <select class="form-select" id="editStaffSupervisor" name="supervisor_id">
                                <option value="">No Supervisor</option>
                                <?php foreach ($supervisors as $supervisor): ?>
                                    <option value="<?php echo $supervisor['id']; ?>">
                                        <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name'] . ' (' . $supervisor['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-save me-1"></i>Update Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addStaffModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Add New Staff Member
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="user_id" class="form-label">Select User *</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">Select User</option>
                                <?php foreach ($available_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Select an existing user to enroll as staff.</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="staff_id" class="form-label">Staff ID *</label>
                            <input type="text" class="form-control" id="staff_id" name="staff_id" 
                                   value="<?php echo $_POST['staff_id'] ?? ''; ?>" 
                                   placeholder="e.g., MCC-001" required>
                            <small class="form-text text-muted">Unique identifier for the staff member.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['name']); ?>" 
                                            <?php echo ($_POST['department'] ?? '') == $dept['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="position" class="form-label">Position *</label>
                            <input type="text" class="form-control" id="position" name="position" 
                                   value="<?php echo $_POST['position'] ?? ''; ?>" 
                                   placeholder="e.g., Administrative Officer" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employment_date" class="form-label">Employment Date</label>
                            <input type="date" class="form-control" id="employment_date" name="employment_date" 
                                   value="<?php echo $_POST['employment_date'] ?? date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="salary_grade" class="form-label">Salary Grade</label>
                            <input type="text" class="form-control" id="salary_grade" name="salary_grade" 
                                   value="<?php echo $_POST['salary_grade'] ?? ''; ?>" 
                                   placeholder="e.g., G7">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="supervisor_id" class="form-label">Supervisor (Optional)</label>
                        <select class="form-select" id="supervisor_id" name="supervisor_id">
                            <option value="">No Supervisor</option>
                            <?php foreach ($supervisors as $supervisor): ?>
                                <option value="<?php echo $supervisor['id']; ?>">
                                    <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name'] . ' (' . $supervisor['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_staff" class="btn btn-success">
                        <i class="fas fa-user-plus me-1"></i>Add Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewStaff(staffId) {
    // Fetch staff details via AJAX
    fetch('get_staff_details.php?id=' + staffId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const staff = data.staff;
                document.getElementById('viewStaffId').textContent = staff.staff_id;
                document.getElementById('viewStaffName').textContent = staff.first_name + ' ' + staff.last_name;
                document.getElementById('viewStaffUsername').textContent = staff.username;
                document.getElementById('viewStaffEmail').textContent = staff.email;
                document.getElementById('viewStaffPhone').textContent = staff.phone || 'N/A';
                document.getElementById('viewStaffDepartment').textContent = staff.department;
                document.getElementById('viewStaffPosition').textContent = staff.position;
                document.getElementById('viewStaffEmploymentDate').textContent = staff.employment_date ? new Date(staff.employment_date).toLocaleDateString() : 'N/A';
                document.getElementById('viewStaffSalaryGrade').textContent = staff.salary_grade || 'N/A';
                document.getElementById('viewStaffSupervisor').textContent = staff.supervisor_name || 'None';
                const statusElement = document.getElementById('viewStaffStatus');
                if (staff.is_active) {
                    statusElement.textContent = 'Active';
                    statusElement.className = 'badge bg-success';
                } else {
                    statusElement.textContent = 'Inactive';
                    statusElement.className = 'badge bg-danger';
                }
                $('#viewStaffModal').modal('show');
            } else {
                alert('Error loading staff details.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading staff details.');
        });
}

function editStaff(staffId) {
    // Fetch staff details for editing
    fetch('get_staff_details.php?id=' + staffId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error: ' + response.status + ' ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const staff = data.staff;
                document.getElementById('editStaffId').value = staff.id;
                document.getElementById('editStaffStaffId').value = staff.staff_id;
                document.getElementById('editStaffDepartment').value = staff.department;
                document.getElementById('editStaffPosition').value = staff.position;
                document.getElementById('editStaffEmploymentDate').value = staff.employment_date ? staff.employment_date.split(' ')[0] : '';
                document.getElementById('editStaffSalaryGrade').value = staff.salary_grade || '';
                document.getElementById('editStaffSupervisor').value = staff.supervisor_id || '';
                $('#editStaffModal').modal('show');
            } else {
                alert(data.message || 'Error loading staff details.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Unable to load staff details: ' + error.message + '. Please try again later.');
        });
}

// Handle edit staff form submission
document.getElementById('editStaffForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('update_staff.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            $('#editStaffModal').modal('hide');
            location.reload(); // Reload the page to show updated data
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating staff member.');
    });
});

// Generate staff ID automatically
document.addEventListener('DOMContentLoaded', function() {
    const staffIdInput = document.getElementById('staff_id');
    const departmentSelect = document.getElementById('department');
    
    departmentSelect.addEventListener('change', function() {
        if (!staffIdInput.value) {
            const deptCode = this.value.substring(0, 3).toUpperCase();
            const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            staffIdInput.value = `MCC-${deptCode}-${randomNum}`;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
=======
                $('#viewStaffModal').modal('show');
            } else {
                alert('Error loading staff details.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading staff details.');
        });
}

function editStaff(staffId) {
    // Fetch staff details for editing
    fetch('get_staff_details.php?id=' + staffId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error: ' + response.status + ' ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const staff = data.staff;
                document.getElementById('editStaffId').value = staff.id;
                document.getElementById('editStaffStaffId').value = staff.staff_id;
                document.getElementById('editStaffDepartment').value = staff.department;
                document.getElementById('editStaffPosition').value = staff.position;
                document.getElementById('editStaffEmploymentDate').value = staff.employment_date ? staff.employment_date.split(' ')[0] : '';
                document.getElementById('editStaffSalaryGrade').value = staff.salary_grade || '';
                document.getElementById('editStaffSupervisor').value = staff.supervisor_id || '';
                $('#editStaffModal').modal('show');
            } else {
                alert(data.message || 'Error loading staff details.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Unable to load staff details: ' + error.message + '. Please try again later.');
        });
}

// Handle edit staff form submission
document.getElementById('editStaffForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('update_staff.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            $('#editStaffModal').modal('hide');
            location.reload(); // Reload the page to show updated data
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating staff member.');
    });
});

// Generate staff ID automatically
document.addEventListener('DOMContentLoaded', function() {
    const staffIdInput = document.getElementById('staff_id');
    const departmentSelect = document.getElementById('department');

    departmentSelect.addEventListener('change', function() {
        if (!staffIdInput.value) {
            const deptCode = this.value.substring(0, 3).toUpperCase();
            const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            staffIdInput.value = `MCC-${deptCode}-${randomNum}`;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
