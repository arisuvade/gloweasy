<?php
// Detect the current page context
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isAuthPage = str_contains($currentPath, '/includes/auth/');

// Set base path for assets based on directory depth
$basePath = '';
if ($isAuthPage) {
    $basePath = '../../';
} elseif (str_contains($currentPath, '/admin/') || 
          str_contains($currentPath, '/superadmin/') || 
          str_contains($currentPath, '/user/')) {
    $basePath = '../';
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Determine user type and dashboard path
$isSuperAdmin = isset($_SESSION['admin_id']) && $_SESSION['admin_branch'] === 'Owner';
$isAdmin = isset($_SESSION['admin_id']) && !$isSuperAdmin;
$isUser = isset($_SESSION['user_id']);

// Set dashboard path based on user type
$dashboardPath = '';
if ($isSuperAdmin) {
    $dashboardPath = $basePath . 'superadmin/dashboard.php';
} elseif ($isAdmin) {
    $dashboardPath = $basePath . 'admin/dashboard.php';
} elseif ($isUser) {
    $dashboardPath = $basePath . 'user/dashboard.php';
} else {
    $dashboardPath = $basePath . 'index.php'; // Fallback for non-logged in users
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bali Ayurveda Spa - <?= $pageTitle ?? 'Welcome' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/sidebar.css">
    <style>
        :root {
            --primary-white: #ffffff;
            --secondary-green: #1a5f3e;
            --accent-green: #3a9d7a;
            --light-green: #e8f5e9;
            --dark-text: #1e3d31;
            --medium-gray: #e0e0e0;
            --light-gray: #f8faf9;
            --deep-forest: #0d3b22;
            --mint-cream: #f0f7f4;
            --spa-teal: #4caf93;
            --sage-green: #8ba888;
            --gradient-start: #1a5f3e;
            --gradient-end: #3a9d7a;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Poppins', sans-serif;
            color: var(--dark-text);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .main-container {
            display: flex;
            flex: 1;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .sidebar {
            width: 250px;
            min-width: 250px;
            background: linear-gradient(180deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            padding: 20px 0;
            flex-shrink: 0;
            color: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 56px; /* Height of navbar */
        }

        .sidebar h2 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: white;
            padding: 12px 20px;
            margin: 5px 15px;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            background-color: rgba(255,255,255,0.05);
        }

        .sidebar a i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .sidebar a:hover, 
        .sidebar a.active {
            background-color: rgba(255,255,255,0.15);
            transform: translateX(3px);
            box-shadow: 2px 2px 8px rgba(0,0,0,0.1);
        }

        .sidebar a.active {
            font-weight: 500;
            background-color: rgba(255,255,255,0.2);
            border-left: 3px solid white;
        }

        .main-content {
            max-width: calc(100% - 125px); /* total of both margins */
            margin-left: 125px; 
            margin-right: auto;
            padding: 30px;
            background-color: var(--primary-white);
            min-height: calc(100vh - 56px);
            box-sizing: border-box;
        }

        /* Navbar styling */
        .navbar {
            background: linear-gradient(90deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            padding: 12px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            height: 56px;
        }

        .navbar-brand {
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            letter-spacing: 0.5px;
            font-size: 1.25rem;
        }

        .logout-link {
            color: white;
            border-color: rgba(255,255,255,0.5);
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }

        .logout-link:hover {
            background-color: rgba(255,255,255,0.15);
            border-color: white;
            transform: translateY(-1px);
        }

        /* Mobile styles */
        .navbar-toggler {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
            margin-right: 1rem;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .navbar-toggler {
                display: block;
            }
        }

        @media (min-width: 993px) {
            .sidebar {
                transform: translateX(0) !important;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <?php if(!$isAuthPage && ($isSuperAdmin || $isAdmin || $isUser)): ?>
                    <button class="navbar-toggler" type="button" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                <?php endif; ?>
                <a class="navbar-brand" href="<?= $dashboardPath ?>">
                    <i class="bi bi-flower1" style="font-size: 1.5rem;"></i>
                    Bali Ayurveda Spa
                </a>
                <?php if(!$isAuthPage && ($isSuperAdmin || $isAdmin || $isUser)): ?>
                    <div class="d-flex align-items-center">
                        <a href="<?= $basePath ?>includes/auth/logout.php" 
                           class="btn btn-outline-light btn-sm logout-link">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <?php if(!$isAuthPage && ($isSuperAdmin || $isAdmin || $isUser)): ?>
    <div class="main-container">
        <div class="sidebar" id="sidebar">
        <?php if($isSuperAdmin): ?>
            <a href="<?= $basePath ?>superadmin/dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= $basePath ?>superadmin/reports.php" class="<?= $currentPage == 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                <span>Reports</span>
            </a>
            
        <?php elseif($isAdmin): ?>
            <!-- Admin links -->
            <a href="<?= $basePath ?>admin/dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= $basePath ?>admin/manage_bookings.php" class="<?= $currentPage == 'manage_bookings.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Bookings</span>
            </a>
            <a href="<?= $basePath ?>admin/manage_therapists.php" class="<?= $currentPage == 'manage_therapists.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Therapists</span>
            </a>
            <a href="<?= $basePath ?>admin/manage_users.php" class="<?= $currentPage == 'manage_users.php' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Users</span>
                </a>
            <a href="<?= $basePath ?>admin/reports.php" class="<?= $currentPage == 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                <span>Reports</span>
            </a>
            
        <?php else: ?>
            <!-- User sidebar links -->
            <a href="<?= $basePath ?>user/dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= $basePath ?>user/book.php" class="<?= $currentPage == 'book.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i>
                <span>Book</span>
            </a>
            <a href="<?= $basePath ?>user/bookings.php" class="<?= $currentPage == 'bookings.php' ? 'active' : '' ?>">
                <i class="bi bi-list-check"></i>
                <span>Bookings</span>
            </a>
        <?php endif; ?>
        </div>
        <main class="main-content">
    <?php else: ?>
        <main class="container-full">
    <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Toggle sidebar on mobile
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        
        if (window.innerWidth <= 992 && 
            !sidebar.contains(event.target) && 
            !toggleBtn.contains(event.target) &&
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    });
</script>