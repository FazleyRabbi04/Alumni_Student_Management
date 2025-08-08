<?php
if (!isset($_SESSION)) {
    session_start();
}
?>

<!-- sidebar.php -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
<style>
    .dropdown-menu {
        width: 100%;
        background-color: #002147;
        border: none;
        padding: 0;
        margin: 0;
        border-radius: 0;
        text-align: left;
    }
    .dropdown-item {
        color: white !important;
        padding-left: 1.5rem;
    }
    .dropdown-item:hover {
        background-color: rgba(255, 255, 255, 0.15) !important;
        color: #f8f9fa !important;
    }
</style>

<div class="sidebar">
    <div class="user-dropdown dropdown w-100 text-center">
        <div
            class="user-name dropdown-toggle"
            id="sidebarUserDropdown"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            role="button"
        >
            <i class="fas fa-user-circle me-1"></i>
            <?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?>
        </div>
        <ul class="dropdown-menu" aria-labelledby="sidebarUserDropdown">
            <li><a class="dropdown-item" href="../pages/profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </div>

    <!-- You can add more sidebar nav items here -->
</div>

<!-- Include Bootstrap 5 JS bundle for dropdown to work -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>