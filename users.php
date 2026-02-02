<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}
$page_title = "Manage Users";
include '../includes/header.php';

$database = new Database();
$conn = $database->getConnection();

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    switch ($action) {
        case 'activate':
            $sql = "UPDATE users SET status = 'active' WHERE id = ?";
            $message = "User activated successfully.";
            break;
        case 'deactivate':
            $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
            $message = "User deactivated successfully.";
            break;
        case 'delete':
            $sql = "DELETE FROM users WHERE id = ? AND user_type = 'public'";
            $message = "User deleted successfully.";
            break;
        default:
            $message = "Invalid action.";
    }
    
    if (isset($sql)) {
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$user_id])) {
            $success = $message;
            HelperFunctions::logActivity($_SESSION['user_id'], 'user_management', $message . ' User ID: ' . $user_id);
        } else {
            $error = "Failed to perform action.";
        }
    }
}

// Get all users with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'public'";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute();
$total_users = $count_stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

// Get users
$users_sql = "SELECT * FROM users WHERE user_type = 'public' ORDER BY created_at DESC LIMIT ? OFFSET ?";
$users_stmt = $conn->prepare($users_sql);
$users_stmt->bindValue(1, $limit, PDO::PARAM_INT);
$users_stmt->bindValue(2, $offset, PDO::PARAM_INT);
$users_stmt->execute();
$users = $users_stmt->fetchAll();

// Search functionality
$search = isset($_GET['search']) ? Database::sanitize($_GET['search']) : '';
if (!empty($search)) {
    $search_sql = "SELECT * FROM users WHERE user_type = 'public' AND 
                  (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?) 
                  ORDER BY created_at DESC";
    $search_stmt = $conn->prepare($search_sql);
    $search_term = "%$search%";
    $search_stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    $users = $search_stmt->fetchAll();
    $total_users = count($users);
    $total_pages = 1;
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-users me-2"></i>Manage Users</h4>
                    <span class="badge bg-light text-primary">Total: <?php echo $total_users; ?> users</span>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Search and Filters -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" action="">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search users by name, username, or email...">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="users.php" class="btn btn-outline-secondary">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <a href="export_csv.php?type=users" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Export CSV
                            </a>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Profession</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($user['profession'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $user['status'] == 'active' ? 'bg-success' : 
                                                       ($user['status'] == 'inactive' ? 'bg-danger' : 'bg-warning'); ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewUser(<?php echo $user['id']; ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($user['status'] == 'active'): ?>
                                                    <a href="?action=deactivate&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Deactivate">
                                                        <i class="fas fa-pause"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?action=activate&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-success" title="Activate">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-outline-info" 
                                                        onclick="editUser(<?php echo $user['id']; ?>)"
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')"
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Users Found</h5>
                                            <?php if (!empty($search)): ?>
                                                <p class="text-muted">No users match your search criteria.</p>
                                            <?php else: ?>
                                                <p class="text-muted">No users registered yet.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1 && empty($search)): ?>
                    <nav aria-label="User pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Statistics -->
    <div class="row">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $total_users; ?></h4>
                            <p class="mb-0">Total Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
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
                            $active_users_sql = "SELECT COUNT(*) as count FROM users WHERE status = 'active' AND user_type = 'public'";
                            $active_users_stmt = $conn->prepare($active_users_sql);
                            $active_users_stmt->execute();
                            $active_users = $active_users_stmt->fetch()['count'];
                            ?>
                            <h4><?php echo $active_users; ?></h4>
                            <p class="mb-0">Active Users</p>
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
                            $inactive_users_sql = "SELECT COUNT(*) as count FROM users WHERE status = 'inactive' AND user_type = 'public'";
                            $inactive_users_stmt = $conn->prepare($inactive_users_sql);
                            $inactive_users_stmt->execute();
                            $inactive_users = $inactive_users_stmt->fetch()['count'];
                            ?>
                            <h4><?php echo $inactive_users; ?></h4>
                            <p class="mb-0">Inactive Users</p>
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
                            $today_users_sql = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE() AND user_type = 'public'";
                            $today_users_stmt = $conn->prepare($today_users_sql);
                            $today_users_stmt->execute();
                            $today_users = $today_users_stmt->fetch()['count'];
                            ?>
                            <h4><?php echo $today_users; ?></h4>
                            <p class="mb-0">New Today</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-plus fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewUser(userId) {
    alert('View user details for ID: ' + userId + '\nThis would typically open a modal with user details and activity history.');
}

function editUser(userId) {
    alert('Edit user with ID: ' + userId + '\nThis would typically open an edit form or modal.');
}

// Search with debounce
const searchInput = document.querySelector('input[name="search"]');
let searchTimeout;

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});
</script>

<?php include '../includes/footer.php'; ?>