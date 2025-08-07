<?php
if (!isset($_SESSION)) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
/* --- Modern Clearful SaaS Navbar --- */
.clear-navbar {
    background: linear-gradient(90deg, rgba(52,132,255,0.88) 0%, rgba(63,207,255,0.91) 100%);
    box-shadow: 0 8px 32px 0 rgba(40,80,200,0.11);
    border-radius: 0 0 22px 22px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1.5px solid rgba(255,255,255,0.17);
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    padding-top: 0.10rem;
    padding-bottom: 0.10rem;
    z-index: 1001;
}

.clear-navbar .navbar-brand {
    font-weight: 700;
    letter-spacing: 1px;
    color: #fff !important;
    font-size: 1.37rem;
    text-shadow: 0 1px 14px rgba(0,0,0,0.09);
    display: flex;
    align-items: center;
    padding-right: 18px;
}
.clear-navbar .navbar-brand i {
    color: #7efff5;
    font-size: 1.65rem;
    margin-right: 10px;
    filter: drop-shadow(0 0 8px #5de0ff99);
}
.clear-navbar .nav-link {
    color: #f9fcff !important;
    font-weight: 500;
    font-size: 1.07rem;
    padding: 0.68rem 1.09rem;
    margin: 0 0.18rem;
    border-radius: 13px;
    background: transparent;
    position: relative;
    transition:
        color 0.18s,
        background 0.18s,
        box-shadow 0.18s;
}
.clear-navbar .nav-link.active, .clear-navbar .nav-link:hover {
    color: #176fff !important;
    background: rgba(255,255,255,0.92);
    box-shadow: 0 3px 18px 0 rgba(87,185,255,0.14);
    font-weight: 600;
    text-shadow: none;
}
.clear-navbar .badge {
    font-size: 0.73rem;
    vertical-align: middle;
    background: #ff5e80;
    color: #fff;
    font-weight: 600;
    box-shadow: 0 2px 8px 0 #ff99b34d;
}
.clear-navbar .dropdown-toggle {
    color: #f8fafd !important;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    border-radius: 12px;
    padding: 0.68rem 1.12rem;
    transition: color 0.2s, background 0.15s;
}
.clear-navbar .dropdown-toggle:hover, .clear-navbar .dropdown-toggle:focus {
    background: rgba(255,255,255,0.82);
    color: #176fff !important;
}
.clear-navbar .dropdown-menu {
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(9px);
    border-radius: 18px;
    box-shadow: 0 8px 32px 0 rgba(87,185,255,0.12);
    border: none;
    min-width: 195px;
    margin-top: 14px;
    padding: 0.8rem 0;
}
.clear-navbar .dropdown-item {
    color: #28355a;
    font-weight: 500;
    padding: 0.56rem 1.35rem;
    border-radius: 10px;
    transition: background 0.15s, color 0.16s;
}
.clear-navbar .dropdown-item:hover, .clear-navbar .dropdown-item:focus {
    background: linear-gradient(100deg,#e6f8ff 60%,#f0fcff 100%);
    color: #176fff;
}
.clear-navbar .dropdown-divider {
    border-top: 1px solid #e5e9f3;
}
@media (max-width: 991px) {
    .clear-navbar {
        border-radius: 0 0 12px 12px;
    }
    .clear-navbar .nav-link,
    .clear-navbar .dropdown-toggle {
        margin: 0.13rem 0;
        padding: 0.66rem 1rem;
        border-radius: 11px;
    }
    .clear-navbar .dropdown-menu {
        min-width: 150px;
        margin-top: 7px;
        border-radius: 14px;
    }
}
</style>

<nav class="navbar navbar-expand-lg clear-navbar sticky-top shadow-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="../pages/dashboard.php">
            <i class="fas fa-graduation-cap"></i>
            Alumni Relationship & Networking System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"
                       href="../pages/dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'events.php' ? 'active' : ''; ?>"
                       href="../pages/events.php">
                        <i class="fas fa-calendar me-1"></i>Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'jobs.php' ? 'active' : ''; ?>"
                       href="../pages/jobs.php">
                        <i class="fas fa-briefcase me-1"></i>Jobs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'alumni.php' ? 'active' : ''; ?>"
                       href="../pages/alumni.php">
                        <i class="fas fa-users me-1"></i>Network
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'communications.php' ? 'active' : ''; ?>"
                       href="../pages/communications.php">
                        <i class="fas fa-envelope me-1"></i>Messages
                        <?php
                        if (isset($_SESSION['user_id'])) {
                            $unread_query = "SELECT COUNT(*) as count FROM sends WHERE person_id = ? AND response = 'Unread'";
                            $unread_stmt = executeQuery($unread_query, [$_SESSION['user_id']]);
                            $unread_count = $unread_stmt ? $unread_stmt->fetch()['count'] : 0;
                            if ($unread_count > 0) {
                                echo '<span class="badge rounded-pill ms-1">' . $unread_count . '</span>';
                            }
                        }
                        ?>
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="../pages/profile.php">
                                <i class="fas fa-user me-2"></i>My Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
