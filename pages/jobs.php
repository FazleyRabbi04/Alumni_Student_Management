<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
  header('Location: ../pages/signin.php');
  exit();
}

$message = '';
$error   = '';

/*Detect role flags (by presence in tables)*/
$is_student = false;
$is_alumni  = false;

$st = executeQuery("SELECT 1 FROM student WHERE person_id=? LIMIT 1", [$user_id]);
if ($st && $st->rowCount()) $is_student = true;

$al = executeQuery("SELECT 1 FROM alumni WHERE person_id=? LIMIT 1", [$user_id]);
if ($al && $al->rowCount()) $is_alumni = true;

/*POST HANDLERS*/

/* Create a job (ALUMNI ONLY) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_job'])) {
  if ($is_student) {
    $error = "Students cannot create jobs.";
  } else {
    $job_title   = trim($_POST['job_title'] ?? '');
    $company     = trim($_POST['company'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($job_title && $company && $location) {
      $sql = "INSERT INTO job (job_title, company, location, description, person_id)
                    VALUES (?, ?, ?, ?, ?)";
      if (executeQuery($sql, [$job_title, $company, $location, $description, $user_id])) {
        // If you have a helper for last insert ID, use it; otherwise remove the next 2 lines.
        if (function_exists('getLastInsertId')) {
          $job_id = getLastInsertId();
          @executeQuery("INSERT IGNORE INTO posts (person_id, job_id) VALUES (?, ?)", [$user_id, $job_id]);
        }
        $message = "Job posted successfully!";
      } else {
        $error = "Failed to post job.";
      }
    } else {
      $error = "Please fill in all required fields.";
    }
  }
}

/* Edit job (owner only) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_job'])) {
  $job_id      = (int)($_POST['job_id'] ?? 0);
  $job_title   = trim($_POST['job_title'] ?? '');
  $company     = trim($_POST['company'] ?? '');
  $location    = trim($_POST['location'] ?? '');
  $description = trim($_POST['description'] ?? '');

  if ($job_id && $job_title && $company && $location) {
    $own = executeQuery("SELECT 1 FROM job WHERE job_id=? AND person_id=?", [$job_id, $user_id]);
    if ($own && $own->rowCount()) {
      $upd = "UPDATE job SET job_title=?, company=?, location=?, description=? WHERE job_id=?";
      if (executeQuery($upd, [$job_title, $company, $location, $description, $job_id])) {
        $message = "Job updated successfully!";
      } else {
        $error = "Failed to update job.";
      }
    } else {
      $error = "Unauthorized: You can only edit your own jobs.";
    }
  } else {
    $error = "Please fill in all required fields.";
  }
}

/* Delete job (owner only) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
  $job_id = (int)($_POST['job_id'] ?? 0);
  if ($job_id) {
    $own = executeQuery("SELECT 1 FROM job WHERE job_id=? AND person_id=?", [$job_id, $user_id]);
    if ($own && $own->rowCount()) {
      if (executeQuery("DELETE FROM job WHERE job_id=?", [$job_id])) {
        $message = "Job deleted successfully!";
      } else {
        $error = "Failed to delete job.";
      }
    } else {
      $error = "Unauthorized: You can only delete your own jobs.";
    }
  }
}

/* Apply (any non-owner) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
  $job_id = (int)($_POST['job_id'] ?? 0);
  if ($job_id) {
    $owner_id = (int)(executeQuery("SELECT person_id FROM job WHERE job_id=?", [$job_id])->fetchColumn() ?? 0);
    if ($owner_id === $user_id) {
      $error = "You cannot apply to a job you posted.";
    } else {
      $sql = "INSERT INTO applies (person_id, job_id) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE status='pending'";
      if (executeQuery($sql, [$user_id, $job_id])) {
        $message = "Application submitted.";
      } else {
        $error = "Failed to submit application.";
      }
    }
  }
}

/* Withdraw application (student or alumni applicant) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_job'])) {
  $job_id = (int)($_POST['job_id'] ?? 0);
  if ($job_id) {
    if (executeQuery("DELETE FROM applies WHERE person_id=? AND job_id=?", [$user_id, $job_id])) {
      $message = "You have withdrawn your application.";
    } else {
      $error = "Failed to withdraw.";
    }
  }
}

/* Owner: Accept / Reject + optional Role (ALUMNI owner only) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_app_status'])) {
  $job_id    = (int)($_POST['job_id'] ?? 0);
  $applicant = (int)($_POST['applicant_id'] ?? 0);
  $decision  = $_POST['decision'] ?? ''; // 'accept' | 'reject'
  $role      = trim($_POST['role'] ?? '');

  if ($job_id && $applicant && in_array($decision, ['accept', 'reject'], true)) {
    $own = executeQuery("SELECT 1 FROM job WHERE job_id=? AND person_id=?", [$job_id, $user_id]);
    if ($own && $own->rowCount()) {
      $status = ($decision === 'accept') ? 'accepted' : 'rejected';
      $ok = executeQuery(
        "UPDATE applies SET status=?, role=IF(?='', role, ?) WHERE person_id=? AND job_id=?",
        [$status, $role, $role, $applicant, $job_id]
      );
      if ($ok) {
        $message = "Application has been {$status}."
          . (($status === 'accepted' && $role !== '') ? " Role set to '{$role}'." : '');
      } else {
        $error = "Failed to update application.";
      }
    } else {
      $error = "Unauthorized: Only the job owner can take action.";
    }
  }
}

/* ----------------------- DATA FOR PAGE ----------------------- */

/* Stats */
$stats_stmt = executeQuery("
    SELECT 
      COUNT(*) AS total_jobs,
      COUNT(CASE WHEN post_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) AS weekly_jobs,
      COUNT(CASE WHEN post_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS monthly_jobs
    FROM job
");
$stats = $stats_stmt ? $stats_stmt->fetch(PDO::FETCH_ASSOC) : ['total_jobs' => 0, 'weekly_jobs' => 0, 'monthly_jobs' => 0];

/* All jobs (+ poster info + applicant count) */
$jobs_stmt = executeQuery("
    SELECT 
      j.*, p.first_name, p.last_name, p.department,
      (SELECT COUNT(*) FROM applies a WHERE a.job_id=j.job_id) AS applicant_count
    FROM job j
    JOIN person p ON p.person_id=j.person_id
    ORDER BY j.post_date DESC
");
$jobs = $jobs_stmt ? $jobs_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

/* My applications map: job_id => status (for buttons on All Jobs) */
$app_map = [];
$my_apps_stmt = executeQuery("SELECT job_id, status FROM applies WHERE person_id=?", [$user_id]);
if ($my_apps_stmt) {
  foreach ($my_apps_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $app_map[(int)$row['job_id']] = $row['status'];
  }
}

/* Alumni-only: My posted jobs + preload applicants */
$my_jobs      = [];
$apps_by_job  = [];
if ($is_alumni) {
  $my_jobs_stmt = executeQuery("
        SELECT 
          j.*,
          (SELECT COUNT(*) FROM applies a WHERE a.job_id=j.job_id) AS applicant_count
        FROM job j
        WHERE j.person_id=?
        ORDER BY j.post_date DESC
    ", [$user_id]);
  $my_jobs = $my_jobs_stmt ? $my_jobs_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

  if (!empty($my_jobs)) {
    $ids = array_map(fn($j) => (int)$j['job_id'], $my_jobs);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $apps_stmt = executeQuery("
            SELECT a.job_id, a.person_id, a.status, a.role, a.applied_at,
                   pr.first_name, pr.last_name, pr.department, pr.Student_ID
            FROM applies a
            JOIN person pr ON pr.person_id=a.person_id
            WHERE a.job_id IN ($placeholders)
            ORDER BY a.applied_at DESC
        ", $ids);
    if ($apps_stmt) {
      foreach ($apps_stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $apps_by_job[(int)$r['job_id']][] = $r;
      }
    }
  }
}

/* Student-only: My applications list with job details */
$my_apps_list = [];
if ($is_student) {
  $my_apps_list_stmt = executeQuery("
        SELECT 
            a.job_id, a.status, a.role, a.applied_at,
            j.job_title, j.company, j.location, j.post_date, j.description,
            pp.first_name AS poster_first, pp.last_name AS poster_last, pp.department AS poster_dept
        FROM applies a
        JOIN job j   ON j.job_id = a.job_id
        JOIN person pp ON pp.person_id = j.person_id
        WHERE a.person_id = ?
        ORDER BY a.applied_at DESC
    ", [$user_id]);
  $my_apps_list = $my_apps_list_stmt ? $my_apps_list_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Job Board</title>

  <!-- Required CSS (necessary includes only) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    /* ---------- Base look & feel ---------- */
    body {
      font-family: 'Open Sans', sans-serif;
      background: #F9FAFB;
      color: #1F2937
    }

    .h2 {
      font-size: 1.75rem;
      font-weight: 700;
      color: #1E3A8A
    }

    .dashboard-card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, .1)
    }

    .dashboard-card .card-body {
      background: linear-gradient(135deg, #1E3A8A, #2563EB);
      color: #fff;
      border-radius: 12px;
      padding: 20px;
      text-align: center
    }

    .job-card .card {
      border: 1px solid #E5E7EB;
      border-radius: 12px;
      transition: box-shadow .2s
    }

    .job-card .card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, .1)
    }

    .job-empty-state {
      max-width: 460px;
      margin: 36px auto;
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 2px 18px rgba(0, 0, 0, .04);
      padding: 48px 30px
    }

    .badge.round {
      border-radius: 10px;
      padding: .35rem .6rem
    }

    /* ---------- Tabs: force black text ---------- */
    .nav-tabs {
      border-bottom: 1.5px solid #e5e7eb !important
    }

    .nav-tabs .nav-link {
      color: #000 !important;
      background: #fff !important;
      font-weight: 600;
      border: none;
      border-bottom: 2px solid transparent
    }

    .nav-tabs .nav-link:hover,
    .nav-tabs .nav-link:focus {
      color: #000 !important
    }

    .nav-tabs .nav-link.active {
      color: #000 !important;
      font-weight: 700;
      border-bottom: 3px solid #1E3A8A !important;
      background-color: #f8f9fa !important
    }

    /* ---------- My jobs table tweaks ---------- */
    #my-jobs .table .collapse>td {
      background: #fff
    }
  </style>
</head>

<body>

  <?php include '../includes/navbar.php'; ?>

  <div class="container-fluid px-0">
    <div class="row">
      <main class="col-12 px-4">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
          <h1 class="h2"><i class="fas fa-briefcase me-2"></i>Job Board</h1>
          <div class="btn-toolbar">
            <?php if ($is_alumni): ?>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#postJobModal">
                <i class="fas fa-plus me-1"></i>Post Job
              </button>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($message): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row mb-4">
          <div class="col-md-4">
            <div class="card dashboard-card">
              <div class="card-body">
                <div class="text-uppercase small">Total Jobs</div>
                <div class="h4 mb-0"><?= (int)($stats['total_jobs'] ?? 0) ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card dashboard-card">
              <div class="card-body">
                <div class="text-uppercase small">This Week</div>
                <div class="h4 mb-0"><?= (int)($stats['weekly_jobs'] ?? 0) ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card dashboard-card">
              <div class="card-body">
                <div class="text-uppercase small">This Month</div>
                <div class="h4 mb-0"><?= (int)($stats['monthly_jobs'] ?? 0) ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="jobsTab" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#all-jobs" type="button">All Jobs</button>
          </li>
          <?php if ($is_alumni): ?>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#my-jobs" type="button">My Posted Jobs</button>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#my-apps" type="button">My Applications</button>
            </li>
          <?php endif; ?>
        </ul>

        <div class="tab-content" id="jobsTabContent">

          <!-- All Jobs -->
          <div class="tab-pane fade show active" id="all-jobs" role="tabpanel">
            <div class="mt-4">
              <!-- Search + Location -->
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
                    $locations_stmt = executeQuery("SELECT DISTINCT location FROM job ORDER BY location");
                    if ($locations_stmt) {
                      while ($location = $locations_stmt->fetch(PDO::FETCH_COLUMN)) {
                        echo '<option value="' . htmlspecialchars($location) . '">' . htmlspecialchars($location) . '</option>';
                      }
                    }
                    ?>
                  </select>
                </div>
              </div>

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
                  <?php foreach ($jobs as $job):
                    $jid = (int)$job['job_id'];
                    $is_owner = ((int)$job['person_id'] === (int)$user_id);
                    $my_status = $app_map[$jid] ?? null; // pending/accepted/rejected or null
                  ?>
                    <div class="col job-card"
                      data-title="<?= strtolower($job['job_title']) ?>"
                      data-company="<?= strtolower($job['company']) ?>"
                      data-location="<?= strtolower($job['location']) ?>">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title mb-0"><?= htmlspecialchars($job['job_title']) ?></h5>
                            <span class="badge bg-primary round"><?= date('M j', strtotime($job['post_date'])) ?></span>
                          </div>

                          <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                              <i class="fas fa-building text-primary me-2"></i>
                              <span class="fw-bold"><?= htmlspecialchars($job['company']) ?></span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                              <i class="fas fa-map-marker-alt text-primary me-2"></i>
                              <span><?= htmlspecialchars($job['location']) ?></span>
                            </div>
                            <div class="d-flex align-items-center">
                              <i class="fas fa-user text-primary me-2"></i>
                              <span>Posted by <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?></span>
                              <?php if ($job['department']): ?>
                                <small class="text-muted ms-2">(<?= htmlspecialchars($job['department']) ?>)</small>
                              <?php endif; ?>
                            </div>
                          </div>

                          <?php if (!empty($job['description'])): ?>
                            <p class="card-text">
                              <?= htmlspecialchars(mb_strimwidth($job['description'], 0, 150, (strlen($job['description']) > 150 ? '...' : ''))) ?>
                            </p>
                          <?php endif; ?>

                          <div class="d-flex justify-content-between align-items-center">
                            <!-- Details modal trigger -->
                            <button type="button" class="btn btn-primary btn-sm"
                              data-bs-toggle="modal" data-bs-target="#jobDetailModal"
                              data-job='<?= json_encode($job, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                              <i class="fas fa-eye me-1"></i>View Details
                            </button>

                            <div class="d-flex align-items-center gap-2">
                              <small class="text-muted"><i class="fas fa-users me-1"></i><?= (int)$job['applicant_count'] ?> applicants</small>

                              <?php if (!$is_owner): ?>
                                <?php if ($my_status === null): ?>
                                  <form method="POST" class="d-inline">
                                    <input type="hidden" name="job_id" value="<?= $jid ?>">
                                    <button type="submit" name="apply_job" class="btn btn-outline-success btn-sm">
                                      <i class="fas fa-handshake me-1"></i>Apply
                                    </button>
                                  </form>
                                <?php elseif ($my_status === 'pending'): ?>
                                  <form method="POST" class="d-inline">
                                    <input type="hidden" name="job_id" value="<?= $jid ?>">
                                    <button type="submit" name="withdraw_job" class="btn btn-outline-secondary btn-sm">
                                      <i class="fas fa-times me-1"></i>Withdraw
                                    </button>
                                  </form>
                                  <span class="badge bg-secondary">Pending</span>
                                <?php elseif ($my_status === 'accepted'): ?>
                                  <form method="POST" class="d-inline">
                                    <input type="hidden" name="job_id" value="<?= $jid ?>">
                                    <button type="submit" name="withdraw_job" class="btn btn-outline-danger btn-sm">
                                      <i class="fas fa-door-open me-1"></i>Leave
                                    </button>
                                  </form>
                                  <span class="badge bg-success">Accepted</span>
                                <?php elseif ($my_status === 'rejected'): ?>
                                  <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                              <?php else: ?>
                                <?php if ($is_alumni): ?>
                                  <span class="badge bg-info">Owner</span>
                                <?php endif; ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php if ($is_alumni): ?>
            <!-- Alumni: My Posted Jobs (with inline applicants) -->
            <div class="tab-pane fade" id="my-jobs" role="tabpanel">
              <div class="mt-4">
                <?php if (empty($my_jobs)): ?>
                  <div class="d-flex justify-content-center align-items-center" style="min-height:240px;">
                    <div class="job-empty-state text-center w-100">
                      <i class="fas fa-briefcase fa-4x text-muted mb-4"></i>
                      <h4>No Jobs Posted</h4>
                      <p>You haven't posted any jobs yet.</p>
                      <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#postJobModal">
                        <i class="fas fa-plus me-1"></i>Post Your First Job
                      </button>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover align-middle" id="my-jobs">
                      <thead>
                        <tr>
                          <th>Job Title</th>
                          <th>Company</th>
                          <th>Location</th>
                          <th>Posted</th>
                          <th>Applicants</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($my_jobs as $job): ?>
                          <tr>
                            <td><strong><?= htmlspecialchars($job['job_title']) ?></strong></td>
                            <td><?= htmlspecialchars($job['company']) ?></td>
                            <td><?= htmlspecialchars($job['location']) ?></td>
                            <td><?= date('M j, Y', strtotime($job['post_date'])) ?></td>
                            <td><span class="badge bg-info"><?= (int)$job['applicant_count'] ?></span></td>
                            <td>
                              <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" title="View"
                                  data-bs-toggle="modal" data-bs-target="#jobDetailModal"
                                  data-job='<?= json_encode($job, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                                  <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-success" title="Edit"
                                  data-bs-toggle="modal" data-bs-target="#editJobModal"
                                  data-job='<?= json_encode($job, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                                  <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" title="Delete"
                                  data-bs-toggle="modal" data-bs-target="#deleteJobModal"
                                  data-job-id="<?= (int)$job['job_id'] ?>"
                                  data-job-title="<?= htmlspecialchars($job['job_title']) ?>">
                                  <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn btn-outline-info"
                                  type="button"
                                  data-bs-toggle="collapse"
                                  data-bs-target="#apps-<?= (int)$job['job_id'] ?>"
                                  aria-expanded="false"
                                  aria-controls="apps-<?= (int)$job['job_id'] ?>">
                                  <i class="fas fa-users"></i>
                                </button>
                              </div>
                            </td>
                          </tr>

                          <?php
                          $jid = (int)$job['job_id'];
                          $rows = $apps_by_job[$jid] ?? [];
                          ?>
                          <tr class="collapse" id="apps-<?= $jid ?>">
                            <td colspan="6">
                              <?php if (empty($rows)): ?>
                                <div class="text-muted py-3 px-2">No applications yet.</div>
                              <?php else: ?>
                                <div class="table-responsive">
                                  <table class="table table-bordered align-middle mb-0">
                                    <thead>
                                      <tr>
                                        <th>Applicant</th>
                                        <th>Dept / ID</th>
                                        <th>Status</th>
                                        <th>Role</th>
                                        <th>Applied</th>
                                        <th style="width:280px;">Action</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <?php foreach ($rows as $r): ?>
                                        <tr>
                                          <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                          <td><?= htmlspecialchars(($r['department'] ?? '') . ($r['Student_ID'] ? ' / ' . $r['Student_ID'] : '')) ?></td>
                                          <td>
                                            <span class="badge <?= $r['status'] === 'accepted' ? 'bg-success' : ($r['status'] === 'rejected' ? 'bg-danger' : 'bg-secondary') ?>">
                                              <?= ucfirst($r['status']) ?>
                                            </span>
                                          </td>
                                          <td><?= htmlspecialchars($r['role'] ?? '') ?></td>
                                          <td><?= htmlspecialchars(date('M j, Y H:i', strtotime($r['applied_at']))) ?></td>
                                          <td>
                                            <form method="POST" class="d-flex gap-2 flex-wrap align-items-center">
                                              <input type="hidden" name="job_id" value="<?= $jid ?>">
                                              <input type="hidden" name="applicant_id" value="<?= (int)$r['person_id'] ?>">
                                              <input type="hidden" name="decision" value="">
                                              <input type="text" name="role" class="form-control form-control-sm" placeholder="Role (optional)" value="<?= htmlspecialchars($r['role'] ?? '') ?>" style="max-width: 160px;">
                                              <button class="btn btn-sm btn-success" name="update_app_status" value="1" onclick="this.form.decision.value='accept'">Accept</button>
                                              <button class="btn btn-sm btn-danger" name="update_app_status" value="1" onclick="this.form.decision.value='reject'">Reject</button>
                                            </form>
                                          </td>
                                        </tr>
                                      <?php endforeach; ?>
                                    </tbody>
                                  </table>
                                </div>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($is_student): ?>
            <!-- Student: My Applications -->
            <div class="tab-pane fade" id="my-apps" role="tabpanel">
              <div class="mt-4">
                <?php if (empty($my_apps_list)): ?>
                  <div class="d-flex justify-content-center align-items-center" style="min-height:240px;">
                    <div class="job-empty-state text-center w-100">
                      <i class="fas fa-clipboard-list fa-4x text-muted mb-4"></i>
                      <h4>No Applications Yet</h4>
                      <p>Browse jobs and apply to see them here.</p>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover align-middle">
                      <thead>
                        <tr>
                          <th>Job</th>
                          <th>Company</th>
                          <th>Location</th>
                          <th>Status</th>
                          <th>Role</th>
                          <th>Applied</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($my_apps_list as $a): ?>
                          <tr>
                            <td>
                              <strong><?= htmlspecialchars($a['job_title']) ?></strong><br>
                              <small class="text-muted">Posted by <?= htmlspecialchars($a['poster_first'] . ' ' . $a['poster_last']) ?><?= $a['poster_dept'] ? ' (' . htmlspecialchars($a['poster_dept']) . ')' : '' ?></small>
                            </td>
                            <td><?= htmlspecialchars($a['company']) ?></td>
                            <td><?= htmlspecialchars($a['location']) ?></td>
                            <td>
                              <span class="badge <?= $a['status'] === 'accepted' ? 'bg-success' : ($a['status'] === 'rejected' ? 'bg-danger' : 'bg-secondary') ?>">
                                <?= ucfirst($a['status']) ?>
                              </span>
                            </td>
                            <td><?= htmlspecialchars($a['role'] ?? '') ?></td>
                            <td><?= htmlspecialchars(date('M j, Y H:i', strtotime($a['applied_at']))) ?></td>
                            <td>
                              <?php if ($a['status'] === 'pending' || $a['status'] === 'accepted'): ?>
                                <form method="POST" class="d-inline">
                                  <input type="hidden" name="job_id" value="<?= (int)$a['job_id'] ?>">
                                  <button type="submit" name="withdraw_job" class="btn btn-sm btn-outline-danger">
                                    <?= $a['status'] === 'accepted' ? 'Leave' : 'Cancel' ?>
                                  </button>
                                </form>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </main>
    </div>
  </div>

  <!-- Modals that both roles may use (students never see Post modal button) -->
  <?php if ($is_alumni): ?>
    <div class="modal fade" id="postJobModal" tabindex="-1" aria-labelledby="postJobModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form method="POST">
            <div class="modal-header">
              <h5 class="modal-title" id="postJobModalLabel"><i class="fas fa-plus-circle me-2"></i>Post a New Job</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3"><label class="form-label">Job Title</label><input type="text" name="job_title" class="form-control" required></div>
              <div class="mb-3"><label class="form-label">Company</label><input type="text" name="company" class="form-control" required></div>
              <div class="mb-3"><label class="form-label">Location</label><input type="text" name="location" class="form-control" required></div>
              <div class="mb-3"><label class="form-label">Description</label><textarea name="description" rows="5" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="post_job" class="btn btn-primary">Post Job</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Edit Job Modal (alumni owner) -->
  <div class="modal fade" id="editJobModal" tabindex="-1" aria-labelledby="editJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="job_id" id="editJobId">
          <div class="modal-header">
            <h5 class="modal-title" id="editJobModalLabel"><i class="fas fa-edit me-2"></i>Edit Job</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3"><label class="form-label">Job Title</label><input type="text" name="job_title" id="edit_job_title" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Company</label><input type="text" name="company" id="edit_company" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Location</label><input type="text" name="location" id="edit_location" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit_description" rows="5" class="form-control"></textarea></div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="edit_job" class="btn btn-success">Update Job</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Job Modal (alumni owner) -->
  <div class="modal fade" id="deleteJobModal" tabindex="-1" aria-labelledby="deleteJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="job_id" id="deleteJobId">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="deleteJobModalLabel"><i class="fas fa-trash me-2"></i>Delete Job</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong id="deleteJobTitle"></strong>?</p>
          </div>
          <div class="modal-footer">
            <button type="submit" name="delete_job" class="btn btn-danger">Delete</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Job Detail Modal (both roles) -->
  <div class="modal fade" id="jobDetailModal" tabindex="-1" aria-labelledby="jobDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="jobDetailTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p><strong>Company:</strong> <span id="jobDetailCompany"></span></p>
          <p><strong>Location:</strong> <span id="jobDetailLocation"></span></p>
          <p><strong>Posted By:</strong> <span id="jobDetailPoster"></span></p>
          <p><strong>Posted On:</strong> <span id="jobDetailDate"></span></p>
          <hr>
          <p id="jobDetailDescription"></p>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <!-- Necessary JS includes only -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    /* -------- Client-side filters & modal fills (minimal, necessary) -------- */
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('jobSearch');
      const locationFilter = document.getElementById('locationFilter');
      const jobCards = document.querySelectorAll('.job-card');

      function filterJobs() {
        const s = (searchInput?.value || '').toLowerCase();
        const loc = (locationFilter?.value || '').toLowerCase();
        jobCards.forEach(card => {
          const title = card.dataset.title;
          const company = card.dataset.company;
          const location = card.dataset.location;
          const matchesSearch = !s || title.includes(s) || company.includes(s) || location.includes(s);
          const matchesLoc = !loc || location.includes(loc);
          card.style.display = (matchesSearch && matchesLoc) ? 'block' : 'none';
        });
      }
      if (searchInput) searchInput.addEventListener('input', filterJobs);
      if (locationFilter) locationFilter.addEventListener('change', filterJobs);

      // Detail modal fill
      const jobDetailModal = document.getElementById('jobDetailModal');
      jobDetailModal.addEventListener('show.bs.modal', (e) => {
        const job = JSON.parse(e.relatedTarget.getAttribute('data-job'));
        document.getElementById('jobDetailTitle').textContent = job.job_title;
        document.getElementById('jobDetailCompany').textContent = job.company;
        document.getElementById('jobDetailLocation').textContent = job.location;
        document.getElementById('jobDetailPoster').textContent = (job.first_name || '') + ' ' + (job.last_name || '');
        document.getElementById('jobDetailDate').textContent = new Date(job.post_date).toLocaleDateString();
        const desc = job.description || 'No description provided.';
        document.getElementById('jobDetailDescription').innerHTML = desc.replace(/\n/g, '<br>');
      });

      // Edit modal fill
      const editJobModal = document.getElementById('editJobModal');
      editJobModal.addEventListener('show.bs.modal', (e) => {
        const job = JSON.parse(e.relatedTarget.getAttribute('data-job'));
        document.getElementById('editJobId').value = job.job_id;
        document.getElementById('edit_job_title').value = job.job_title;
        document.getElementById('edit_company').value = job.company;
        document.getElementById('edit_location').value = job.location;
        document.getElementById('edit_description').value = job.description || '';
      });

      // Delete modal fill
      const deleteJobModal = document.getElementById('deleteJobModal');
      deleteJobModal.addEventListener('show.bs.modal', (e) => {
        const btn = e.relatedTarget;
        document.getElementById('deleteJobId').value = btn.getAttribute('data-job-id');
        document.getElementById('deleteJobTitle').textContent = btn.getAttribute('data-job-title');
      });
    });
  </script>
</body>

</html>