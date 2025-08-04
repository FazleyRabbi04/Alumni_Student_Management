<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"
                   href="../pages/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"
                   href="../pages/profile.php">
                    <i class="fas fa-user"></i>My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'events.php' ? 'active' : ''; ?>"
                   href="../pages/events.php">
                    <i class="fas fa-calendar"></i>Events
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'jobs.php' ? 'active' : ''; ?>"
                   href="../pages/jobs.php">
                    <i class="fas fa-briefcase"></i>Job Board
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'alumni.php' ? 'active' : ''; ?>"
                   href="../pages/alumni.php">
                    <i class="fas fa-users"></i>Alumni Network
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'students.php' ? 'active' : ''; ?>"
                   href="../pages/students.php">
                    <i class="fas fa-user-graduate"></i>Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'communications.php' ? 'active' : ''; ?>"
                   href="../pages/communications.php">
                    <i class="fas fa-envelope"></i>Communications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'sessions.php' ? 'active' : ''; ?>"
                   href="../pages/sessions.php">
                    <i class="fas fa-chalkboard-teacher"></i>Sessions
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Quick Links</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="../pages/achievements.php">
                    <i class="fas fa-trophy"></i>Achievements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../pages/interests.php">
                    <i class="fas fa-heart"></i>Interests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../pages/skills.php">
                    <i class="fas fa-tools"></i>Skills
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../pages/reports.php">
                    <i class="fas fa-chart-bar"></i>Reports
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Settings</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="../pages/settings.php">
                    <i class="fas fa-cog"></i>Account Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../pages/privacy.php">
                    <i class="fas fa-shield-alt"></i>Privacy
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../pages/help.php">
                    <i class="fas fa-question-circle"></i>Help & Support
                </a>
            </li>
        </ul>
    </div>
</nav>