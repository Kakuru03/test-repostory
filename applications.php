<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}
$page_title = "Manage Applications";
include '../includes/header.php';

$database = new Database();
$conn = $database->getConnection();

$type = $_GET['type'] ?? 'internship';
$action = $_GET['action'] ?? '';
$application_id = $_GET['id'] ?? 0;

$error = '';
$success = '';

// Handle application actions
if ($action && $application_id) {
    $status = '';
    $feedback = '';
    
    switch ($action) {
        case 'approve':
            $status = 'approved';
            break;
        case 'reject':
            $status = 'rejected';
            $feedback = $_POST['feedback'] ?? '';
            break;
        case 'review':
            $status = 'under_review';
            break;
        case 'shortlist':
            $status = 'shortlisted';
            break;
        case 'hire':
            $status = 'hired';
            break;
    }
    
    if ($status) {
        if ($type === 'internship') {
            $sql = "UPDATE internship_applications SET status = ?, feedback = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
        } else {
            $sql = "UPDATE job_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
            $params = [$status, $_SESSION['staff_id'], $application_id];
        }
        
        $stmt = $conn->prepare($sql);
        
        if ($type === 'internship') {
            $params = [$status, $feedback, $_SESSION['staff_id'], $application_id];
        }
        
        if ($stmt->execute($params)) {
            $success = "Application {$action} successfully!";
            
            // Get application details for logging
            if ($type === 'internship') {
                $app_sql = "SELECT ia.*, u.first_name, u.last_name, u.email 
                           FROM internship_applications ia 
                           JOIN users u ON ia.user_id = u.id 
                           WHERE ia.id = ?";
            } else {
                $app_sql = "SELECT ja.*, u.first_name, u.last_name, u.email 
                           FROM job_applications ja 
                           JOIN users u ON ja.user_id = u.id 
                           WHERE ja.id = ?";
            }
            
            $app_stmt = $conn->prepare($app_sql);
            $app_stmt->execute([$application_id]);
            $application = $app_stmt->fetch();
            
            HelperFunctions::logActivity($_SESSION['user_id'], 'application_review', 
                "{$action} {$type} application for {$application['first_name']} {$application['last_name']}");
                
            // Create notification for user
            HelperFunctions::createNotification(
                $application['user_id'],
                "Application Update",
                "Your {$type} application has been {$status}.",
                $status === 'approved' ? 'success' : ($status === 'rejected' ? 'error' : 'info'),
                $type . '_application',
                $application_id
            );
        } else {
            $error = "Failed to update application.";
        }
    }
}

// Get applications based on type
if ($type === 'internship') {
    $sql = "SELECT ia.*, u.username, u.first_name, u.last_name, u.email, u.phone,
                   s.first_name as reviewer_first, s.last_name as reviewer_last
            FROM internship_applications ia
            JOIN users u ON ia.user_id = u.id
            LEFT JOIN staff st ON ia.reviewed_by = st.id
            LEFT JOIN users s ON st.user_id = s.id
            ORDER BY ia.applied_at DESC";
    $count_sql = "SELECT COUNT(*) as total, 
                         SUM(status = 'pending') as pending,
                         SUM(status = 'approved') as approved,
                         SUM(status = 'rejected') as rejected
                  FROM internship_applications";
} else {
    $sql = "SELECT ja.*, u.username, u.first_name, u.last_name, u.email, u.phone,
                   s.first_name as reviewer_first, s.last_name as reviewer_last
            FROM job_applications ja
            JOIN users u ON ja.user_id = u.id
            LEFT JOIN staff st ON ja.reviewed_by = st.id
            LEFT JOIN users s ON st.user_id = s.id
            ORDER BY ja.applied_at DESC";
    $count_sql = "SELECT COUNT(*) as total, 
                         SUM(status = 'pending') as pending,
                         SUM(status = 'shortlisted') as shortlisted,
                         SUM(status = 'hired') as hired,
                         SUM(status = 'rejected') as rejected
                  FROM job_applications";
}

$stmt = $conn->prepare($sql);
$stmt->execute();
$applications = $stmt->fetchAll();

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute();
$stats = $count_stmt->fetch();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas <?php echo $type === 'internship' ? 'fa-graduation-cap' : 'fa-briefcase'; ?> me-2"></i>
                        Manage <?php echo ucfirst($type); ?> Applications
                    </h4>
                    <div class="btn-group">
                        <a href="?type=internship" class="btn btn-<?php echo $type === 'internship' ? 'light' : 'outline-light'; ?>">
                            <i class="fas fa-graduation-cap me-1"></i>Internships
                        </a>
                        <a href="?type=job" class="btn btn-<?php echo $type === 'job' ? 'light' : 'outline-light'; ?>">
                            <i class="fas fa-briefcase me-1"></i>Jobs
                        </a>
                    </div>
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

                    <!-- Application Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body text-center">
                                    <h4><?php echo $stats['total']; ?></h4>
                                    <p class="mb-0">Total Applications</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-warning">
                                <div class="card-body text-center">
                                    <h4><?php echo $stats['pending']; ?></h4>
                                    <p class="mb-0">Pending</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-success">
                                <div class="card-body text-center">
                                    <h4><?php echo $type === 'internship' ? $stats['approved'] : $stats['shortlisted']; ?></h4>
                                    <p class="mb-0"><?php echo $type === 'internship' ? 'Approved' : 'Shortlisted'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-danger">
                                <div class="card-body text-center">
                                    <h4><?php echo $stats['rejected']; ?></h4>
                                    <p class="mb-0">Rejected</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Applications Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Applicant</th>
                                    <th><?php echo $type === 'internship' ? 'Department' : 'Job Title'; ?></th>
                                    <th>Contact</th>
                                    <th>Applied</th>
                                    <th>Status</th>
                                    <th>Reviewer</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($applications) > 0): ?>
                                    <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?php echo $app['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">@<?php echo htmlspecialchars($app['username']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($type === 'internship'): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($app['department']); ?></span>
                                                <br>
                                                <small>Duration: <?php echo $app['duration_months']; ?> months</small>
                                            <?php else: ?>
                                                <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($app['department']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($app['email']); ?>
                                                <br>
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($app['phone'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($app['applied_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $app['status'] === 'approved' || $app['status'] === 'hired' ? 'bg-success' : 
                                                       ($app['status'] === 'rejected' ? 'bg-danger' : 
                                                       ($app['status'] === 'shortlisted' || $app['status'] === 'under_review' ? 'bg-info' : 'bg-warning')); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($app['reviewer_first']): ?>
                                                <?php echo htmlspecialchars($app['reviewer_first'] . ' ' . $app['reviewer_last']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('M j', strtotime($app['reviewed_at'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Not reviewed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewApplication(<?php echo $app['id']; ?>, '<?php echo $type; ?>')"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($app['status'] === 'pending'): ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-success dropdown-toggle" 
                                                                type="button" data-bs-toggle="dropdown"
                                                                title="Take Action">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if ($type === 'internship'): ?>
                                                                <li>
                                                                    <a class="dropdown-item text-success" 
                                                                       href="?type=<?php echo $type; ?>&action=approve&id=<?php echo $app['id']; ?>">
                                                                        <i class="fas fa-check me-2"></i>Approve
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item text-warning" 
                                                                       href="?type=<?php echo $type; ?>&action=review&id=<?php echo $app['id']; ?>">
                                                                        <i class="fas fa-search me-2"></i>Under Review
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item text-danger" 
                                                                       href="#" 
                                                                       onclick="showRejectForm(<?php echo $app['id']; ?>, '<?php echo $type; ?>')">
                                                                        <i class="fas fa-times me-2"></i>Reject
                                                                    </a>
                                                                </li>
                                                            <?php else: ?>
                                                                <li>
                                                                    <a class="dropdown-item text-info" 
                                                                       href="?type=<?php echo $type; ?>&action=shortlist&id=<?php echo $app['id']; ?>">
                                                                        <i class="fas fa-list me-2"></i>Shortlist
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item text-success" 
                                                                       href="?type=<?php echo $type; ?>&action=hire&id=<?php echo $app['id']; ?>">
                                                                        <i class="fas fa-user-check me-2"></i>Hire
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item text-danger" 
                                                                       href="?type=<?php echo $type; ?>&action=reject&id=<?php echo $app['id']; ?>">
                                                                        <i class="fas fa-times me-2"></i>Reject
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas <?php echo $type === 'internship' ? 'fa-graduation-cap' : 'fa-briefcase'; ?> fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No <?php echo $type; ?> Applications</h5>
                                            <p class="text-muted">No <?php echo $type; ?> applications have been submitted yet.</p>
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

<!-- Reject Application Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectModalLabel">
                    <i class="fas fa-times me-2"></i>Reject Application
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="rejectForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="feedback" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="feedback" name="feedback" rows="4" 
                                  placeholder="Please provide a reason for rejecting this application..." required></textarea>
                        <small class="form-text text-muted">This feedback will be sent to the applicant.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewApplication(appId, type) {
    const url = `application_details.php?id=${appId}&type=${type}`;
    window.open(url, '_blank', 'width=800,height=600');
}

function showRejectForm(appId, type) {
    const form = document.getElementById('rejectForm');
    form.action = `?type=${type}&action=reject&id=${appId}`;
    
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}

// Filter applications by status
function filterApplications(status) {
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const statusBadge = row.querySelector('.badge');
        if (status === 'all' || statusBadge.textContent.toLowerCase().includes(status)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>