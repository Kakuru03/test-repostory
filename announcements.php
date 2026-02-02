<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}
$page_title = "Manage Announcements";
include '../includes/header.php';

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = Database::sanitize($_POST['title']);
    $content = Database::sanitize($_POST['content']);
    $target_audience = Database::sanitize($_POST['target_audience']);
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $start_date = Database::sanitize($_POST['start_date']);
    $end_date = !empty($_POST['end_date']) ? Database::sanitize($_POST['end_date']) : null;

    // Validate inputs
    if (empty($title) || empty($content)) {
        $error = 'Title and content are required.';
    } else {
        // Insert announcement
        $sql = "INSERT INTO announcements (title, content, author_id, target_audience, is_urgent, start_date, end_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$title, $content, $_SESSION['user_id'], $target_audience, $is_urgent, $start_date, $end_date])) {
            $announcement_id = $conn->lastInsertId();
            
            $success = 'Announcement published successfully!';
            HelperFunctions::logActivity($_SESSION['user_id'], 'announcement_created', 
                "Created announcement: {$title}");
                
            // Clear form
            $_POST = array();
        } else {
            $error = 'Failed to publish announcement. Please try again.';
        }
    }
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $announcement_id = intval($_GET['id']);
    
    $sql = "DELETE FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$announcement_id])) {
        $success = 'Announcement deleted successfully!';
        HelperFunctions::logActivity($_SESSION['user_id'], 'announcement_deleted', 
            "Deleted announcement ID: {$announcement_id}");
    } else {
        $error = 'Failed to delete announcement.';
    }
}

// Get all announcements
$sql = "SELECT a.*, u.first_name, u.last_name, u.username 
        FROM announcements a 
        JOIN users u ON a.author_id = u.id 
        ORDER BY a.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$announcements = $stmt->fetchAll();

// Get announcement statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(is_urgent = 1) as urgent,
                SUM(target_audience = 'public') as public_announcements,
                SUM(target_audience = 'staff') as staff_announcements,
                SUM(DATE(created_at) = CURDATE()) as today
              FROM announcements";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Create New Announcement</h4>
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

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="title" class="form-label">Announcement Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo $_POST['title'] ?? ''; ?>" 
                                   placeholder="Enter a clear and concise title" required>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Announcement Content *</label>
                            <textarea class="form-control" id="content" name="content" rows="6" 
                                      placeholder="Write the full announcement content here..." required><?php echo $_POST['content'] ?? ''; ?></textarea>
                            <small class="form-text text-muted">You can use basic HTML formatting if needed.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="target_audience" class="form-label">Target Audience *</label>
                                <select class="form-select" id="target_audience" name="target_audience" required>
                                    <option value="all" <?php echo ($_POST['target_audience'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Users</option>
                                    <option value="public" <?php echo ($_POST['target_audience'] ?? '') === 'public' ? 'selected' : ''; ?>>Public Users Only</option>
                                    <option value="staff" <?php echo ($_POST['target_audience'] ?? '') === 'staff' ? 'selected' : ''; ?>>Staff Only</option>
                                    <option value="interns" <?php echo ($_POST['target_audience'] ?? '') === 'interns' ? 'selected' : ''; ?>>Interns Only</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $_POST['start_date'] ?? date('Y-m-d\TH:i'); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date (Optional)</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $_POST['end_date'] ?? ''; ?>">
                                <small class="form-text text-muted">Leave empty if the announcement has no expiration.</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="is_urgent" name="is_urgent" 
                                           <?php echo isset($_POST['is_urgent']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold text-danger" for="is_urgent">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Mark as Urgent
                                    </label>
                                    <small class="form-text text-muted d-block">Urgent announcements will be highlighted.</small>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Publish Announcement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Announcement Statistics -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Announcement Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Total Announcements
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['total']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Urgent Announcements
                            <span class="badge bg-danger rounded-pill"><?php echo $stats['urgent']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Public Announcements
                            <span class="badge bg-success rounded-pill"><?php echo $stats['public_announcements']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Staff Announcements
                            <span class="badge bg-warning rounded-pill"><?php echo $stats['staff_announcements']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Published Today
                            <span class="badge bg-info rounded-pill"><?php echo $stats['today']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary text-start" onclick="fillUrgentTemplate()">
                            <i class="fas fa-exclamation-triangle me-2"></i>Urgent Announcement Template
                        </button>
                        <button type="button" class="btn btn-outline-success text-start" onclick="fillStaffTemplate()">
                            <i class="fas fa-user-tie me-2"></i>Staff Announcement Template
                        </button>
                        <button type="button" class="btn btn-outline-info text-start" onclick="fillPublicTemplate()">
                            <i class="fas fa-users me-2"></i>Public Announcement Template
                        </button>
                        <a href="export_csv.php?type=announcements" class="btn btn-outline-warning text-start">
                            <i class="fas fa-download me-2"></i>Export Announcements
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Existing Announcements -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Existing Announcements</h5>
                    <span class="badge bg-light text-warning"><?php echo count($announcements); ?> Total</span>
                </div>
                <div class="card-body">
                    <?php if (count($announcements) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Audience</th>
                                        <th>Author</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $announcement): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                            <?php if ($announcement['is_urgent']): ?>
                                                <span class="badge bg-danger ms-1">URGENT</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $announcement['target_audience'] === 'staff' ? 'bg-warning' : 
                                                       ($announcement['target_audience'] === 'public' ? 'bg-success' : 'bg-primary'); ?>">
                                                <?php echo ucfirst($announcement['target_audience']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                            <br>
                                            <small class="text-muted">@<?php echo htmlspecialchars($announcement['username']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $now = new DateTime();
                                            $end_date = $announcement['end_date'] ? new DateTime($announcement['end_date']) : null;
                                            $start_date = new DateTime($announcement['start_date']);
                                            
                                            if ($end_date && $now > $end_date) {
                                                echo '<span class="badge bg-secondary">Expired</span>';
                                            } elseif ($now < $start_date) {
                                                echo '<span class="badge bg-info">Scheduled</span>';
                                            } else {
                                                echo '<span class="badge bg-success">Active</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo $announcement['end_date'] ? date('M j, Y', strtotime($announcement['end_date'])) : 'Never'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewAnnouncement(<?php echo $announcement['id']; ?>)"
                                                        title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-info" 
                                                        onclick="editAnnouncement(<?php echo $announcement['id']; ?>)"
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?action=delete&id=<?php echo $announcement['id']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this announcement?')"
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Announcements</h5>
                            <p class="text-muted">No announcements have been created yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillUrgentTemplate() {
    document.getElementById('title').value = 'URGENT: Important Update';
    document.getElementById('content').value = 'This is an urgent announcement requiring immediate attention.\n\nPlease take note of the following important information:\n\n• Item 1\n• Item 2\n• Item 3\n\nThank you for your immediate attention to this matter.';
    document.getElementById('target_audience').value = 'all';
    document.getElementById('is_urgent').checked = true;
}

function fillStaffTemplate() {
    document.getElementById('title').value = 'Staff Meeting Announcement';
    document.getElementById('content').value = 'Dear Staff Members,\n\nThis is to inform you about an upcoming staff meeting.\n\nDate: [Insert Date]\nTime: [Insert Time]\nVenue: [Insert Venue]\nAgenda: [Insert Agenda]\n\nYour attendance is mandatory. Please come prepared.';
    document.getElementById('target_audience').value = 'staff';
    document.getElementById('is_urgent').checked = false;
}

function fillPublicTemplate() {
    document.getElementById('title').value = 'Public Service Announcement';
    document.getElementById('content').value = 'Dear Citizens,\n\nThis is to bring to your attention important information regarding our services.\n\nWe are committed to serving you better and appreciate your continued support.\n\nFor any inquiries, please contact our support team.';
    document.getElementById('target_audience').value = 'public';
    document.getElementById('is_urgent').checked = false;
}

function viewAnnouncement(id) {
    const url = `announcement_preview.php?id=${id}`;
    window.open(url, '_blank', 'width=800,height=600');
}

function editAnnouncement(id) {
    alert('Edit announcement with ID: ' + id + '\nThis would typically open an edit form.');
}

// Set minimum datetime for start date to current time
document.getElementById('start_date').min = new Date().toISOString().slice(0, 16);
</script>

<?php include '../includes/footer.php'; ?>