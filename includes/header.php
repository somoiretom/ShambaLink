<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Shamba Link'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/auth.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
    <?php if (isset($custom_css)): ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/<?php echo $custom_css; ?>">
    <?php endif; ?>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="<?php echo BASE_URL; ?>" class="logo">
                    <img src="<?php echo BASE_URL; ?>/assets/images/ssg3.png" alt="" class="logo-img">
                    <span>Shamba Link</span>
                </a>
                
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>/insights.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'insights.php' ? 'active' : ''; ?>">Insights</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>/resources.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'resources.php' ? 'active' : ''; ?>">Resources</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>/weather-prediction.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'weather-prediction.php' ? 'active' : ''; ?>">Weather Predict</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>/about.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : ''; ?>">About Us</a>
                    </li>
                    
                    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role'])): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'admin/dashboard.php') !== false ? 'active' : ''; ?>">Admin Dashboard</a>
                            </li>
                        <?php elseif ($_SESSION['role'] === 'farmer'): ?>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/farmer/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'farmer/dashboard.php') !== false ? 'active' : ''; ?>">Farmer Dashboard</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'user/dashboard.php') !== false ? 'active' : ''; ?>">My Account</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="nav-link">Logout</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Mobile menu toggle -->
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </nav>
        </div>
    </header>