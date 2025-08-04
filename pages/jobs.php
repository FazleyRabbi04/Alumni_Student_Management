```php
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

// Get all jobs with poster information and contact details
$jobs_query = "SELECT j.*, p.first_name, p.last_name, p.department,
               (SELECT COUNT(*) FROM posts po WHERE po.job_id = j.job_id) as applicant_count,
               (SELECT GROUP_CONCAT(email) FROM email_address e WHERE e.person_id = j.person_id) as emails,
               (SELECT GROUP_CONCAT(phone_number) FROM person_phone ph WHERE ph.person_id = j.person_id) as phones
               FROM job j 
               JOIN person p ON j.person_id = p.person_id 
               ORDER BY j.post_date DESC";
$jobs_stmt = executeQuery($jobs_query);
$jobs = $jobs_stmt ? $jobs_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get user's posted jobs with contact details
$my_jobs_query = "SELECT j.*, 
                  (SELECT COUNT(*) FROM posts po WHERE po.job_id = j.job_id AND po.person_id != j.person_id) as applicant_count,
                  (SELECT GROUP_CONCAT(email) FROM email_address e WHERE e.person_id = j.person_id) as emails,
                  (SELECT GROUP_CONCAT(phone_number) FROM person_phone ph WHERE ph.person_id = j.person_id) as phones
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
    <link href="../assets/css/custom.css" rel="stylesheet">
    <style>
        .contact-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .contact-list li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
<?php 
if (file_exists('../includes/sidebar.php')) {
    include '../includes/sidebar.php';
} else {
    echo '<div class="alert alert-danger">Error: Sidebar file not found. Please check the file path.</div>';
}
?>

<div class="container-fluid">
    <div class="row">
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
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
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
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
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="jobSearch" placeholder="Search jobs by title, company, or location...">
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
                        <div class="row" id="jobListings">
                            <?php if (empty($jobs)): ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="fas fa-briefcase fa-4x text-muted mb-4"></i>
                                        <h4>No Job Postings</h4>
                                        <p class="text-muted">Be the first to post a job opportunity!</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($jobs as $job): ?>
                                    <div class="col-lg-6 mb-4 job-card"
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
                            <div class="text-center py-5">
                                <i class="fas fa-briefcase fa-4x text-muted mb-4"></i>
                                <h4>No Jobs Posted</h4>
                                <p class="text-muted">You haven't posted any jobs yet. Start by posting your first job opportunity!</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#postJobModal">
                                    <i class="fas fa-plus me-1"></i>Post Your First Job
                                </button>
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

<!-- Post Job Modal -->
<div class="modal fade" id="postJobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Post New Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="job_title" class="form-label">Job Title *</label>
                            <input type="text" class="form-control" name="job_title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="company" class="form-label">Company *</label>
                            <input type="text" class="form-control" name="company" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Location *</label>
                        <input type="text" class="form-control" name="location" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Job Description</label>
                        <textarea class="form-control" name="description" rows="5"
                                  placeholder="Describe the job role, requirements, benefits, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="post_job" class="btn btn-primary">Post Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Job Modal -->
<div class="modal fade" id="editJobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="job_id" id="editJobId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_job_title" class="form-label">Job Title *</label>
                            <input type="text" class="form-control" name="job_title" id="edit_job_title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_company" class="form-label">Company *</label>
                            <input type="text" class="form-control" name="company" id="edit_company" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_location" class="form-label">Location *</label>
                        <input type="text" class="form-control" name="location" id="edit_location" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Job Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="5"
                                  placeholder="Describe the job role, requirements, benefits, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_job" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Job Confirmation Modal -->
<div class="modal fade" id="deleteJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="job_id" id="deleteJobId">
                    <p>Are you sure you want to delete the job "<span id="deleteJobTitle"></span>"? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_job" class="btn btn-danger">Delete Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Job Detail Modal -->
<div class="modal fade" id="jobDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="jobDetailTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong><i class="fas fa-building me-2"></i>Company:</strong>
                        <p id="jobDetailCompany"></p>
                    </div>
                    <div class="col-md-6">
                        <strong><i class="fas fa-map-marker-alt me-2"></i>Location:</strong>
                        <p id="jobDetailLocation"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong><i class="fas fa-user me-2"></i>Posted by:</strong>
                        <p id="jobDetailPoster"></p>
                    </div>
                    <div class="col-md-6">
                        <strong><i class="fas fa-calendar me-2"></i>Posted on:</strong>
                        <p id="jobDetailDate"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong><i class="fas fa-envelope me-2"></i>Contact Emails:</strong>
                        <ul class="contact-list" id="jobDetailEmails"></ul>
                    </div>
                    <div class="col-md-6">
                        <strong><i class="fas fa-phone me-2"></i>Contact Phones:</strong>
                        <ul class="contact-list" id="jobDetailPhones"></ul>
                    </div>
                </div>
                <div class="mb-3">
                    <strong><i class="fas fa-align-left me-2"></i>Description:</strong>
                    <div id="jobDetailDescription" class="mt-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-1"></i>Express Interest
                </button>
            </div>
        </div>
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

            // Handle emails
            const emailList = document.getElementById('jobDetailEmails');
            emailList.innerHTML = '';
            if (jobData.emails) {
                const emails = jobData.emails.split(',');
                emails.forEach(email => {
                    const li = document.createElement('li');
                    li.textContent = email;
                    emailList.appendChild(li);
                });
            } else {
                const li = document.createElement('li');
                li.textContent = 'No emails provided';
                li.classList.add('text-muted');
                emailList.appendChild(li);
            }

            // Handle phones
            const phoneList = document.getElementById('jobDetailPhones');
            phoneList.innerHTML = '';
            if (jobData.phones) {
                const phones = jobData.phones.split(',');
                phones.forEach(phone => {
                    const li = document.createElement('li');
                    li.textContent = phone;
                    phoneList.appendChild(li);
                });
            } else {
                const li = document.createElement('li');
                li.textContent = 'No phone numbers provided';
                li.classList.add('text-muted');
                phoneList.appendChild(li);
            }

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
```