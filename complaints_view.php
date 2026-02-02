<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}
$page_title = "Manage Complaints";
include '../includes/header.php';

$database = new Database();
$conn = $database->getConnection();

$action = $_GET['action'] ?? '';
$complaint_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle complaint actions
if ($action && $complaint_id) {
    $status = '';
    $resolution_notes = '';
    $assigned_to = null;
    
    switch ($action) {
        case 'assign':
            $assigned_to = intval($_POST['assigned_to']);
            $status = 'in_progress';
            break;
        case 'progress':
            $status = 'in_progress';
            $resolution_notes = Database::sanitize($_POST['resolution_notes'] ?? '');
            break;
        case 'resolve':
            $status = 'resolved';
            $resolution_notes = Database::sanitize($_POST['resolution_notes'] ?? '');
            break;
        case 'close':
            $status = 'closed';
            break;
        case 'reopen':
            $status = 'submitted';
            break;
    }
    
    if ($status) {
        if ($action === 'assign') {
            $sql = "UPDATE complaints SET status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?";
            $params = [$status, $assigned_to, $complaint_id];
        } else {
            $sql = "UPDATE complaints SET status = ?, resolution_notes = ?, updated_at = NOW() WHERE id = ?";
            $params = [$status, $resolution_notes, $complaint_id];
        }
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute($params)) {
            $success = "Complaint {$action} successfully!";
            
            // Get complaint details for logging and notification
            $comp_sql = "SELECT c.*, u.first_name, u.last_name, u.email 
                        FROM complaints c 
                        JOIN users u ON c.user_id = u.id 
                        WHERE c.id = ?";
            $comp_stmt = $conn->prepare($comp_sql);
            $comp_stmt->execute([$complaint_id]);
            $complaint = $comp_stmt->fetch();
            
            HelperFunctions::logActivity($_SESSION['user_id'], 'complaint_updated', 
                "{$action} complaint: {$complaint['title']}");
                
            // Create notification for user
            HelperFunctions::createNotification(
                $complaint['user_id'],
                "Complaint Update",
                "Your complaint '{$complaint['title']}' has been {$status}.",
                $status === 'resolved' ? 'success' : 'info',
                'complaint',
                $complaint_id
            );
        } else {
            $error = "Failed to update complaint.";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "c.priority = ?";
    $params[] = $priority_filter;
}

if ($category_filter !== 'all') {
    $where_conditions[] = "c.category = ?";
    $params[] = $category_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get complaints
// Get complaints - CORRECTED VERSION
$sql = "SELECT c.*, u.username, u.first_name, u.last_name, u.email, u.phone,
               assigned_user.first_name as assigned_first, assigned_user.last_name as assigned_last
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN staff st ON c.assigned_to = st.id
        LEFT JOIN users assigned_user ON st.user_id = assigned_user.id
        {$where_clause}
        ORDER BY 
            CASE c.priority 
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4
            END,
            c.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Get complaint statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(status = 'submitted') as submitted,
                SUM(status = 'in_progress') as in_progress,
                SUM(status = 'resolved') as resolved,
                SUM(status = 'closed') as closed,
                SUM(priority = 'urgent') as urgent,
                SUM(priority = 'high') as high,
                SUM(DATE(created_at) = CURDATE()) as today
              FROM complaints";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get staff for assignment
$staff_sql = "SELECT s.id, u.first_name, u.last_name, u.username, s.department 
              FROM staff s 
              JOIN users u ON s.user_id = u.id 
              WHERE s.is_active = TRUE 
              ORDER BY u.first_name, u.last_name";
$staff_stmt = $conn->prepare($staff_sql);
$staff_stmt->execute();
$staff_members = $staff_stmt->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Manage Complaints</h4>
                    <a href="export_csv.php?type=complaints" class="btn btn-light btn-sm">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </a>
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

                    <!-- Complaint Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['total']; ?></h5>
                                    <small>Total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-warning">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['submitted']; ?></h5>
                                    <small>Submitted</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-info">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['in_progress']; ?></h5>
                                    <small>In Progress</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-success">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['resolved']; ?></h5>
                                    <small>Resolved</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-danger">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['urgent']; ?></h5>
                                    <small>Urgent</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-secondary">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['today']; ?></h5>
                                    <small>Today</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="statusFilter" class="form-label">Status</label>
                            <select class="form-select" id="statusFilter" onchange="applyFilters()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="priorityFilter" class="form-label">Priority</label>
                            <select class="form-select" id="priorityFilter" onchange="applyFilters()">
                                <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priority</option>
                                <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="categoryFilter" class="form-label">Category</label>
                            <select class="form-select" id="categoryFilter" onchange="applyFilters()">
                                <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="service" <?php echo $category_filter === 'service' ? 'selected' : ''; ?>>Service</option>
                                <option value="infrastructure" <?php echo $category_filter === 'infrastructure' ? 'selected' : ''; ?>>Infrastructure</option>
                                <option value="staff" <?php echo $category_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Complaints Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Complainant</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($complaints) > 0): ?>
                                    <?php foreach ($complaints as $complaint): ?>
                                    <tr>
                                        <td><?php echo $complaint['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($complaint['title']); ?></strong>
                                            <?php if ($complaint['location']): ?>
                                                <br>
                                                <small class="text-muted">📍 <?php echo htmlspecialchars($complaint['location']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($complaint['first_name'] . ' ' . $complaint['last_name']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($complaint['email']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($complaint['category']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $complaint['priority'] == 'urgent' ? 'bg-danger' : 
                                                       ($complaint['priority'] == 'high' ? 'bg-warning' : 
                                                       ($complaint['priority'] == 'medium' ? 'bg-info' : 'bg-secondary')); ?>">
                                                <?php echo ucfirst($complaint['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $complaint['status'] == 'resolved' ? 'bg-success' : 
                                                       ($complaint['status'] == 'in_progress' ? 'bg-primary' : 
                                                       ($complaint['status'] == 'submitted' ? 'bg-warning' : 'bg-secondary')); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($complaint['assigned_first']): ?>
                                                <?php echo htmlspecialchars($complaint['assigned_first'] . ' ' . $complaint['assigned_last']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($complaint['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewComplaint(<?php echo $complaint['id']; ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <div class="dropdown">
                                                    <button class="btn btn-outline-success dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown"
                                                            title="Take Action">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if (!$complaint['assigned_to']): ?>
                                                            <li>
                                                                <a class="dropdown-item text-info" 
                                                                   href="#" 
                                                                   onclick="showAssignForm(<?php echo $complaint['id']; ?>)">
                                                                    <i class="fas fa-user-plus me-2"></i>Assign Staff
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($complaint['status'] === 'submitted'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-primary" 
                                                                   href="?action=progress&id=<?php echo $complaint['id']; ?>">
                                                                    <i class="fas fa-play me-2"></i>Start Progress
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($complaint['status'] === 'in_progress'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" 
                                                                   href="#" 
                                                                   onclick="showResolveForm(<?php echo $complaint['id']; ?>)">
                                                                    <i class="fas fa-check me-2"></i>Resolve
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($complaint['status'] === 'resolved'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-secondary" 
                                                                   href="?action=close&id=<?php echo $complaint['id']; ?>">
                                                                    <i class="fas fa-times me-2"></i>Close
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($complaint['status'] === 'closed'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" 
                                                                   href="?action=reopen&id=<?php echo $complaint['id']; ?>">
                                                                    <i class="fas fa-redo me-2"></i>Reopen
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-exclamation-triangle fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Complaints Found</h5>
                                            <p class="text-muted">No complaints match your current filters.</p>
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
</div>

<!-- Assign Staff Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="assignModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Assign Staff to Complaint
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="assignForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="assigned_to" class="form-label">Select Staff Member</label>
                        <select class="form-select" id="assigned_to" name="assigned_to" required>
                            <option value="">Select Staff Member</option>
                            <?php foreach ($staff_members as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>">
                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' - ' . $staff['department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Assign Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resolve Complaint Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="resolveModalLabel">
                    <i class="fas fa-check me-2"></i>Resolve Complaint
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="resolveForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="resolution_notes" class="form-label">Resolution Notes</label>
                        <textarea class="form-control" id="resolution_notes" name="resolution_notes" rows="4" 
                                  placeholder="Describe how the complaint was resolved..." required></textarea>
                        <small class="form-text text-muted">These notes will be shared with the complainant.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Resolve Complaint</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewComplaint(complaintId) {
    const url = `complaint_details.php?id=${complaintId}`;
    window.open(url, '_blank', 'width=800,height=600');
}

function showAssignForm(complaintId) {
    const form = document.getElementById('assignForm');
    form.action = `?action=assign&id=${complaintId}`;
    
    const modal = new bootstrap.Modal(document.getElementById('assignModal'));
    modal.show();
}

function showResolveForm(complaintId) {
    const form = document.getElementById('resolveForm');
    form.action = `?action=resolve&id=${complaintId}`;
    
    const modal = new bootstrap.Modal(document.getElementById('resolveModal'));
    modal.show();
}

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const priority = document.getElementById('priorityFilter').value;
    const category = document.getElementById('categoryFilter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('status', status);
    url.searchParams.set('priority', priority);
    url.searchParams.set('category', category);
    
    window.location.href = url.toString();
}

// Auto-refresh every 30 seconds to check for new complaints
setTimeout(() => {
    window.location.reload();
}, 30000);
</script>

<?php include '../includes/footer.php'; ?>