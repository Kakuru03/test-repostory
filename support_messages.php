<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}
$page_title = "Manage Support Messages";
include '../includes/header.php';

$database = new Database();
$conn = $database->getConnection();

$action = $_GET['action'] ?? '';
$message_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle message actions
if ($action && $message_id) {
    switch ($action) {
        case 'reply':
            $reply_message = Database::sanitize($_POST['reply_message'] ?? '');
            $status = 'replied';
            
            if (empty($reply_message)) {
                $error = "Reply message cannot be empty.";
            } else {
                $sql = "UPDATE support_messages SET status = ?, reply_message = ?, replied_by = ?, replied_at = NOW() WHERE id = ?";
                $params = [$status, $reply_message, $_SESSION['user_id'], $message_id];
                
                $stmt = $conn->prepare($sql);
                
                if ($stmt->execute($params)) {
                    $success = "Reply sent successfully!";
                    
                    // Get message details for logging and notification
                    $msg_sql = "SELECT sm.*, u.first_name, u.last_name, u.email 
                                FROM support_messages sm 
                                JOIN users u ON sm.user_id = u.id 
                                WHERE sm.id = ?";
                    $msg_stmt = $conn->prepare($msg_sql);
                    $msg_stmt->execute([$message_id]);
                    $message = $msg_stmt->fetch();
                    
                    HelperFunctions::logActivity($_SESSION['user_id'], 'support_replied', 
                        "Replied to support message: {$message['subject']}");
                        
                    // Create notification for user
                    HelperFunctions::createNotification(
                        $message['user_id'],
                        "Support Reply",
                        "Admin has replied to your support message: {$message['subject']}",
                        'success',
                        'support',
                        $message_id
                    );
                } else {
                    $error = "Failed to send reply.";
                }
            }
            break;
            
        case 'close':
            $sql = "UPDATE support_messages SET status = 'closed' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$message_id])) {
                $success = "Support message closed successfully!";
            } else {
                $error = "Failed to close support message.";
            }
            break;
            
        case 'reopen':
            $sql = "UPDATE support_messages SET status = 'pending' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$message_id])) {
                $success = "Support message reopened successfully!";
            } else {
                $error = "Failed to reopen support message.";
            }
            break;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "sm.status = ?";
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "sm.message_type = ?";
    $params[] = $type_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get support messages
$sql = "SELECT sm.*, u.username, u.first_name, u.last_name, u.email, u.phone,
               admin.first_name as admin_first, admin.last_name as admin_last
        FROM support_messages sm
        JOIN users u ON sm.user_id = u.id
        LEFT JOIN users admin ON sm.replied_by = admin.id
        {$where_clause}
        ORDER BY 
            CASE sm.status 
                WHEN 'pending' THEN 1
                WHEN 'replied' THEN 2
                ELSE 3
            END,
            sm.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Get message statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(status = 'pending') as pending,
                SUM(status = 'replied') as replied,
                SUM(status = 'closed') as closed,
                SUM(message_type = 'technical') as technical,
                SUM(message_type = 'billing') as billing,
                SUM(message_type = 'general') as general,
                SUM(DATE(created_at) = CURDATE()) as today
              FROM support_messages";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-headset me-2"></i>Manage Support Messages</h4>
                    <a href="export_csv.php?type=support" class="btn btn-light btn-sm">
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

                    <!-- Support Statistics -->
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
                                    <h5 class="mb-0"><?php echo $stats['pending']; ?></h5>
                                    <small>Pending</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-success">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['replied']; ?></h5>
                                    <small>Replied</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-secondary">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['closed']; ?></h5>
                                    <small>Closed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-danger">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['technical']; ?></h5>
                                    <small>Technical</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-white bg-dark">
                                <div class="card-body text-center p-3">
                                    <h5 class="mb-0"><?php echo $stats['today']; ?></h5>
                                    <small>Today</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="statusFilter" class="form-label">Status</label>
                            <select class="form-select" id="statusFilter" onchange="applyFilters()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="typeFilter" class="form-label">Message Type</label>
                            <select class="form-select" id="typeFilter" onchange="applyFilters()">
                                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="technical" <?php echo $type_filter === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                <option value="billing" <?php echo $type_filter === 'billing' ? 'selected' : ''; ?>>Billing</option>
                                <option value="general" <?php echo $type_filter === 'general' ? 'selected' : ''; ?>>General</option>
                                <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Messages Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Subject</th>
                                    <th>User</th>
                                    <th>Message</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Replied By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($messages) > 0): ?>
                                    <?php foreach ($messages as $message): ?>
                                    <tr>
                                        <td><?php echo $message['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($message['subject']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($message['email']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="message-preview">
                                                <?php 
                                                $preview = htmlspecialchars($message['message']);
                                                if (strlen($preview) > 100) {
                                                    echo substr($preview, 0, 100) . '...';
                                                } else {
                                                    echo $preview;
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $message['message_type'] == 'technical' ? 'bg-danger' : 
                                                       ($message['message_type'] == 'billing' ? 'bg-warning' : 
                                                       ($message['message_type'] == 'general' ? 'bg-info' : 'bg-secondary')); ?>">
                                                <?php echo ucfirst($message['message_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $message['status'] == 'replied' ? 'bg-success' : 
                                                       ($message['status'] == 'pending' ? 'bg-warning' : 'bg-secondary'); ?>">
                                                <?php echo ucfirst($message['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($message['created_at'])); ?>
                                                <br>
                                                <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($message['admin_first']): ?>
                                                <?php echo htmlspecialchars($message['admin_first'] . ' ' . $message['admin_last']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($message['replied_at'])); ?>
                                                <br>
                                                    <?php echo date('g:i A', strtotime($message['replied_at'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Not replied</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewMessage(<?php echo $message['id']; ?>)"
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
                                                        <?php if ($message['status'] === 'pending'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-primary" 
                                                                   href="#" 
                                                                   onclick="showReplyForm(<?php echo $message['id']; ?>)">
                                                                    <i class="fas fa-reply me-2"></i>Reply
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($message['status'] === 'replied'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-secondary" 
                                                                   href="?action=close&id=<?php echo $message['id']; ?>">
                                                                    <i class="fas fa-times me-2"></i>Close
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($message['status'] === 'closed'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" 
                                                                   href="?action=reopen&id=<?php echo $message['id']; ?>">
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
                                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Support Messages Found</h5>
                                            <p class="text-muted">No support messages match your current filters.</p>
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

<!-- Reply Message Modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="replyModalLabel">
                    <i class="fas fa-reply me-2"></i>Reply to Support Message
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="replyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="original_message" class="form-label">Original Message</label>
                        <textarea class="form-control" id="original_message" rows="4" readonly style="background-color: #f8f9fa;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="reply_message" class="form-label">Your Reply</label>
                        <textarea class="form-control" id="reply_message" name="reply_message" rows="6" 
                                  placeholder="Type your response here..." required></textarea>
                        <small class="form-text text-muted">Your reply will be sent to the user and stored in the system.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewModalLabel">
                    <i class="fas fa-eye me-2"></i>Support Message Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewMessage(messageId) {
    // Fetch message details via AJAX
    fetch(`get_message_details.php?id=${messageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = data.message;
                let replySection = '';
                
                if (message.reply_message) {
                    replySection = `
                        <div class="card mt-3 border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-reply me-2"></i>Admin Reply</h6>
                            </div>
                            <div class="card-body">
                                <p class="card-text">${message.reply_message}</p>
                                <small class="text-muted">
                                    Replied by: ${message.admin_first} ${message.admin_last} on 
                                    ${new Date(message.replied_at).toLocaleDateString()} at 
                                    ${new Date(message.replied_at).toLocaleTimeString()}
                                </small>
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('viewModalBody').innerHTML = `
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">${message.subject}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>From:</strong> ${message.first_name} ${message.last_name}
                                </div>
                                <div class="col-md-6">
                                    <strong>Email:</strong> ${message.email}
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Type:</strong> 
                                    <span class="badge ${getTypeBadgeClass(message.message_type)}">
                                        ${message.message_type}
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong> 
                                    <span class="badge ${getStatusBadgeClass(message.status)}">
                                        ${message.status}
                                    </span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong>Message:</strong>
                                <div class="border p-3 mt-2 rounded bg-light">
                                    ${message.message}
                                </div>
                            </div>
                            <div class="text-muted">
                                <small>Submitted: ${new Date(message.created_at).toLocaleString()}</small>
                            </div>
                        </div>
                    </div>
                    ${replySection}
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('viewModal'));
                modal.show();
            } else {
                alert('Failed to load message details.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load message details.');
        });
}

function showReplyForm(messageId) {
    // Fetch message details to show in the form
    fetch(`get_message_details.php?id=${messageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = data.message;
                document.getElementById('original_message').value = message.message;
                
                const form = document.getElementById('replyForm');
                form.action = `?action=reply&id=${messageId}`;
                
                const modal = new bootstrap.Modal(document.getElementById('replyModal'));
                modal.show();
            } else {
                alert('Failed to load message details.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load message details.');
        });
}

function getTypeBadgeClass(type) {
    switch(type) {
        case 'technical': return 'bg-danger';
        case 'billing': return 'bg-warning';
        case 'general': return 'bg-info';
        default: return 'bg-secondary';
    }
}

function getStatusBadgeClass(status) {
    switch(status) {
        case 'pending': return 'bg-warning';
        case 'replied': return 'bg-success';
        case 'closed': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const type = document.getElementById('typeFilter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('status', status);
    url.searchParams.set('type', type);
    
    window.location.href = url.toString();
}

// Auto-refresh every 30 seconds to check for new messages
setTimeout(() => {
    window.location.reload();
}, 30000);

// Add hover effects to table rows
document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
            this.style.transition = 'all 0.3s ease';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>