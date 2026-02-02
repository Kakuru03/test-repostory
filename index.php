<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}
$page_title = "Admin Dashboard";
include '../includes/header.php';

$database = new Database();
$conn = $database->getConnection();

// Get statistics for admin dashboard
$stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE user_type = 'public') as total_users,
        (SELECT COUNT(*) FROM staff WHERE is_active = TRUE) as total_staff,
        (SELECT COUNT(*) FROM internship_applications WHERE status = 'pending') as pending_internships,
        (SELECT COUNT(*) FROM job_applications WHERE status = 'pending') as pending_jobs,
        (SELECT COUNT(*) FROM complaints WHERE status = 'submitted') as pending_complaints,
        (SELECT COUNT(*) FROM support_messages WHERE status = 'new') as new_support_messages,
        (SELECT COUNT(*) FROM announcements WHERE DATE(created_at) = CURDATE()) as today_announcements
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get recent activities
$activities_sql = "SELECT ua.*, u.username, u.first_name, u.last_name 
                   FROM user_activities ua 
                   JOIN users u ON ua.user_id = u.id 
                   ORDER BY ua.created_at DESC 
                   LIMIT 10";
$activities_stmt = $conn->prepare($activities_sql);
$activities_stmt->execute();
$activities = $activities_stmt->fetchAll();

// Get system status
$system_sql = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM internship_applications WHERE status = 'approved' AND start_date >= CURDATE()) as upcoming_interns,
        (SELECT COUNT(*) FROM complaints WHERE status = 'in_progress') as in_progress_complaints
";
$system_stmt = $conn->prepare($system_sql);
$system_stmt->execute();
$system_stats = $system_stmt->fetch();
?>

<div class="container-fluid py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass-card text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h2 mb-2">Admin Dashboard</h1>
                            <p class="mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! 
                                Here's an overview of the system.</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="btn-group">
                                <a href="users.php" class="btn btn-primary">
                                    <i class="fas fa-users me-2"></i>Manage Users
                                </a>
                                <a href="staff.php" class="btn btn-success">
                                    <i class="fas fa-user-tie me-2"></i>Manage Staff
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card glass-card text-white border-start border-primary border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="card-title">Total Users</h6>
                            <h3 class="mb-0"><?php echo $stats['total_users']; ?></h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                    <a href="users.php" class="text-white stretched-link small">View All →</a>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card glass-card text-white border-start border-success border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="card-title">Active Staff</h6>
                            <h3 class="mb-0"><?php echo $stats['total_staff']; ?></h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-user-tie fa-2x text-success"></i>
                        </div>
                    </div>
                    <a href="staff.php" class="text-white stretched-link small">Manage →</a>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card glass-card text-white border-start border-warning border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="card-title">Pending Internships</h6>
                            <h3 class="mb-0"><?php echo $stats['pending_internships']; ?></h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-graduation-cap fa-2x text-warning"></i>
                        </div>
                    </div>
                    <a href="applications.php?type=internship" class="text-white stretched-link small">Review →</a>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card glass-card text-white border-start border-info border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="card-title">Pending Jobs</h6>
                            <h3 class="mb-0"><?php echo $stats['pending_jobs']; ?></h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-briefcase fa-2x text-info"></i>
                        </div>
                    </div>
                    <a href="applications.php?type=job" class="text-white stretched-link small">Review →</a>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card glass-card text-white border-start border-danger border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="card-title">Pending Complaints</h6>
                            <h3 class="mb-0"><?php echo $stats['pending_complaints']; ?></h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                    </div>
                    <a href="complaints_view.php" class="text-white stretched-link small">Handle →</a>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card glass-card text-white border-start border-secondary border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="card-title">Support Messages</h6>
                            <h3 class="mb-0"><?php echo $stats['new_support_messages']; ?></h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-headset fa-2x text-secondary"></i>
                        </div>
                    </div>
                    <a href="support_messages.php" class="text-white stretched-link small">Respond →</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- System Overview -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>System Overview</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3">
                                <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                                <h4><?php echo $system_stats['active_users']; ?></h4>
                                <p class="mb-0 text-muted">Active Users</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3">
                                <i class="fas fa-user-graduate fa-2x text-info mb-2"></i>
                                <h4><?php echo $system_stats['upcoming_interns']; ?></h4>
                                <p class="mb-0 text-muted">Upcoming Interns</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3">
                                <i class="fas fa-tasks fa-2x text-warning mb-2"></i>
                                <h4><?php echo $system_stats['in_progress_complaints']; ?></h4>
                                <p class="mb-0 text-muted">Complaints in Progress</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-4">
                        <h6 class="mb-3">Quick Actions</h6>
                        <div class="row g-2">
                            <div class="col-sm-6 col-md-3">
                                <a href="announcements.php" class="btn btn-outline-primary w-100 text-start">
                                    <i class="fas fa-bullhorn me-2"></i>Post Announcement
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <a href="staff.php?action=add" class="btn btn-outline-success w-100 text-start">
                                    <i class="fas fa-user-plus me-2"></i>Add Staff
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <a href="export_csv.php" class="btn btn-outline-info w-100 text-start">
                                    <i class="fas fa-download me-2"></i>Export Data
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <a href="reports.php" class="btn btn-outline-warning w-100 text-start">
                                    <i class="fas fa-chart-pie me-2"></i>View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent System Activities</h5>
                </div>
                <div class="card-body">
                    <?php if (count($activities) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Activity</th>
                                        <th>Description</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($activity['activity_type']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                        <td>
                                            <small class="text-muted"><?php echo HelperFunctions::formatDate($activity['created_at']); ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No recent activities.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4 mb-4">
            <!-- System Status -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-server me-2"></i>System Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Database</span>
                            <span class="badge bg-success">Online</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: 100%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Web Server</span>
                            <span class="badge bg-success">Online</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: 100%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Chat Service</span>
                            <span class="badge bg-warning">Maintenance</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-warning" style="width: 75%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>File Upload</span>
                            <span class="badge bg-success">Online</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Summary -->
            <div class="card mt-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Today's Summary</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>New Announcements</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['today_announcements']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>User Registrations</span>
                            <span class="badge bg-success rounded-pill">
                                <?php
                                $today_users_sql = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()";
                                $today_users_stmt = $conn->prepare($today_users_sql);
                                $today_users_stmt->execute();
                                echo $today_users_stmt->fetch()['count'];
                                ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>New Complaints</span>
                            <span class="badge bg-danger rounded-pill">
                                <?php
                                $today_complaints_sql = "SELECT COUNT(*) as count FROM complaints WHERE DATE(created_at) = CURDATE()";
                                $today_complaints_stmt = $conn->prepare($today_complaints_sql);
                                $today_complaints_stmt->execute();
                                echo $today_complaints_stmt->fetch()['count'];
                                ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Support Messages</span>
                            <span class="badge bg-info rounded-pill">
                                <?php
                                $today_support_sql = "SELECT COUNT(*) as count FROM support_messages WHERE DATE(created_at) = CURDATE()";
                                $today_support_stmt = $conn->prepare($today_support_sql);
                                $today_support_stmt->execute();
                                echo $today_support_stmt->fetch()['count'];
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Tools -->
            <div class="card mt-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Admin Tools</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../config/backup.php" class="btn btn-outline-primary text-start">
                            <i class="fas fa-database me-2"></i>Database Backup
                        </a>
                        <a href="system_logs.php" class="btn btn-outline-info text-start">
                            <i class="fas fa-file-alt me-2"></i>System Logs
                        </a>
                        <a href="user_management.php" class="btn btn-outline-warning text-start">
                            <i class="fas fa-cog me-2"></i>User Management
                        </a>
                        <a href="settings.php" class="btn btn-outline-success text-start">
                            <i class="fas fa-sliders-h me-2"></i>System Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>
