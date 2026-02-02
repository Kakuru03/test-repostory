<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

$id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? '';

if (!$id || !in_array($type, ['internship', 'job'])) {
    die('Invalid request parameters.');
}

// Get application details
if ($type === 'internship') {
    $sql = "SELECT ia.*, u.username, u.first_name, u.last_name, u.email, u.phone,
                   s.first_name as reviewer_first, s.last_name as reviewer_last
            FROM internship_applications ia
            JOIN users u ON ia.user_id = u.id
            LEFT JOIN staff st ON ia.reviewed_by = st.id
            LEFT JOIN users s ON st.user_id = s.id
            WHERE ia.id = ?";
} else {
    $sql = "SELECT ja.*, u.username, u.first_name, u.last_name, u.email, u.phone,
                   s.first_name as reviewer_first, s.last_name as reviewer_last
            FROM job_applications ja
            JOIN users u ON ja.user_id = u.id
            LEFT JOIN staff st ON ja.reviewed_by = st.id
            LEFT JOIN users s ON st.user_id = s.id
            WHERE ja.id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$application = $stmt->fetch();

if (!$application) {
    die('Application not found.');
}

// Handle document download
if (isset($_GET['download']) && $application['resume_path']) {
    $file_path = DOCUMENT_PATH . $application['resume_path'];
    if (file_exists($file_path)) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_type = '';
        switch ($extension) {
            case 'pdf':
                $mime_type = 'application/pdf';
                break;
            case 'docx':
                $mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                break;
            default:
                $mime_type = 'application/octet-stream';
                break;
        }
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        $error = 'Resume file not found.';
    }
}

$page_title = ucfirst($type) . " Application Details";
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas <?php echo $type === 'internship' ? 'fa-graduation-cap' : 'fa-briefcase'; ?> me-2"></i>
                        <?php echo ucfirst($type); ?> Application Details
                    </h4>
                    <a href="applications.php?type=<?php echo $type; ?>" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i>Back to Applications
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Application Status -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="alert alert-<?php echo $application['status'] === 'approved' || $application['status'] === 'hired' ? 'success' :
                                                       ($application['status'] === 'rejected' ? 'danger' :
                                                       ($application['status'] === 'shortlisted' || $application['status'] === 'under_review' ? 'info' : 'warning')); ?> d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                                <?php if ($application['reviewed_at']): ?>
                                    <span class="ms-auto text-muted small">
                                        Reviewed on <?php echo date('M j, Y g:i A', strtotime($application['reviewed_at'])); ?>
                                        <?php if ($application['reviewer_first']): ?>
                                            by <?php echo htmlspecialchars($application['reviewer_first'] . ' ' . $application['reviewer_last']); ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Applicant Information -->
                        <div class="col-lg-4">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Applicant Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>Name:</strong><br>
                                        <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Username:</strong><br>
                                        <?php echo htmlspecialchars($application['username']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Email:</strong><br>
                                        <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>">
                                            <?php echo htmlspecialchars($application['email']); ?>
                                        </a>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Phone:</strong><br>
                                        <a href="tel:<?php echo htmlspecialchars($application['phone'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($application['phone'] ?? 'N/A'); ?>
                                        </a>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Applied On:</strong><br>
                                        <?php echo date('M j, Y g:i A', strtotime($application['applied_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Application Details -->
                        <div class="col-lg-8">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas <?php echo $type === 'internship' ? 'fa-graduation-cap' : 'fa-briefcase'; ?> me-2"></i>
                                        Application Details
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($type === 'internship'): ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <strong>Department:</strong><br>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($application['department']); ?></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong>Duration:</strong><br>
                                                <?php echo $application['duration_months']; ?> months
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Skills:</strong><br>
                                            <?php echo htmlspecialchars($application['skills'] ?? 'Not specified'); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Academic Level:</strong><br>
                                            <?php echo htmlspecialchars($application['academic_level'] ?? 'Not specified'); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Institution:</strong><br>
                                            <?php echo htmlspecialchars($application['institution'] ?? 'Not specified'); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Expected Graduation:</strong><br>
                                            <?php echo $application['expected_graduation'] ? date('M Y', strtotime($application['expected_graduation'])) : 'Not specified'; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <strong>Job Title:</strong><br>
                                                <?php echo htmlspecialchars($application['job_title']); ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong>Department:</strong><br>
                                                <?php echo htmlspecialchars($application['department']); ?>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <strong>Expected Salary:</strong><br>
                                                UGX <?php echo number_format($application['expected_salary'] ?? 0); ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong>Years of Experience:</strong><br>
                                                <?php echo $application['years_experience'] ?? 0; ?> years
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Qualifications:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($application['qualifications'] ?? 'Not specified')); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <strong>Cover Letter:</strong><br>
                                        <div class="border p-3 bg-light rounded">
                                            <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                                        </div>
                                    </div>

                                    <!-- Resume Section -->
                                    <?php if ($application['resume_path']): ?>
                                        <div class="mb-3">
                                            <strong>Resume:</strong><br>
                                            <div class="d-flex gap-2">
                                                <a href="?id=<?php echo $id; ?>&type=<?php echo $type; ?>&download=1"
                                                   class="btn btn-outline-primary btn-sm"
                                                   target="_blank">
                                                    <i class="fas fa-download me-1"></i>Download Resume
                                                </a>
                                                <?php
                                                $file_path = DOCUMENT_PATH . $application['resume_path'];
                                                if (file_exists($file_path)):
                                                    $file_size = filesize($file_path);
                                                    $file_size_mb = round($file_size / 1024 / 1024, 2);
                                                ?>
                                                    <small class="text-muted">
                                                        (<?php echo strtoupper(pathinfo($application['resume_path'], PATHINFO_EXTENSION)); ?>,
                                                        <?php echo $file_size_mb; ?> MB)
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-danger">(File not found on server)</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <strong>Resume:</strong><br>
                                            <span class="text-muted">No resume uploaded</span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Feedback Section -->
                                    <?php if (isset($application['feedback']) && $application['feedback']): ?>
                                        <div class="mb-3">
                                            <strong>Feedback:</strong><br>
                                            <div class="border p-3 bg-light rounded">
                                                <?php echo nl2br(htmlspecialchars($application['feedback'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-center gap-2">
                                <a href="applications.php?type=<?php echo $type; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Applications
                                </a>

                                <?php if ($application['status'] === 'pending'): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog me-1"></i>Take Action
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if ($type === 'internship'): ?>
                                                <li>
                                                    <a class="dropdown-item text-success"
                                                       href="applications.php?type=<?php echo $type; ?>&action=approve&id=<?php echo $id; ?>">
                                                        <i class="fas fa-check me-2"></i>Approve
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-warning"
                                                       href="applications.php?type=<?php echo $type; ?>&action=review&id=<?php echo $id; ?>">
                                                        <i class="fas fa-search me-2"></i>Under Review
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger"
                                                       href="#"
                                                       onclick="showRejectForm(<?php echo $id; ?>, '<?php echo $type; ?>')">
                                                        <i class="fas fa-times me-2"></i>Reject
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <a class="dropdown-item text-info"
                                                       href="applications.php?type=<?php echo $type; ?>&action=shortlist&id=<?php echo $id; ?>">
                                                        <i class="fas fa-list me-2"></i>Shortlist
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-success"
                                                       href="applications.php?type=<?php echo $type; ?>&action=hire&id=<?php echo $id; ?>">
                                                        <i class="fas fa-user-check me-2"></i>Hire
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger"
                                                       href="applications.php?type=<?php echo $type; ?>&action=reject&id=<?php echo $id; ?>">
                                                        <i class="fas fa-times me-2"></i>Reject
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
            <form method="POST" action="applications.php?type=<?php echo $type; ?>&action=reject&id=<?php echo $id; ?>">
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
function showRejectForm(appId, type) {
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
