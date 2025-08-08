<?php
require_once 'config/database.php';
startSecureSession();

$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
$message = '';
$error = '';

// Handle job posting
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_job']) && isLoggedIn()) {
    $job_title = trim($_POST['job_title']);
    $company = trim($_POST['company']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    if (!empty($job_title) && !empty($company) && !empty($location)) {
        $job_query = "INSERT INTO job (job_title, company, location, description, person_id) VALUES (?, ?, ?, ?, ?)";
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_job']) && isLoggedIn()) {
    $job_id = $_POST['job_id'];
    $job_title = trim($_POST['job_title']);
    $company = trim($_POST['company']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    if (!empty($job_title) && !empty($company) && !empty($location)) {
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_job']) && isLoggedIn()) {
    $job_id = $_POST['job_id'];
    $check_query = "SELECT person_id FROM job WHERE job_id = ? AND person_id = ?";
    $check_stmt = executeQuery($check_query, [$job_id, $user_id]);
    if ($check_stmt && $check_stmt->rowCount() > 0) {
        $delete_posts_query = "DELETE FROM posts WHERE job_id = ?";
        if (executeQuery($delete_posts_query, [$job_id])) {
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

// Fetch recent jobs
$jobs_query = "SELECT j.job_id, j.job_title, j.company, j.location, j.post_date, p.first_name, p.last_name 
               FROM job j 
               JOIN person p ON j.person_id = p.person_id 
               ORDER BY j.post_date DESC 
               LIMIT 5";
$jobs_stmt = executeQuery($jobs_query);
$recent_jobs = $jobs_stmt ? $jobs_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Alumni Relationship & Networking System" />
    <title>Careers - Alumni Relationship & Networking System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&family=Roboto:wght@500;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />

    <!-- AOS Animation CSS -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f5f7fa;
            color: #002147;
        }

        .bg-navy {
            background-color: #002147;
        }

        .text-navy {
            color: #002147;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 0.5px;
        }

        .nav-link {
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #aad4ff !important;
        }

        .hero {
            background: linear-gradient(to right, #002147, #0077c8);
            color: #fff;
            padding: 60px 20px;
            text-align: center;
            position: relative;
        }

        .hero h1 {
            font-weight: 700;
            font-size: 2.75rem;
        }

        h2.section-title {
            font-weight: 700;
            font-family: 'Roboto', sans-serif;
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        .job-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 24px;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .management-section {
            margin-top: 40px;
        }

        #jobPostForm .form-label i {
            color: #002147;
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<?php if (isLoggedIn()): ?>
    <!-- Job Management Section for Logged-in Users -->
    <section class="py-5">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <h2 class="section-title text-navy" data-aos="fade-up">Manage Your Job Postings</h2>
            <div class="management-section" data-aos="fade-up" data-aos-delay="200">
                <form id="jobPostForm" method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jobTitle" class="form-label">
                                <i class="fas fa-briefcase me-2"></i>Job Title *
                            </label>
                            <input type="text" class="form-control" id="jobTitle" name="job_title" required
                                   placeholder="e.g., Software Engineer">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="company" class="form-label">
                                <i class="fas fa-building me-2"></i>Company *
                            </label>
                            <input type="text" class="form-control" id="company" name="company" required
                                   placeholder="e.g., TechCorp Inc.">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">
                                <i class="fas fa-map-marker-alt me-2"></i>Location *
                            </label>
                            <input type="text" class="form-control" id="location" name="location" required
                                   placeholder="e.g., Dhaka, Bangladesh">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left me-2"></i>Description
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Job description..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" name="post_job">Post Job</button>
                </form>
            </div>

            <!-- Job Listings for Management -->
            <?php if (!empty($recent_jobs)): ?>
                <h3 class="mt-5 text-navy" data-aos="fade-up">Your Recent Job Postings</h3>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" data-aos="fade-up" data-aos-delay="200">
                    <?php foreach ($recent_jobs as $job): ?>
                        <div class="col">
                            <div class="job-card">
                                <h5 class="card-title"><?php echo htmlspecialchars($job['job_title']); ?></h5>
                                <p class="card-text">
                                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($job['company']); ?><br>
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?><br>
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($job['post_date'])); ?>
                                </p>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editJobModal"
                                        data-job='<?php echo json_encode($job); ?>'>
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="btn btn-danger btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#deleteJobModal"
                                        data-job-id="<?php echo $job['job_id']; ?>" data-job-title="<?php echo htmlspecialchars($job['job_title']); ?>">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted mt-4" data-aos="fade-up">You have no job postings yet.</p>
            <?php endif; ?>

            <!-- Edit Job Modal -->
            <div class="modal fade" id="editJobModal" tabindex="-1" aria-labelledby="editJobModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editJobModalLabel">Edit Job Posting</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" action="">
                                <input type="hidden" id="editJobId" name="job_id">
                                <div class="mb-3">
                                    <label for="edit_job_title" class="form-label">Job Title *</label>
                                    <input type="text" class="form-control" id="edit_job_title" name="job_title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_company" class="form-label">Company *</label>
                                    <input type="text" class="form-control" id="edit_company" name="company" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_location" class="form-label">Location *</label>
                                    <input type="text" class="form-control" id="edit_location" name="location" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100" name="edit_job">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Job Modal -->
            <div class="modal fade" id="deleteJobModal" tabindex="-1" aria-labelledby="deleteJobModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteJobModalLabel">Delete Job</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete the job "<strong id="deleteJobTitle"></strong>"?</p>
                            <form method="POST" action="">
                                <input type="hidden" id="deleteJobId" name="job_id">
                                <button type="submit" class="btn btn-danger w-100" name="delete_job">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    </section>
<?php else: ?>
    <!-- Public Job Browsing Section -->
    <section class="hero" data-aos="fade-up">
        <div class="container">
            <h1 class="display-4">Explore Career Opportunities</h1>
            <p class="lead">Discover exciting job openings posted by our alumni network!</p>
            <a href="#jobs" class="btn btn-primary">View Jobs</a>
        </div>
    </section>

    <section class="py-5" id="jobs">
        <div class="container">
            <h2 class="section-title text-navy" data-aos="fade-up">Latest Job Opportunities</h2>
            <?php if (!empty($recent_jobs)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" data-aos="fade-up" data-aos-delay="200">
                    <?php foreach ($recent_jobs as $job): ?>
                        <div class="col">
                            <div class="job-card">
                                <h5 class="card-title"><?php echo htmlspecialchars($job['job_title']); ?></h5>
                                <p class="card-text">
                                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($job['company']); ?><br>
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?><br>
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($job['post_date'])); ?><br>
                                    <i class="fas fa-user me-1"></i>Posted by <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>
                                </p>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#jobDetailModal"
                                        data-job='<?php echo json_encode($job); ?>'>
                                    <i class="fas fa-info-circle me-1"></i>Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted mt-4" data-aos="fade-up">No job opportunities available at the moment.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Job Detail Modal -->
    <div class="modal fade" id="jobDetailModal" tabindex="-1" aria-labelledby="jobDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="jobDetailModalLabel">Job Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 id="jobDetailTitle"></h6>
                    <p><strong>Company:</strong> <span id="jobDetailCompany"></span></p>
                    <p><strong>Location:</strong> <span id="jobDetailLocation"></span></p>
                    <p><strong>Poster:</strong> <span id="jobDetailPoster"></span></p>
                    <p><strong>Posted Date:</strong> <span id="jobDetailDate"></span></p>
                    <p><strong>Description:</strong> <span id="jobDetailDescription"></span></p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });

    document.addEventListener('DOMContentLoaded', function() {
        // Job detail modal
        const jobDetailModal = document.getElementById('jobDetailModal');
        if (jobDetailModal) {
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
        }

        // Edit job modal
        const editJobModal = document.getElementById('editJobModal');
        if (editJobModal) {
            editJobModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const jobData = JSON.parse(button.getAttribute('data-job'));

                document.getElementById('editJobId').value = jobData.job_id;
                document.getElementById('edit_job_title').value = jobData.job_title;
                document.getElementById('edit_company').value = jobData.company;
                document.getElementById('edit_location').value = jobData.location;
                document.getElementById('edit_description').value = jobData.description || '';
            });
        }

        // Delete job modal
        const deleteJobModal = document.getElementById('deleteJobModal');
        if (deleteJobModal) {
            deleteJobModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const jobId = button.getAttribute('data-job-id');
                const jobTitle = button.getAttribute('data-job-title');

                document.getElementById('deleteJobId').value = jobId;
                document.getElementById('deleteJobTitle').textContent = jobTitle;
            });
        }
    });
</script>
</body>
</html>