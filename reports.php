<?php
require_once '../includes/auth_check.php';
if (!HelperFunctions::isAdmin()) {
    HelperFunctions::redirect('../user/dashboard.php');
}
$page_title = "Reports & Analytics";
include '../includes/header.php';

$database = new Database();
$conn = $database->getConnection();

// Get date range for reports
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

// User Registration Report
$user_sql = "SELECT 
                COUNT(*) as total_users,
                SUM(DATE(created_at) BETWEEN ? AND ?) as period_users,
                SUM(user_type = 'public') as public_users,
                SUM(user_type = 'staff') as staff_users,
                SUM(status = 'active') as active_users,
                SUM(status = 'inactive') as inactive_users
             FROM users";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->execute([$start_date, $end_date]);
$user_stats = $user_stmt->fetch();

// Application Reports
$app_sql = "SELECT 
                COUNT(*) as total_apps,
                SUM(DATE(applied_at) BETWEEN ? AND ?) as period_apps,
                SUM(status = 'pending') as pending_apps,
                SUM(status = 'approved') as approved_apps,
                SUM(status = 'rejected') as rejected_apps
             FROM internship_applications";
$app_stmt = $conn->prepare($app_sql);
$app_stmt->execute([$start_date, $end_date]);
$internship_stats = $app_stmt->fetch();

$job_sql = "SELECT 
                COUNT(*) as total_jobs,
                SUM(DATE(applied_at) BETWEEN ? AND ?) as period_jobs,
                SUM(status = 'pending') as pending_jobs,
                SUM(status = 'shortlisted') as shortlisted_jobs,
                SUM(status = 'hired') as hired_jobs,
                SUM(status = 'rejected') as rejected_jobs
             FROM job_applications";
$job_stmt = $conn->prepare($job_sql);
$job_stmt->execute([$start_date, $end_date]);
$job_stats = $job_stmt->fetch();

// Complaint Reports
$complaint_sql = "SELECT 
                    COUNT(*) as total_complaints,
                    SUM(DATE(created_at) BETWEEN ? AND ?) as period_complaints,
                    SUM(status = 'submitted') as submitted_complaints,
                    SUM(status = 'in_progress') as progress_complaints,
                    SUM(status = 'resolved') as resolved_complaints,
                    SUM(status = 'closed') as closed_complaints,
                    SUM(priority = 'urgent') as urgent_complaints,
                    SUM(priority = 'high') as high_complaints
                 FROM complaints";
$complaint_stmt = $conn->prepare($complaint_sql);
$complaint_stmt->execute([$start_date, $end_date]);
$complaint_stats = $complaint_stmt->fetch();

// Department-wise Applications
$dept_sql = "SELECT department, COUNT(*) as count 
             FROM internship_applications 
             WHERE DATE(applied_at) BETWEEN ? AND ?
             GROUP BY department 
             ORDER BY count DESC";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->execute([$start_date, $end_date]);
$dept_stats = $dept_stmt->fetchAll();

// Monthly User Registration Trend
$monthly_sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM users 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 6";
$monthly_stmt = $conn->prepare($monthly_sql);
$monthly_stmt->execute();
$monthly_stats = $monthly_stmt->fetchAll();

// Staff Performance
$staff_sql = "SELECT 
                u.first_name, u.last_name, s.department,
                COUNT(DISTINCT ia.id) as internship_reviews,
                COUNT(DISTINCT ja.id) as job_reviews,
                COUNT(DISTINCT c.id) as complaints_handled
              FROM staff s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN internship_applications ia ON s.id = ia.reviewed_by
              LEFT JOIN job_applications ja ON s.id = ja.reviewed_by
              LEFT JOIN complaints c ON s.id = c.assigned_to
              WHERE s.is_active = TRUE
              GROUP BY s.id, u.first_name, u.last_name, s.department
              ORDER BY (internship_reviews + job_reviews + complaints_handled) DESC";
$staff_stmt = $conn->prepare($staff_sql);
$staff_stmt->execute();
$staff_performance = $staff_stmt->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-chart-line me-2"></i>Reports & Analytics</h4>
                </div>
                <div class="card-body">
                    <!-- Date Range Filter -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-5">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo $start_date; ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo $end_date; ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="btn-group mt-3">
                                <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" 
                                   class="btn btn-outline-primary">This Month</a>
                                <a href="?start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" 
                                   class="btn btn-outline-primary">Last 30 Days</a>
                                <a href="?start_date=<?php echo date('Y-01-01'); ?>&end_date=<?php echo date('Y-12-31'); ?>" 
                                   class="btn btn-outline-primary">This Year</a>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body text-center">
                                    <h3><?php echo $user_stats['period_users']; ?></h3>
                                    <p class="mb-0">New Users</p>
                                    <small>Total: <?php echo $user_stats['total_users']; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card text-white bg-success">
                                <div class="card-body text-center">
                                    <h3><?php echo $internship_stats['period_apps'] + $job_stats['period_jobs']; ?></h3>
                                    <p class="mb-0">New Applications</p>
                                    <small>Total: <?php echo $internship_stats['total_apps'] + $job_stats['total_jobs']; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card text-white bg-warning">
                                <div class="card-body text-center">
                                    <h3><?php echo $complaint_stats['period_complaints']; ?></h3>
                                    <p class="mb-0">New Complaints</p>
                                    <small>Total: <?php echo $complaint_stats['total_complaints']; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card text-white bg-info">
                                <div class="card-body text-center">
                                    <h3><?php echo $complaint_stats['resolved_complaints']; ?></h3>
                                    <p class="mb-0">Resolved Complaints</p>
                                    <small>Rate: <?php echo $complaint_stats['period_complaints'] > 0 ? 
                                        round(($complaint_stats['resolved_complaints'] / $complaint_stats['period_complaints']) * 100, 1) : 0; ?>%</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Reports -->
                    <div class="row">
                        <!-- User Statistics -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>User Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <h4><?php echo $user_stats['public_users']; ?></h4>
                                            <small class="text-muted">Public Users</small>
                                        </div>
                                        <div class="col-4">
                                            <h4><?php echo $user_stats['staff_users']; ?></h4>
                                            <small class="text-muted">Staff Users</small>
                                        </div>
                                        <div class="col-4">
                                            <h4><?php echo $user_stats['active_users']; ?></h4>
                                            <small class="text-muted">Active Users</small>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <canvas id="userChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Application Statistics -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Application Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <h5><?php echo $internship_stats['period_apps']; ?></h5>
                                            <small class="text-muted">Internship Apps</small>
                                        </div>
                                        <div class="col-6">
                                            <h5><?php echo $job_stats['period_jobs']; ?></h5>
                                            <small class="text-muted">Job Apps</small>
                                        </div>
                                    </div>
                                    <canvas id="applicationChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Complaint Statistics -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Complaint Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <h5><?php echo $complaint_stats['urgent_complaints']; ?></h5>
                                            <small class="text-muted">Urgent</small>
                                        </div>
                                        <div class="col-4">
                                            <h5><?php echo $complaint_stats['resolved_complaints']; ?></h5>
                                            <small class="text-muted">Resolved</small>
                                        </div>
                                        <div class="col-4">
                                            <h5><?php echo $complaint_stats['progress_complaints']; ?></h5>
                                            <small class="text-muted">In Progress</small>
                                        </div>
                                    </div>
                                    <canvas id="complaintChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Department-wise Applications -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>Applications by Department</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Department</th>
                                                    <th>Applications</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total_dept_apps = array_sum(array_column($dept_stats, 'count'));
                                                foreach ($dept_stats as $dept): 
                                                    $percentage = $total_dept_apps > 0 ? round(($dept['count'] / $total_dept_apps) * 100, 1) : 0;
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                                    <td><?php echo $dept['count']; ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar" role="progressbar" 
                                                                 style="width: <?php echo $percentage; ?>%"
                                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                                 aria-valuemin="0" aria-valuemax="100">
                                                                <?php echo $percentage; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Performance -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Staff Performance</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Staff Member</th>
                                                    <th>Department</th>
                                                    <th>Internship Reviews</th>
                                                    <th>Job Reviews</th>
                                                    <th>Complaints Handled</th>
                                                    <th>Total Activities</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($staff_performance as $staff): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($staff['department']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $staff['internship_reviews']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo $staff['job_reviews']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $staff['complaints_handled']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo $staff['internship_reviews'] + $staff['job_reviews'] + $staff['complaints_handled']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Options -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0"><i class="fas fa-download me-2"></i>Export Reports</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <a href="export_csv.php?type=users&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                               class="btn btn-outline-primary w-100">
                                                <i class="fas fa-users me-2"></i>User Report
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="export_csv.php?type=applications&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                               class="btn btn-outline-success w-100">
                                                <i class="fas fa-briefcase me-2"></i>Application Report
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="export_csv.php?type=complaints&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                               class="btn btn-outline-warning w-100">
                                                <i class="fas fa-exclamation-triangle me-2"></i>Complaint Report
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="export_csv.php?type=staff&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                               class="btn btn-outline-info w-100">
                                                <i class="fas fa-user-tie me-2"></i>Staff Report
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// User Registration Chart
const userCtx = document.getElementById('userChart').getContext('2d');
const userChart = new Chart(userCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($monthly_stats, 'month')); ?>,
        datasets: [{
            label: 'User Registrations',
            data: <?php echo json_encode(array_column($monthly_stats, 'count')); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Application Status Chart
const appCtx = document.getElementById('applicationChart').getContext('2d');
const applicationChart = new Chart(appCtx, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Approved/Shortlisted', 'Rejected'],
        datasets: [{
            data: [
                <?php echo $internship_stats['pending_apps'] + $job_stats['pending_jobs']; ?>,
                <?php echo $internship_stats['approved_apps'] + $job_stats['shortlisted_jobs'] + $job_stats['hired_jobs']; ?>,
                <?php echo $internship_stats['rejected_apps'] + $job_stats['rejected_jobs']; ?>
            ],
            backgroundColor: [
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(255, 99, 132, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true
    }
});

// Complaint Status Chart
const complaintCtx = document.getElementById('complaintChart').getContext('2d');
const complaintChart = new Chart(complaintCtx, {
    type: 'pie',
    data: {
        labels: ['Submitted', 'In Progress', 'Resolved', 'Closed'],
        datasets: [{
            data: [
                <?php echo $complaint_stats['submitted_complaints']; ?>,
                <?php echo $complaint_stats['progress_complaints']; ?>,
                <?php echo $complaint_stats['resolved_complaints']; ?>,
                <?php echo $complaint_stats['closed_complaints']; ?>
            ],
            backgroundColor: [
                'rgba(255, 206, 86, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true
    }
});

// Print report function
function printReport() {
    window.print();
}

// Auto-refresh reports every 5 minutes
setTimeout(() => {
    window.location.reload();
}, 300000);
</script>

<style>
@media print {
    .navbar, .card-header .btn, .btn-group, .form-control, .btn {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
    }
    
    .card-header {
        background: #fff !important;
        color: #000 !important;
        border-bottom: 2px solid #000 !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>