<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle job posting
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_job'])) {
    $job_title = trim($_POST['job_title']);
    $company = trim($_POST['company']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    if (!empty($job_title) && !empty($company) && !empty($location)) {
        $job_query = "INSERT INTO job (job_title, company, location, description, person_id) 
                      VALUES (?, ?, ?, ?, ?)";
        if (executeQuery($job_query, [$job_title, $company, $location, $description, $user_id])) {
            $job_id = getLastInsertId();
            $posts_query = "INSERT INTO posts (person_id, job_id) VALUES (?, ?)";
            executeQuery($posts_query, [$user_id, $job_id]);
            $message = 'Job posted successfully!';
        } else {
            $error = 'Failed to post job.';
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Handle job editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_job'])) {
    $job_id = $_POST['job_id'];
    $job_title = trim($_POST['job_title']);
    $company = trim($_POST['company']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    if (!empty($job_title) && !empty($company) && !empty($location)) {
        // Verify user owns the job
        $check_query = "SELECT person_id FROM job WHERE job_id = ? AND person_id = ?";
        $check_stmt = executeQuery($check_query, [$job_id, $user_id]);
        if ($check_stmt && $check_stmt->rowCount() > 0) {
            $update_query = "UPDATE job SET job_title = ?, company = ?, location = ?, description = ? WHERE job_id = ?";
            if (executeQuery($update_query, [$job_title, $company, $location, $description, $job_id])) {
                $message = 'Job updated successfully!';
            } else {
                $error = 'Failed to update job.';
            }
        } else {
            $error = 'Unauthorized: You can only edit your own jobs.';
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Handle job deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_job'])) {
    $job_id = $_POST['job_id'];
    // Verify user owns the job
    $check_query = "SELECT person_id FROM job WHERE job_id = ? AND person_id = ?";
    $check_stmt = executeQuery($check_query, [$job_id, $user_id]);
    if ($check_stmt && $check_stmt->rowCount() > 0) {
        // Delete associated posts first (due to foreign key constraints)
        $delete_posts_query = "DELETE FROM posts WHERE job_id = ?";
        if (executeQuery($delete_posts_query, [$job_id])) {
            // Delete the job
            $delete_job_query = "DELETE FROM job WHERE job_id = ?";
            if (executeQuery($delete_job_query, [$job_id])) {
                $message = 'Job deleted successfully!';
            } else {
                $error = 'Failed to delete job.';
            }
        } else {
            $error = 'Failed to delete associated posts.';
        }
    } else {
        $error = 'Unauthorized: You can only delete your own jobs.';
    }
}

// Get all jobs with poster information
$jobs_query = "SELECT j.*, p.first_name, p.last_name, p.department,
               (SELECT COUNT(*) FROM posts po WHERE po.job_id = j.job_id) as applicant_count
               FROM job j 
               JOIN person p ON j.person_id = p.person_id 
               ORDER BY j.post_date DESC";
$jobs_stmt = executeQuery($jobs_query);
$jobs = $jobs_stmt ? $jobs_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get user's posted jobs
$my_jobs_query = "SELECT j.*, 
                  (SELECT COUNT(*) FROM posts po WHERE po.job_id = j.job_id AND po.person_id != j.person_id) as applicant_count
                  FROM job j 
                  WHERE j.person_id = ? 
                  ORDER BY j.post_date DESC";
$my_jobs_stmt = executeQuery($my_jobs_query, [$user_id]);
$my_jobs = $my_jobs_stmt ? $my_jobs_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get jobs statistics
$stats_query = "SELECT 
    COUNT(*) as total_jobs,
    COUNT(CASE WHEN post_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as weekly_jobs,
    COUNT(CASE WHEN post_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as monthly_jobs
    FROM job";
$stats_stmt = executeQuery($stats_query);
$stats = $stats_stmt ? $stats_stmt->fetch(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Board - Alumni Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #F9FAFB;
            color: #1F2937;
            margin: 0;
        }
        .container-fluid {
            padding-top: 20px;
            padding-bottom: 40px;
            width: 100%;
            max-width: none;
        }
        .h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1E3A8A;
        }
        .dashboard-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .dashboard-card .card-body {
            background: linear-gradient(135deg, #1E3A8A, #2563EB);
            color: #ffffff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .dashboard-card.success .card-body,
        .dashboard-card.warning .card-body {
            background: linear-gradient(135deg, #1E3A8A, #2563EB);
            color: #ffffff;
        }
        /* FIXED TAB COLORS */
        .nav-tabs {
            border-bottom: 1.5px solid #e5e7eb !important;
        }
        .nav-tabs .nav-link {
            color: #1F2937 !important;
            background: #fff !important;
            font-weight: 600;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 10px 20px;
            transition: color 0.2s, border-bottom 0.2s;
        }
        .nav-tabs .nav-link.active {
            color: #1E3A8A !important;
            background: #fff !important;
            border-bottom: 3px solid #1E3A8A !important;
            font-weight: 700;
        }
        /* All job and my job content - dark text, white background */
        .job-card .card,
        .job-card .card-body,
        .job-card .card-title,
        .job-card .card-text,
        .job-card .fw-bold,
        .job-card .d-flex,
        .job-card small,
        .job-card span,
        .job-card p,
        .table,
        .table th,
        .table td {
            color: #1F2937 !important;
            background: #fff !important;
        }
        .table-hover tbody tr:hover {
            background-color: #F3F4F6 !important;
        }
        .job-card .card {
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            transition: box-shadow 0.2s ease;
        }
        .job-card .card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .job-card .card-body {
            padding: 20px;
        }
        .job-empty-state {
            max-width: 460px;
            margin: 36px auto;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 18px 0 rgba(0,0,0,0.04);
            padding: 48px 30px;
        }
        .job-empty-state h4 {
            color: #1F2937;
            margin-top: 0.5rem;
        }
        .job-empty-state p {
            color: #4B5563;
            margin-bottom: 0.5rem;
        }
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            background: #1E3A8A;
            color: #FFFFFF;
            border-bottom: 1px solid #E5E7EB;
        }
        .modal-body {
            padding: 25px;
        }
        .form-label {
            font-weight: 600;
            color: #1F2937;
        }
        .form-control {
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            padding: 10px;
        }
        .form-control:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .alert {
            border-radius: 8px;
            padding: 15px;
        }
        .badge {
            border-radius: 10px;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container-fluid px-0">
    <div class="row">
        <main class="col-12 px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-briefcase me-2"></i>Job Board
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#postJobModal">
                        <i class="fas fa-plus me-1"></i>Post Job
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body text-center">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Total Jobs
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">
                                        <?php echo $stats['total_jobs'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-briefcase fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card success">
                        <div class="card-body text-center">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        This Week
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">
                                        <?php echo $stats['weekly_jobs'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-week fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card warning">
                        <div class="card-body text-center">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        This Month
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">
                                        <?php echo $stats['monthly_jobs'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs" id="jobsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-jobs-tab" data-bs-toggle="tab" data-bs-target="#all-jobs" type="button" role="tab">
                        <i class="fas fa-list me-1"></i>All Jobs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="my-jobs-tab" data-bs-toggle="tab" data-bs-target="#my-jobs" type="button" role="tab">
                        <i class="fas fa-user-tie me-1"></i>My Posted Jobs
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="jobsTabContent">
                <!-- All Jobs Tab -->
                <div class="tab-pane fade show active" id="all-jobs" role="tabpanel">
                    <div class="mt-4">
                        <!-- Search and Filter -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control border-start-0" id="jobSearch" placeholder="Search jobs by title, company, or location...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="locationFilter">
                                    <option value="">All Locations</option>
                                    <?php
                                    $locations_query = "SELECT DISTINCT location FROM job ORDER BY location";
                                    $locations_stmt = executeQuery($locations_query);
                                    if ($locations_stmt) {
                                        while ($location = $locations_stmt->fetch(PDO::FETCH_COLUMN)) {
                                            echo '<option value="' . htmlspecialchars($location) . '">' . htmlspecialchars($location) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Job Listings -->
                        <div class="row row-cols-1 row-cols-md-2 g-4" id="jobListings">
                            <?php if (empty($jobs)): ?>
                                <div class="col-12 d-flex justify-content-center align-items-center" style="min-height:340px;">
                                    <div class="job-empty-state text-center w-100">
                                        <i class="fas fa-briefcase fa-4x text-muted mb-4"></i>
                                        <h4>No Job Postings</h4>
                                        <p>Be the first to post a job opportunity!</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($jobs as $job): ?>
                                    <div class="col job-card"
                                         data-title="<?php echo strtolower($job['job_title']); ?>"
                                         data-company="<?php echo strtolower($job['company']); ?>"
                                         data-location="<?php echo strtolower($job['location']); ?>">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($job['job_title']); ?></h5>
                                                    <span class="badge bg-primary">
                                                        <?php echo date('M j', strtotime($job['post_date'])); ?>
                                                    </span>
                                                </div>
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-building text-primary me-2"></i>
                                                        <span class="fw-bold"><?php echo htmlspecialchars($job['company']); ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                        <span><?php echo htmlspecialchars($job['location']); ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-user text-primary me-2"></i>
                                                        <span>Posted by <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></span>
                                                        <?php if ($job['department']): ?>
                                                            <small class="text-muted ms-2">(<?php echo htmlspecialchars($job['department']); ?>)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if ($job['description']): ?>
                                                    <p class="card-text">
                                                        <?php echo htmlspecialchars(substr($job['description'], 0, 150)); ?>
                                                        <?php if (strlen($job['description']) > 150): ?>...<?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <button type="button" class="btn btn-primary btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#jobDetailModal"
                                                            data-job='<?php echo json_encode($job); ?>'>
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i><?php echo $job['applicant_count']; ?> interested
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- My Jobs Tab -->
                <div class="tab-pane fade" id="my-jobs" role="tabpanel">
                    <div class="mt-4">
                        <?php if (empty($my_jobs)): ?>
                            <div class="d-flex justify-content-center align-items-center" style="min-height:240px;">
                                <div class="job-empty-state text-center w-100">
                                    <i class="fas fa-briefcase fa-4x text-muted mb-4"></i>
                                    <h4>No Jobs Posted</h4>
                                    <p>You haven't posted any jobs yet. Start by posting your first job opportunity!</p>
                                    <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#postJobModal">
                                        <i class="fas fa-plus me-1"></i>Post Your First Job
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Company</th>
                                        <th>Location</th>
                                        <th>Posted Date</th>
                                        <th>Applicants</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($my_jobs as $job): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($job['job_title']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($job['company']); ?></td>
                                            <td><?php echo htmlspecialchars($job['location']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($job['post_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $job['applicant_count']; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" title="View Details"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#jobDetailModal"
                                                            data-job='<?php echo json_encode($job); ?>'>
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-success" title="Edit Job"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editJobModal"
                                                            data-job='<?php echo json_encode($job); ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" title="Delete Job"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteJobModal"
                                                            data-job-id="<?php echo $job['job_id']; ?>"
                                                            data-job-title="<?php echo htmlspecialchars($job['job_title']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Job search functionality
        const searchInput = document.getElementById('jobSearch');
        const locationFilter = document.getElementById('locationFilter');
        const jobCards = document.querySelectorAll('.job-card');

        function filterJobs() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedLocation = locationFilter.value.toLowerCase();

            jobCards.forEach(card => {
                const title = card.dataset.title;
                const company = card.dataset.company;
                const location = card.dataset.location;

                const matchesSearch = !searchTerm ||
                    title.includes(searchTerm) ||
                    company.includes(searchTerm) ||
                    location.includes(searchTerm);

                const matchesLocation = !selectedLocation || location.includes(selectedLocation);

                if (matchesSearch && matchesLocation) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterJobs);
        locationFilter.addEventListener('change', filterJobs);

        // Job detail modal
        const jobDetailModal = document.getElementById('jobDetailModal');
        jobDetailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const jobData = JSON.parse(button.getAttribute('data-job'));

            document.getElementById('jobDetailTitle').textContent = jobData.job_title;
            document.getElementById('jobDetailCompany').textContent = jobData.company;
            document.getElementById('jobDetailLocation').textContent = jobData.location;
            document.getElementById('jobDetailPoster').textContent = jobData.first_name + ' ' + jobData.last_name;
            document.getElementById('jobDetailDate').textContent = new Date(jobData.post_date).toLocaleDateString();

            const description = jobData.description || 'No description provided.';
            document.getElementById('jobDetailDescription').innerHTML = description.replace(/\n/g, '<br>');
        });

        // Edit job modal
        const editJobModal = document.getElementById('editJobModal');
        editJobModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const jobData = JSON.parse(button.getAttribute('data-job'));

            document.getElementById('editJobId').value = jobData.job_id;
            document.getElementById('edit_job_title').value = jobData.job_title;
            document.getElementById('edit_company').value = jobData.company;
            document.getElementById('edit_location').value = jobData.location;
            document.getElementById('edit_description').value = jobData.description || '';
        });

        // Delete job modal
        const deleteJobModal = document.getElementById('deleteJobModal');
        deleteJobModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const jobId = button.getAttribute('data-job-id');
            const jobTitle = button.getAttribute('data-job-title');

            document.getElementById('deleteJobId').value = jobId;
            document.getElementById('deleteJobTitle').textContent = jobTitle;
        });

        // Animate counters in statistics cards
        const counters = document.querySelectorAll('.h4');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            let current = 0;
            const increment = target / 20;

            const updateCounter = () => {
                if (current < target) {
                    current += increment;
                    counter.textContent = Math.ceil(current);
                    setTimeout(updateCounter, 50);
                } else {
                    counter.textContent = target;
                }
            };

            updateCounter();
        });
    });
</script>
</body>
</html>
