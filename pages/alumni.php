<?php
require_once '../config/database.php';
requireLogin();

$message = '';
$error = '';

// Handle filter form submission
$department_filter = isset($_GET['department']) ? trim($_GET['department']) : '';
$grad_year_filter = isset($_GET['grad_year']) ? trim($_GET['grad_year']) : '';

$alumni_query = "SELECT p.person_id, p.first_name, p.last_name, p.department, a.grad_year 
                 FROM person p 
                 INNER JOIN alumni a ON p.person_id = a.person_id 
                 WHERE 1=1";
$params = [];

if ($department_filter) {
    $alumni_query .= " AND p.department LIKE ?";
    $params[] = "%$department_filter%";
}
if ($grad_year_filter) {
    $alumni_query .= " AND a.grad_year = ?";
    $params[] = $grad_year_filter;
}

$alumni_query .= " ORDER BY p.last_name, p.first_name";
$alumni_stmt = executeQuery($alumni_query, $params);
$alumni = $alumni_stmt ? $alumni_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get distinct departments and graduation years for filters
$dept_query = "SELECT DISTINCT department FROM person WHERE department IS NOT NULL ORDER BY department";
$dept_stmt = executeQuery($dept_query, []);
$departments = $dept_stmt ? $dept_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

$year_query = "SELECT DISTINCT grad_year FROM alumni ORDER BY grad_year DESC";
$year_stmt = executeQuery($year_query, []);
$grad_years = $year_stmt ? $year_stmt->fetchAll(PDO::FETCH_COLUMN) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Directory - Alumni Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
                <h2><i class="fas fa-users me-2"></i>Alumni Directory</h2>
            </div>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Alumni</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" 
                                            <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="grad_year" class="form-label">Graduation Year</label>
                            <select class="form-select" name="grad_year">
                                <option value="">All Years</option>
                                <?php foreach ($grad_years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year); ?>" 
                                            <?php echo $grad_year_filter === $year ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Alumni List -->
            <?php if (empty($alumni)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-4"></i>
                    <h4>No Alumni Found</h4>
                    <p class="text-muted">Try adjusting the filters to find alumni.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($alumni as $alum): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="profile-avatar me-3 d-flex align-items-center justify-content-center bg-white">
                                            <i class="fas fa-user fa-2x text-muted"></i>
                                        </div>
                                        <div>
                                            <h6 class="card-title mb-0">
                                                <?php echo htmlspecialchars($alum['first_name'] . ' ' . $alum['last_name']); ?>
                                            </h6>
                                            <p class="card-text text-muted mb-0">
                                                <?php echo htmlspecialchars($alum['department'] ?: 'Department not specified'); ?>
                                            </p>
                                            <small class="text-muted">Class of <?php echo htmlspecialchars($alum['grad_year']); ?></small>
                                        </div>
                                    </div>
                                    <a href="view_profile.php?person_id=<?php echo $alum['person_id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>View Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/custom.js"></script>
</body>
</html>