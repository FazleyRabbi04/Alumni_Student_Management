<?php
if (!isset($_SESSION)) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="../pages/dashboard.php">
            <i class="fas fa-graduation-cap me-2"></i>Alumni Relationship & Networking System
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
                        // Get unread message count
                        if (isset($_SESSION['user_id'])) {
                            $unread_query = "SELECT COUNT(*) as count FROM sends WHERE person_id = ? AND response = 'Unread'";
                            $unread_stmt = executeQuery($unread_query, [$_SESSION['user_id']]);
                            $unread_count = $unread_stmt ? $unread_stmt->fetch()['count'] : 0;
                            if ($unread_count > 0) {
                                echo '<span class="badge bg-danger ms-1">' . $unread_count . '</span>';
                            }
                        }
                        ?>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                       data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="../pages/profile.php">
                                <i class="fas fa-user me-2"></i>My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../pages/settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
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