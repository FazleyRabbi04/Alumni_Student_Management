<?php
require_once '../config/database.php';
startSecureSession();
requireLogin();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../pages/signin.php');
    exit();
}

/* --------------------------- PDO Bootstrap --------------------------- */
/** @var PDO|null $pdo */
$pdo = null;
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
} elseif (function_exists('db') && db() instanceof PDO) {
    $pdo = db();
} elseif (function_exists('getPDO') && getPDO() instanceof PDO) {
    $pdo = getPDO();
}
if (!$pdo instanceof PDO) {
    http_response_code(500);
    exit('Database connection (PDO) is not available. Expose $pdo or db()/getPDO() from config/database.php.');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* ----------------------------- Helpers ----------------------------- */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function checkCsrf(): void {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}
function isAdmin(PDO $pdo, int $uid): bool {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM person LIKE 'is_admin'");
        if ($stmt->rowCount() === 0) return false;

        $q = $pdo->prepare("SELECT COALESCE(is_admin, 0) AS is_admin FROM person WHERE person_id = ?");
        $q->execute([$uid]);
        $row = $q->fetch();
        return $row ? (bool)$row['is_admin'] : false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Find a user by Student_ID, email (email_address table), or full name.
 * Returns: ['person_id','first_name','last_name','email' (if any)] or null
 */
function findUserByEmailOrName(PDO $pdo, string $needle): ?array {
    $q = trim($needle);
    if ($q === '') return null;
    $q_norm = preg_replace('/\s+/', ' ', $q);

    // 1) Exact Student_ID
    $stmt = $pdo->prepare(
        "SELECT person_id, first_name, last_name
         FROM person
         WHERE Student_ID = ?
         LIMIT 1"
    );
    $stmt->execute([$q_norm]);
    if ($u = $stmt->fetch()) {
        $em = $pdo->prepare("SELECT email FROM email_address WHERE person_id = ? LIMIT 1");
        $em->execute([$u['person_id']]);
        $u['email'] = ($er = $em->fetch()) ? ($er['email'] ?? null) : null;
        return $u;
    }

    // 2) Exact email
    if (filter_var($q_norm, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare(
            "SELECT p.person_id, p.first_name, p.last_name, ea.email
             FROM email_address ea
             JOIN person p ON p.person_id = ea.person_id
             WHERE ea.email = ?
             LIMIT 1"
        );
        $stmt->execute([$q_norm]);
        if ($u = $stmt->fetch()) return $u;
    }

    // 3) Exact full-name (case-insensitive)
    $stmt = $pdo->prepare(
        "SELECT person_id, first_name, last_name
         FROM person
         WHERE TRIM(CONCAT(first_name,' ',last_name)) COLLATE utf8mb4_general_ci = ?
         LIMIT 1"
    );
    $stmt->execute([$q_norm]);
    if ($u = $stmt->fetch()) {
        $em = $pdo->prepare("SELECT email FROM email_address WHERE person_id = ? LIMIT 1");
        $em->execute([$u['person_id']]);
        $u['email'] = ($er = $em->fetch()) ? ($er['email'] ?? null) : null;
        return $u;
    }

    // 4) Tokenized partial name
    $parts = explode(' ', $q_norm);
    if (count($parts) >= 2) {
        $first = $parts[0];
        $last  = $parts[count($parts)-1];
        $stmt = $pdo->prepare(
            "SELECT person_id, first_name, last_name
             FROM person
             WHERE first_name LIKE ? AND last_name LIKE ?
             ORDER BY person_id ASC
             LIMIT 1"
        );
        $stmt->execute([$first.'%', $last.'%']);
        if ($u = $stmt->fetch()) {
            $em = $pdo->prepare("SELECT email FROM email_address WHERE person_id = ? LIMIT 1");
            $em->execute([$u['person_id']]);
            $u['email'] = ($er = $em->fetch()) ? ($er['email'] ?? null) : null;
            return $u;
        }
    }

    // 5) Fallback partial on full name
    $like = '%'.$q_norm.'%';
    $stmt = $pdo->prepare(
        "SELECT person_id, first_name, last_name
         FROM person
         WHERE CONCAT(first_name,' ',last_name) LIKE ?
         ORDER BY person_id ASC
         LIMIT 1"
    );
    $stmt->execute([$like]);
    if ($u = $stmt->fetch()) {
        $em = $pdo->prepare("SELECT email FROM email_address WHERE person_id = ? LIMIT 1");
        $em->execute([$u['person_id']]);
        $u['email'] = ($er = $em->fetch()) ? ($er['email'] ?? null) : null;
        return $u;
    }

    return null;
}

/* ----------------------------- State ------------------------------ */
$tab        = $_GET['tab']   ?? 'inbox'; // inbox|sent
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 8;
$offset     = ($page - 1) * $per_page;

$success = '';
$error   = '';

/* ---------------------------- Actions ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'compose') {
        checkCsrf();
        $to_id    = isset($_POST['to_id']) ? (int)$_POST['to_id'] : 0;
        $to_input = trim($_POST['to'] ?? '');
        $subject  = trim($_POST['subject'] ?? '');
        $message  = trim($_POST['message'] ?? '');

        if (($to_id <= 0 && $to_input === '') || $subject === '' || $message === '') {
            $error = 'Please fill in To, Subject, and Message.';
        } elseif (strlen($subject) > 150) {
            $error = 'Subject is too long (max 150 characters).';
        } elseif (strlen($message) > 2000) {
            $error = 'Message is too long (max 2000 characters).';
        } else {
            // Resolve recipient
            if ($to_id > 0) {
                $q = $pdo->prepare("SELECT person_id, first_name, last_name FROM person WHERE person_id = ? LIMIT 1");
                $q->execute([$to_id]);
                $recipient = $q->fetch() ?: null;
                if ($recipient) $recipient['email'] = null;
            } else {
                $recipient = findUserByEmailOrName($pdo, $to_input);
            }

            if (!$recipient) {
                $error = 'Recipient not found. Use their email, Student_ID, or full name.';
            } elseif ((int)$recipient['person_id'] === (int)$user_id) {
                $error = 'You cannot send a message to yourself.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "INSERT INTO communication (message, sent_at, subject, method)
                         VALUES (?, NOW(), ?, 'Email')"
                    );
                    $stmt->execute([$message, $subject]);

                    $comm_id = (int)$pdo->lastInsertId();

                    $stmt2 = $pdo->prepare(
                        "INSERT INTO sends (person_id, comm_id, response, status)
                         VALUES (?, ?, 'Unread', 'Sent')"
                    );
                    $stmt2->execute([(int)$recipient['person_id'], $comm_id]);

                    $stmt3 = $pdo->prepare(
                        "INSERT INTO sends (person_id, comm_id, response, status)
                         VALUES (?, ?, 'Read', 'Sent')"
                    );
                    $stmt3->execute([(int)$user_id, $comm_id]);

                    $pdo->commit();
                    $success = 'Message sent successfully.';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Failed to send message: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }

    if ($action === 'mark_read') {
        checkCsrf();
        $comm_id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE sends SET response = 'Read'
             WHERE comm_id = ? AND person_id = ?"
        );
        $stmt->execute([$comm_id, $user_id]);
        $success = 'Message marked as read.';
    }

    if ($action === 'delete') {
        checkCsrf();
        $comm_id = (int)($_POST['id'] ?? 0);
        $admin = isAdmin($pdo, (int)$user_id);

        if ($admin) {
            $stmt = $pdo->prepare("DELETE FROM communication WHERE comm_id = ?");
            $stmt->execute([$comm_id]);
            $success = 'Message deleted.';
        } else {
            $stmt = $pdo->prepare(
                "DELETE FROM sends
                 WHERE comm_id = ? AND person_id = ?"
            );
            $stmt->execute([$comm_id, $user_id]);

            $stmt = $pdo->prepare(
                "DELETE c FROM communication c
                 LEFT JOIN sends s ON s.comm_id = c.comm_id
                 WHERE c.comm_id = ? AND s.comm_id IS NULL"
            );
            $stmt->execute([$comm_id]);

            $success = 'Message deleted.';
        }
    }
}

/* --------------------------- Fetch Rows --------------------------- */
$search_sql = '';
$search_params = [];
if ($search !== '') {
    $search_sql = " AND (
        c.subject LIKE ? OR
        CONCAT(ps.first_name,' ',ps.last_name) LIKE ? OR
        CONCAT(pr.first_name,' ',pr.last_name) LIKE ?
    )";
    $search_params = array_fill(0, 3, '%'.$search.'%');
}

if ($tab === 'sent') {
    $count_sql = "
        SELECT COUNT(DISTINCT c.comm_id) AS cnt
        FROM communication c
        JOIN sends s_you ON s_you.comm_id = c.comm_id AND s_you.person_id = ? AND s_you.response = 'Read'
        JOIN sends s_other ON s_other.comm_id = c.comm_id AND s_other.person_id <> s_you.person_id
        JOIN person ps ON ps.person_id = s_you.person_id
        JOIN person pr ON pr.person_id = s_other.person_id
        WHERE 1=1 $search_sql
    ";
    $list_sql = "
        SELECT c.comm_id, c.subject, c.sent_at,
               s_you.person_id AS you_id,
               s_other.person_id AS other_id,
               ps.first_name AS you_fn, ps.last_name AS you_ln,
               pr.first_name AS other_fn, pr.last_name AS other_ln,
               s_other.response AS other_response
        FROM communication c
        JOIN sends s_you ON s_you.comm_id = c.comm_id AND s_you.person_id = ? AND s_you.response = 'Read'
        JOIN sends s_other ON s_other.comm_id = c.comm_id AND s_other.person_id <> s_you.person_id
        JOIN person ps ON ps.person_id = s_you.person_id
        JOIN person pr ON pr.person_id = s_other.person_id
        WHERE 1=1 $search_sql
        GROUP BY c.comm_id
        ORDER BY c.sent_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $params = array_merge([$user_id], $search_params);
} else {
    $count_sql = "
        SELECT COUNT(DISTINCT c.comm_id) AS cnt
        FROM communication c
        JOIN sends s_you ON s_you.comm_id = c.comm_id AND s_you.person_id = ?
        JOIN sends s_other ON s_other.comm_id = c.comm_id AND s_other.person_id <> s_you.person_id
        JOIN person ps ON ps.person_id = s_other.person_id
        JOIN person pr ON pr.person_id = s_you.person_id
        WHERE 1=1 $search_sql
    ";
    $list_sql = "
        SELECT c.comm_id, c.subject, c.sent_at,
               s_you.person_id AS you_id,
               s_other.person_id AS other_id,
               ps.first_name AS other_fn, ps.last_name AS other_ln,
               pr.first_name AS you_fn, pr.last_name AS you_ln,
               s_you.response AS your_response
        FROM communication c
        JOIN sends s_you ON s_you.comm_id = c.comm_id AND s_you.person_id = ?
        JOIN sends s_other ON s_other.comm_id = c.comm_id AND s_other.person_id <> s_you.person_id
        JOIN person ps ON ps.person_id = s_other.person_id
        JOIN person pr ON pr.person_id = s_you.person_id
        WHERE 1=1 $search_sql
        GROUP BY c.comm_id
        ORDER BY c.sent_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $params = array_merge([$user_id], $search_params);
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = (int)($count_stmt->fetch()['cnt'] ?? 0);
$total_pages = max(1, (int)ceil($total / $per_page));

$list_stmt = $pdo->prepare($list_sql);
$list_stmt->execute($params);
$rows = $list_stmt->fetchAll();

$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Messages</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background-color: #f8fafc; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
        }
        .comm-container { 
            max-width: 1100px; 
            margin-top: 20px; 
            padding: 0 15px;
        }
        .page-header { 
            color: #1e293b; 
            font-weight: 600; 
            font-size: 2rem; 
        }
        .compose-btn { 
            background: #3b82f6; 
            border: none; 
            border-radius: 10px; 
            padding: 12px 24px; 
            font-weight: 500; 
            transition: all 0.2s; 
        }
        .compose-btn:hover { 
            background: #2563eb; 
            transform: translateY(-1px); 
        }
        .search-input { 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            padding: 10px 16px; 
            max-width: 400px; 
        }
        .search-input:focus { 
            border-color: #3b82f6; 
            box-shadow: 0 0 0 3px rgba(59,130,246,.1); 
        }
        .search-btn, .clear-btn { 
            border-radius: 10px; 
            padding: 10px 20px; 
            font-weight: 500; 
        }
        .comm-tabs { 
            border: none; 
            margin: 20px 0; 
        }
        .comm-tabs .nav-link { 
            color: #64748b !important; 
            background: white; 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            margin-right: 10px; 
            padding: 12px 20px; 
            font-weight: 500; 
            transition: all .2s; 
        }
        .comm-tabs .nav-link:hover { 
            color: #3b82f6 !important; 
            border-color: #3b82f6; 
        }
        .comm-tabs .nav-link.active { 
            color: white !important; 
            background-color: #3b82f6; 
            border-color: #3b82f6; 
        }
        .message-card { 
            background: white; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            margin-bottom: 16px; 
            transition: all .2s; 
        }
        .message-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,.1); 
        }
        .message-card.unread { 
            border-left: 4px solid #3b82f6; 
            background: #f8faff; 
        }
        .message-content { 
            padding: 12px; 
        }
        .sender-badge { 
            background: #3b82f6; 
            color: white; 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-size: .9rem; 
            font-weight: 500; 
        }
        .receiver-badge { 
            background: #10b981; 
            color: white; 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-size: .9rem; 
            font-weight: 500; 
        }
        .unread-badge { 
            background: #f59e0b; 
            color: white; 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: .85rem; 
            font-weight: 500; 
        }
        .message-subject { 
            font-size: 1.2rem; 
            font-weight: 600; 
            color: #1e293b; 
            margin: 8px 0; 
        }
        .message-meta { 
            color: #64748b; 
            font-size: .95rem; 
            margin-bottom: 8px; 
        }
        .btn-action { 
            border-radius: 8px; 
            padding: 8px 18px; 
            font-size: .9rem; 
            font-weight: 500; 
            margin-bottom: 8px; 
            min-width: 100px; 
            transition: all .2s; 
        }
        .btn-action:hover { 
            transform: translateY(-1px); 
        }
        .empty-state { 
            text-align: center; 
            padding: 40px 20px; 
            color: #64748b; 
        }
        .empty-state i { 
            font-size: 2.5rem; 
            margin-bottom: 12px; 
            opacity: .5; 
        }
        .pagination .page-link { 
            border: 1px solid #e2e8f0; 
            color: #64748b; 
            border-radius: 8px; 
            margin: 0 3px; 
            padding: 8px 14px; 
            transition: all .2s; 
        }
        .pagination .page-link:hover { 
            border-color: #3b82f6; 
            color: #3b82f6; 
        }
        .pagination .page-item.active .page-link { 
            background: #3b82f6; 
            border-color: #3b82f6; 
            color: white; 
        }
        .modal-content { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 20px 25px -5px rgba(0,0,0,.1); 
        }
        .modal-header { 
            border-bottom: 1px solid #e2e8f0; 
            padding: 16px 20px; 
        }
        .modal-body { 
            padding: 20px; 
        }
        .form-control { 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            padding: 8px 12px; 
            transition: all .2s; 
        }
        .form-control:focus { 
            border-color: #3b82f6; 
            box-shadow: 0 0 0 3px rgba(59,130,246,.1); 
        }
        .form-label { 
            font-weight: 500; 
            color: #374151; 
            margin-bottom: 6px; 
        }
        .alert { 
            border: none; 
            border-radius: 10px; 
            padding: 10px 14px; 
            font-weight: 500; 
            margin-bottom: 16px;
        }
        .alert-success { 
            background: #dcfce7; 
            color: #166534; 
            border-left: 4px solid #22c55e; 
        }
        .alert-danger { 
            background: #fef2f2; 
            color: #dc2626; 
            border-left: 4px solid #ef4444; 
        }
        .autocomplete-container { 
            position: relative; 
        }
        .autocomplete-suggestions { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            right: 0; 
            background: white; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            max-height: 180px; 
            overflow-y: auto; 
            z-index: 1000; 
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        .autocomplete-item { 
            padding: 8px 12px; 
            cursor: pointer; 
            transition: background 0.2s; 
        }
        .autocomplete-item:hover { 
            background: #f1f5f9; 
        }
        .autocomplete-item.active { 
            background: #e2e8f0; 
        }
        @media (max-width: 768px) {
            .message-content { 
                padding: 10px; 
            }
            .action-buttons { 
                display: flex; 
                gap: 6px; 
                flex-wrap: wrap; 
                justify-content: center; 
            }
            .btn-action { 
                flex: 1; 
                min-width: 90px; 
                padding: 6px 12px; 
            }
            .comm-container { 
                padding: 0 8px; 
            }
            .page-header { 
                font-size: 1.7rem; 
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container comm-container py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header mb-0">Messages</h2>
        <button class="btn btn-primary compose-btn" data-bs-toggle="modal" data-bs-target="#composeModal">
            <i class="fas fa-plus me-2"></i>New Message
        </button>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form class="d-flex gap-2 mb-3 align-items-center flex-wrap" method="get" action="">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input class="form-control search-input flex-grow-1" type="search" name="q"
               placeholder="Search by subject or name..." value="<?= htmlspecialchars($search) ?>" 
               aria-label="Search messages">
        <button class="btn btn-primary search-btn" type="submit">
            <i class="fas fa-search me-1"></i>Search
        </button>
        <?php if ($search !== ''): ?>
          <a class="btn btn-outline-secondary clear-btn" href="?tab=<?= urlencode($tab) ?>">Clear</a>
        <?php endif; ?>
    </form>

    <ul class="nav comm-tabs">
        <li class="nav-item">
            <a class="nav-link <?= $tab==='inbox'?'active':'' ?>"
               href="?tab=inbox<?= $search!=='' ? '&q='.urlencode($search) : '' ?>" 
               aria-current="<?= $tab==='inbox'?'page':'' ?>">
                <i class="fas fa-inbox me-2"></i>Inbox
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab==='sent'?'active':'' ?>"
               href="?tab=sent<?= $search!=='' ? '&q='.urlencode($search) : '' ?>" 
               aria-current="<?= $tab==='sent'?'page':'' ?>">
                <i class="fas fa-paper-plane me-2"></i>Sent
            </a>
        </li>
    </ul>

    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h4>No messages</h4>
            <p class="text-muted">
                Your <?= $tab === 'sent' ? 'sent messages' : 'inbox' ?> is empty. 
                Send a message to get started.
            </p>
        </div>
    <?php else: ?>
        <div class="messages-list">
            <?php foreach ($rows as $m): ?>
                <?php
                    if ($tab === 'sent') {
                        $badge_text = 'To: ' . htmlspecialchars(trim(($m['other_fn'] ?? '').' '.($m['other_ln'] ?? '')));
                        $is_unread = false;
                    } else {
                        $badge_text = 'From: ' . htmlspecialchars(trim(($m['other_fn'] ?? '').' '.($m['other_ln'] ?? '')));
                        $is_unread = (isset($m['your_response']) && $m['your_response'] !== 'Read');
                    }
                ?>
                <div class="card message-card <?= $is_unread ? 'unread' : '' ?>" role="article">
                    <div class="message-content">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1 me-3">
                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <span class="<?= $tab==='sent' ? 'receiver-badge' : 'sender-badge' ?>">
                                        <?= $badge_text ?>
                                    </span>
                                    <?php if ($is_unread): ?>
                                        <span class="unread-badge">Unread</span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="message-subject"><?= htmlspecialchars($m['subject']) ?></h5>
                                <div class="message-meta">
                                    <i class="fas fa-clock me-1"></i>
                                    <?= date('M j, Y g:i A', strtotime($m['sent_at'])) ?>
                                </div>
                            </div>
                            <div class="action-buttons d-flex flex-column align-items-end gap-2">
                                <?php if ($is_unread): ?>
                                <form method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= (int)$m['comm_id'] ?>">
                                    <button class="btn btn-success btn-action" type="submit" 
                                            title="Mark as read">
                                        <i class="fas fa-check me-1"></i>Mark Read
                                    </button>
                                </form>
                                <?php endif; ?>
                                <button class="btn btn-outline-primary btn-action"
                                        data-bs-toggle="modal"
                                        data-bs-target="#composeModal"
                                        data-reply="1"
                                        data-toid="<?= (int)($m['other_id'] ?? 0) ?>"
                                        data-toname="<?= htmlspecialchars(trim(($m['other_fn'] ?? '').' '.($m['other_ln'] ?? ''))) ?>"
                                        data-subject="<?= htmlspecialchars('Re: ' . $m['subject']) ?>"
                                        title="Reply to this message">
                                    <i class="fas fa-reply me-1"></i>Reply
                                </button>
                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$m['comm_id'] ?>">
                                    <button class="btn btn-outline-danger btn-action" type="submit" 
                                            title="Delete this message">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4" aria-label="Messages pagination">
            <ul class="pagination justify-content-center">
                <?php
                    $base = '?tab='.urlencode($tab).($search!=='' ? '&q='.urlencode($search) : '');
                    $prev = max(1, $page-1);
                    $next = min($total_pages, $page+1);
                ?>
                <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="<?= $base.'&page=1' ?>" aria-label="First page">
                        <span aria-hidden="true">First</span>
                    </a>
                </li>
                <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="<?= $base.'&page='.$prev ?>" aria-label="Previous page">
                        <span aria-hidden="true">Prev</span>
                    </a>
                </li>
                <li class="page-item active" aria-current="page">
                    <span class="page-link">Page <?= $page ?> of <?= $total_pages ?></span>
                </li>
                <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                    <a class="page-link" href="<?= $base.'&page='.$next ?>" aria-label="Next page">
                        <span aria-hidden="true">Next</span>
                    </a>
                </li>
                <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                    <a class="page-link" href="<?= $base.'&page='.$total_pages ?>" aria-label="Last page">
                        <span aria-hidden="true">Last</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="post" id="composeForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="compose">
      <input type="hidden" name="to_id" id="composeToId" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="composeModalLabel">New Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 autocomplete-container">
          <label for="composeTo" class="form-label">To (email, Student ID, or name)</label>
          <input type="text" class="form-control" name="to" id="composeTo" 
                 placeholder="e.g., john.doe@uni.edu, 20CSE999, or John Doe" 
                 autocomplete="off" required aria-describedby="toHelp">
          <div id="toHelp" class="form-text">
            Start typing to search for a recipient.
          </div>
          <div class="autocomplete-suggestions" id="autocompleteSuggestions"></div>
        </div>
        <div class="mb-3">
          <label for="composeSubject" class="form-label">Subject</label>
          <input type="text" class="form-control" name="subject" id="composeSubject" 
                 maxlength="150" required aria-describedby="subjectHelp">
          <div id="subjectHelp" class="form-text">Max 150 characters.</div>
        </div>
        <div class="mb-3">
          <label for="composeMessage" class="form-label">Message</label>
          <textarea class="form-control" name="message" id="composeMessage" rows="5" 
                    maxlength="2000" required aria-describedby="messageHelp"></textarea>
          <div id="messageHelp" class="form-text">Max 2000 characters.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="sendButton">
            <i class="fas fa-paper-plane me-2"></i>Send
        </button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pre-fill compose modal for replies
const composeModal = document.getElementById('composeModal');
composeModal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    const toIdInput = document.getElementById('composeToId');
    const toField = document.getElementById('composeTo');
    const subjField = document.getElementById('composeSubject');
    const msgField = document.getElementById('composeMessage');
    const suggestions = document.getElementById('autocompleteSuggestions');

    // Clear previous state
    toIdInput.value = '';
    toField.value = '';
    subjField.value = '';
    msgField.value = '';
    suggestions.innerHTML = '';

    if (button && button.getAttribute('data-reply')) {
        const toId = button.getAttribute('data-toid') || '';
        const toName = button.getAttribute('data-toname') || '';
        const subject = button.getAttribute('data-subject') || '';

        toIdInput.value = toId;
        toField.value = toName;
        toField.setAttribute('data-selected-id', toId);
        subjField.value = subject;
        toField.readOnly = true;
        msgField.focus();
    } else {
        toField.readOnly = false;
        toField.focus();
    }
});

// Autocomplete for recipient field
const toField = document.getElementById('composeTo');
const suggestionsContainer = document.getElementById('autocompleteSuggestions');
let selectedIndex = -1;

toField.addEventListener('input', async () => {
    const query = toField.value.trim();
    suggestionsContainer.innerHTML = '';
    selectedIndex = -1;

    if (query.length < 2 || toField.readOnly) return;

    try {
        const response = await fetch(`/api/search-users.php?q=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });
        const users = await response.json();

        if (users.length === 0) {
            suggestionsContainer.innerHTML = '<div class="autocomplete-item text-muted">No users found</div>';
            return;
        }

        users.forEach(user => {
            const item = document.createElement('div');
            item.classList.add('autocomplete-item');
            item.textContent = `${user.first_name} ${user.last_name} (${user.email || user.Student_ID || 'No email'})`;
            item.setAttribute('data-id', user.person_id);
            item.setAttribute('data-name', `${user.first_name} ${user.last_name}`);
            item.addEventListener('click', () => {
                toField.value = item.getAttribute('data-name');
                document.getElementById('composeToId').value = item.getAttribute('data-id');
                suggestionsContainer.innerHTML = '';
                toField.setAttribute('data-selected-id', item.getAttribute('data-id'));
            });
            suggestionsContainer.appendChild(item);
        });
    } catch (error) {
        suggestionsContainer.innerHTML = '<div class="autocomplete-item text-danger">Error fetching users</div>';
    }
});

// Keyboard navigation for autocomplete
toField.addEventListener('keydown', (e) => {
    if (toField.readOnly) return;
    const items = suggestionsContainer.getElementsByClassName('autocomplete-item');
    if (items.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
        updateSelection(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, -1);
        updateSelection(items);
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
        e.preventDefault();
        const selectedItem = items[selectedIndex];
        toField.value = selectedItem.getAttribute('data-name');
        document.getElementById('composeToId').value = selectedItem.getAttribute('data-id');
        suggestionsContainer.innerHTML = '';
        toField.setAttribute('data-selected-id', selectedItem.getAttribute('data-id'));
    }
});

function updateSelection(items) {
    Array.from(items).forEach((item, index) => {
        item.classList.toggle('active', index === selectedIndex);
        if (index === selectedIndex) {
            item.scrollIntoView({ block: 'nearest' });
        }
    });
}

// Clear suggestions when clicking outside
document.addEventListener('click', (e) => {
    if (!toField.contains(e.target) && !suggestionsContainer.contains(e.target)) {
        suggestionsContainer.innerHTML = '';
        selectedIndex = -1;
    }
});

// Client-side form validation
const composeForm = document.getElementById('composeForm');
composeForm.addEventListener('submit', (e) => {
    const toId = document.getElementById('composeToId').value;
    const toField = document.getElementById('composeTo');
    const subject = document.getElementById('composeSubject').value;
    const message = document.getElementById('composeMessage').value;

    if (!toId && !toField.value.trim()) {
        e.preventDefault();
        alert('Please select a recipient.');
        toField.focus();
    } else if (!subject.trim()) {
        e.preventDefault();
        alert('Please enter a subject.');
        document.getElementById('composeSubject').focus();
    } else if (!message.trim()) {
        e.preventDefault();
        alert('Please enter a message.');
        document.getElementById('composeMessage').focus();
    }
});
</script>
</body>
</html>