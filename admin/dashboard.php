<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

// Restrict access to admins only
requireRole('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle farmer approval/rejection
    if (isset($_POST['action'])) {
        $csrf_token = sanitize($_POST['csrf_token'] ?? '');
        if (!verifyCSRFToken($csrf_token)) {
            die("Invalid CSRF token");
        }
        
        $farmerId = (int)($_POST['farmer_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 1, approved_at = NOW() WHERE id = ?");
            if ($stmt->execute([$farmerId])) {
                $_SESSION['success'] = "Farmer approved successfully";
                
                // Send approval notification
                $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
                $stmt->execute([$farmerId]);
                $farmer = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($farmer) {
                    sendApprovalEmail($farmer['email'] ?? '', $farmer['first_name'] ?? '');
                }
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_approved = 0");
            if ($stmt->execute([$farmerId])) {
                $_SESSION['success'] = "Farmer registration rejected";
            }
        }
    }
    
    // Handle user management actions
    if (isset($_POST['user_action'])) {
        $csrf_token = sanitize($_POST['csrf_token'] ?? '');
        if (!verifyCSRFToken($csrf_token)) {
            die("Invalid CSRF token");
        }
        
        $userId = (int)($_POST['user_id'] ?? 0);
        $userAction = $_POST['user_action'] ?? '';
        
        if ($userAction === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            if ($stmt->execute([$userId])) {
                $_SESSION['success'] = "User deactivated successfully";
            }
        } elseif ($userAction === 'activate') {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            if ($stmt->execute([$userId])) {
                $_SESSION['success'] = "User activated successfully";
            }
        } elseif ($userAction === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            if ($stmt->execute([$userId])) {
                $_SESSION['success'] = "User deleted successfully";
            }
        }
    }
    
    // Handle homepage content updates
    if (isset($_POST['update_content'])) {
        $csrf_token = sanitize($_POST['csrf_token'] ?? '');
        if (!verifyCSRFToken($csrf_token)) {
            die("Invalid CSRF token");
        }
        
        $contentType = sanitize($_POST['content_type'] ?? '');
        $contentValue = sanitize($_POST['content_value'] ?? '');
        
        if (in_array($contentType, ['vision', 'mission', 'hero', 'testimonials'])) {
            $stmt = $pdo->prepare("INSERT INTO website_content (content_type, content_value) 
                                   VALUES (?, ?)
                                   ON DUPLICATE KEY UPDATE content_value = ?");
            if ($stmt->execute([$contentType, $contentValue, $contentValue])) {
                $_SESSION['success'] = "Content updated successfully";
            }
        }
    }
    
    // Handle hero video update
    if (isset($_FILES['hero_video']) && $_FILES['hero_video']['error'] == UPLOAD_ERR_OK) {
        $csrf_token = sanitize($_POST['csrf_token'] ?? '');
        if (!verifyCSRFToken($csrf_token)) {
            die("Invalid CSRF token");
        }
        
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/hero/';
        $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (in_array($_FILES['hero_video']['type'], $allowedTypes) && 
            $_FILES['hero_video']['size'] <= $maxSize) {
            
            $fileName = 'hero_video_' . time() . '.' . pathinfo($_FILES['hero_video']['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['hero_video']['tmp_name'], $filePath)) {
                // Update the hero video path in database
                $stmt = $pdo->prepare("INSERT INTO website_content (content_type, content_value) 
                                       VALUES ('hero_video', ?)
                                       ON DUPLICATE KEY UPDATE content_value = ?");
                if ($stmt->execute([$fileName, $fileName])) {
                    $_SESSION['success'] = "Hero video updated successfully";
                }
            } else {
                $_SESSION['error'] = "Error uploading video";
            }
        } else {
            $_SESSION['error'] = "Invalid video file. Only MP4, WebM or Ogg files up to 10MB are allowed.";
        }
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action'])) {
        $csrf_token = sanitize($_POST['csrf_token'] ?? '');
        if (!verifyCSRFToken($csrf_token)) {
            die("Invalid CSRF token");
        }
        
        $selectedFarmers = $_POST['selected_farmers'] ?? [];
        $bulkAction = $_POST['bulk_action'] ?? '';
        
        if (!empty($selectedFarmers) && is_array($selectedFarmers)) {
            if ($bulkAction === 'approve_selected') {
                $placeholders = implode(',', array_fill(0, count($selectedFarmers), '?'));
                $stmt = $pdo->prepare("UPDATE users SET is_approved = 1, approved_at = NOW() WHERE id IN ($placeholders)");
                if ($stmt->execute($selectedFarmers)) {
                    $_SESSION['success'] = count($selectedFarmers) . " farmers approved successfully";
                }
            } elseif ($bulkAction === 'reject_selected') {
                $placeholders = implode(',', array_fill(0, count($selectedFarmers), '?'));
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND is_approved = 0");
                if ($stmt->execute($selectedFarmers)) {
                    $_SESSION['success'] = count($selectedFarmers) . " farmers rejected successfully";
                }
            } elseif ($bulkAction === 'deactivate_selected') {
                $placeholders = implode(',', array_fill(0, count($selectedFarmers), '?'));
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders)");
                if ($stmt->execute($selectedFarmers)) {
                    $_SESSION['success'] = count($selectedFarmers) . " users deactivated successfully";
                }
            } elseif ($bulkAction === 'activate_selected') {
                $placeholders = implode(',', array_fill(0, count($selectedFarmers), '?'));
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders)");
                if ($stmt->execute($selectedFarmers)) {
                    $_SESSION['success'] = count($selectedFarmers) . " users activated successfully";
                }
            } elseif ($bulkAction === 'delete_selected') {
                $placeholders = implode(',', array_fill(0, count($selectedFarmers), '?'));
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role != 'admin'");
                if ($stmt->execute($selectedFarmers)) {
                    $_SESSION['success'] = count($selectedFarmers) . " users deleted successfully";
                }
            }
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Check session timeout
if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
    logout();
}
$_SESSION['last_activity'] = time();

// Initialize stats with default values
$stats = [
    // Farmer stats
    'total_farmers' => 0,
    'active_farmers' => 0,
    'pending_approval' => 0,
    'suspended_farmers' => 0,
    'new_farmers_today' => 0,
    'new_farmers_week' => 0,
    
    // Consumer stats
    'total_consumers' => 0,
    'active_consumers' => 0,
    'new_consumers_today' => 0,
    'new_consumers_week' => 0,
    
    // Admin stats
    'total_admins' => 0,
    
    // Product stats
    'total_products' => 0,
    'active_products' => 0,
    'new_products_today' => 0,
    
    // Order stats
    'total_orders' => 0,
    'completed_orders' => 0,
    'pending_orders' => 0,
    'total_revenue' => 0,
    'today_revenue' => 0,
    'week_revenue' => 0
];

// Get statistics
try {
    // Farmer statistics
    $stats['total_farmers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer'")->fetchColumn();
    $stats['active_farmers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND is_active = 1 AND is_approved = 1")->fetchColumn();
    $stats['pending_approval'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND is_approved = 0")->fetchColumn();
    $stats['suspended_farmers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND is_active = 0")->fetchColumn();
    $stats['new_farmers_today'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['new_farmers_week'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    
    // Consumer statistics
    $stats['total_consumers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'consumer'")->fetchColumn();
    $stats['active_consumers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'consumer' AND is_active = 1")->fetchColumn();
    $stats['new_consumers_today'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'consumer' AND DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['new_consumers_week'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'consumer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    
    // Admin statistics
    $stats['total_admins'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    
    // Product statistics
    $stats['total_products'] = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stats['active_products'] = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    $stats['new_products_today'] = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    
    // Order statistics
    $stats['total_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['completed_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
    $stats['pending_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $stats['total_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn();
    $stats['today_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed' AND DATE(order_date) = CURDATE()")->fetchColumn();
    $stats['week_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed' AND order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading dashboard statistics";
}

// Get pending approvals
$pendingApprovals = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.farm_name, u.created_at, 
               u.profile_pic, u.farm_location, u.farm_description
        FROM users u
        WHERE u.role = 'farmer' AND u.is_approved = 0
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $pendingApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Pending approvals query error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading pending approvals";
}

// Get recent users (farmers and consumers)
$recentUsers = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, role, is_active, is_approved, created_at
        FROM users
        WHERE role IN ('farmer', 'consumer')
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent users query error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading recent users";
}

// Get recent activity
$recentActivity = [];
try {
    $stmt = $pdo->prepare("
        (SELECT 'farmer' AS type, CONCAT('New farmer: ', first_name, ' ', last_name) AS title, created_at 
         FROM users WHERE role = 'farmer' ORDER BY created_at DESC LIMIT 3)
        UNION
        (SELECT 'consumer' AS type, CONCAT('New consumer: ', first_name, ' ', last_name) AS title, created_at 
         FROM users WHERE role = 'consumer' ORDER BY created_at DESC LIMIT 3)
        UNION
        (SELECT 'product' AS type, CONCAT('New product: ', title) AS title, created_at 
         FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3)
        UNION
        (SELECT 'order' AS type, CONCAT('New order #', id) AS title, created_at 
         FROM orders WHERE status IN ('completed', 'processing') ORDER BY created_at DESC LIMIT 3)
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent activity query error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading recent activity";
}

// Get current homepage content
$homepageContent = [
    'vision' => '',
    'mission' => '',
    'hero' => '',
    'testimonials' => '',
    'hero_video' => ''
];

try {
    $stmt = $pdo->query("SELECT content_type, content_value FROM website_content");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        if (array_key_exists($row['content_type'], $homepageContent)) {
            $homepageContent[$row['content_type']] = $row['content_value'];
        }
    }
} catch (PDOException $e) {
    error_log("Homepage content query error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading homepage content";
}

// Get weather data with proper error handling
$weatherData = [
    'city' => 'Unknown',
    'temp' => 'N/A',
    'description' => 'Weather data unavailable',
    'icon' => '01d',
    'humidity' => 'N/A',
    'wind_speed' => 'N/A',
    'pressure' => 'N/A',
    'forecast' => []
];

try {
    $apiWeatherData = getWeatherData(DEFAULT_LAT, DEFAULT_LON);
    if (!empty($apiWeatherData)) {
        $weatherData = array_merge($weatherData, $apiWeatherData);
    } else {
        $weatherData = array_merge($weatherData, getDefaultWeather());
        error_log("Falling back to default weather data");
    }
} catch (Exception $e) {
    error_log("Weather API error: " . $e->getMessage());
    $weatherData = array_merge($weatherData, getDefaultWeather());
}

$page_title = "Admin Dashboard";
$custom_css = [
    BASE_URL . "/assets/css/admin.css",
    BASE_URL . "/assets/css/weather.css",
    BASE_URL . "/assets/css/charts.css"
];
$custom_js = [
    BASE_URL . "/assets/js/chart.min.js",
    BASE_URL . "/assets/js/admin-dashboard.js"
];

$csrf_token = generateCSRFToken();
include $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/header.php';
?>

<div class="admin-dashboard">
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="admin-header">
            <h1>Dashboard Overview</h1>
            <div class="admin-actions">
                <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/farmers.php" class="btn btn-primary">
                    <i class="material-icons">agriculture</i> Farmers
                </a>
                <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/consumers.php" class="btn btn-primary">
                    <i class="material-icons">people</i> Consumers
                </a>
                <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/approvals.php" class="btn btn-warning">
                    <i class="material-icons">approval</i> Approvals (<?php echo $stats['pending_approval']; ?>)
                </a>
                <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/home-content.php" class="btn btn-info">
                    <i class="material-icons">home</i> Home Content
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <!-- Farmer Stats -->
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">agriculture</i></div>
                <div class="stat-value"><?php echo $stats['total_farmers']; ?></div>
                <div class="stat-label">Total Farmers</div>
                <div class="stat-trend">
                    <?php if ($stats['new_farmers_today'] > 0): ?>
                        <span class="trend-up">+<?php echo $stats['new_farmers_today']; ?> today</span>
                    <?php endif; ?>
                    <?php if ($stats['new_farmers_week'] > 0): ?>
                        <span class="trend-up">+<?php echo $stats['new_farmers_week']; ?> this week</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">check_circle</i></div>
                <div class="stat-value"><?php echo $stats['active_farmers']; ?></div>
                <div class="stat-label">Active Farmers</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon"><i class="material-icons">hourglass_empty</i></div>
                <div class="stat-value"><?php echo $stats['pending_approval']; ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
            
            <!-- Consumer Stats -->
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">people</i></div>
                <div class="stat-value"><?php echo $stats['total_consumers']; ?></div>
                <div class="stat-label">Total Consumers</div>
                <div class="stat-trend">
                    <?php if ($stats['new_consumers_today'] > 0): ?>
                        <span class="trend-up">+<?php echo $stats['new_consumers_today']; ?> today</span>
                    <?php endif; ?>
                    <?php if ($stats['new_consumers_week'] > 0): ?>
                        <span class="trend-up">+<?php echo $stats['new_consumers_week']; ?> this week</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">check_circle</i></div>
                <div class="stat-value"><?php echo $stats['active_consumers']; ?></div>
                <div class="stat-label">Active Consumers</div>
            </div>
            
            <!-- Admin Stats -->
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">security</i></div>
                <div class="stat-value"><?php echo $stats['total_admins']; ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            
            <!-- Product Stats -->
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">shopping_basket</i></div>
                <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                <div class="stat-label">Total Products</div>
                <div class="stat-trend">
                    <?php if ($stats['new_products_today'] > 0): ?>
                        <span class="trend-up">+<?php echo $stats['new_products_today']; ?> today</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">check_circle</i></div>
                <div class="stat-value"><?php echo $stats['active_products']; ?></div>
                <div class="stat-label">Active Products</div>
            </div>
            
            <!-- Order Stats -->
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">receipt</i></div>
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">check_circle</i></div>
                <div class="stat-value"><?php echo $stats['completed_orders']; ?></div>
                <div class="stat-label">Completed Orders</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="material-icons">hourglass_empty</i></div>
                <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
                <div class="stat-label">Pending Orders</div>
            </div>
            
            <!-- Revenue Stats -->
            <div class="stat-card success">
                <div class="stat-icon"><i class="material-icons">attach_money</i></div>
                <div class="stat-value">Ksh.<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon"><i class="material-icons">today</i></div>
                <div class="stat-value">Ksh.<?php echo number_format($stats['today_revenue'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon"><i class="material-icons">date_range</i></div>
                <div class="stat-value">Ksh. <?php echo number_format($stats['week_revenue'], 2); ?></div>
                <div class="stat-label">Weekly Revenue</div>
            </div>
        </div>

        <div class="dashboard-main">
            <!-- Left Column -->
            <div class="dashboard-column">
                <!-- Pending Approvals -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="material-icons">warning</i> Pending Farmer Approvals (<?php echo $stats['pending_approval']; ?>)</h2>
                        <div class="card-actions">
                            <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/approvals.php" class="btn btn-sm btn-outline">
                                View All
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($pendingApprovals)): ?>
                        <form method="POST" id="bulk-approval-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="table-responsive">
                                <table class="approvals-table">
                                    <thead>
                                        <tr>
                                            <th width="30"><input type="checkbox" id="select-all"></th>
                                            <th>Farmer Details</th>
                                            <th>Farm Info</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingApprovals as $farmer): ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_farmers[]" value="<?php echo $farmer['id']; ?>"></td>
                                            <td>
                                                <div class="user-info">
                                                    <?php if (!empty($farmer['profile_pic'])): ?>
                                                        <img src="<?php echo PROFILE_UPLOAD_URL . htmlspecialchars($farmer['profile_pic'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                             alt="Profile" class="user-avatar">
                                                    <?php else: ?>
                                                        <div class="user-avatar default-avatar">
                                                            <?php echo strtoupper(substr($farmer['first_name'] ?? '', 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="user-details">
                                                        <strong><?php echo htmlspecialchars(($farmer['first_name'] ?? '') . ' ' . ($farmer['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <small><?php echo htmlspecialchars($farmer['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($farmer['farm_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <small><?php echo htmlspecialchars($farmer['farm_location'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td><?php echo !empty($farmer['created_at']) ? date('M j, Y', strtotime($farmer['created_at'])) : 'N/A'; ?></td>
                                            <td class="actions">
                                                <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/farmers.php?action=view&id=<?php echo $farmer['id']; ?>" 
                                                   class="btn btn-sm btn-outline" 
                                                   title="View Details">
                                                    <i class="material-icons">visibility</i>
                                                </a>
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" 
                                                   title="Approve">
                                                    <i class="material-icons">check</i>
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to reject this farmer?')"
                                                   title="Reject">
                                                    <i class="material-icons">close</i>
                                                </button>
                                                <input type="hidden" name="farmer_id" value="<?php echo $farmer['id']; ?>">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="bulk-actions">
                                <select name="bulk_action" class="form-control">
                                    <option value="">Bulk Actions</option>
                                    <option value="approve_selected">Approve Selected</option>
                                    <option value="reject_selected">Reject Selected</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="material-icons">check_circle</i>
                            <p>No pending approvals</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Users -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="material-icons">group</i> Recent Users</h2>
                        <div class="card-actions">
                            <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/users.php" class="btn btn-sm btn-outline">
                                View All
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($recentUsers)): ?>
                        <form method="POST" id="user-management-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="table-responsive">
                                <table class="users-table">
                                    <thead>
                                        <tr>
                                            <th width="30"><input type="checkbox" id="select-all-users"></th>
                                            <th>User Details</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentUsers as $user): ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>"></td>
                                            <td>
                                                <div class="user-details">
                                                    <strong><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <small><?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $user['role'] === 'farmer' ? 'badge-primary' : 'badge-secondary'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['role'] === 'farmer' && !$user['is_approved']): ?>
                                                    <span class="badge badge-warning">Pending Approval</span>
                                                <?php elseif ($user['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : 'N/A'; ?></td>
                                            <td class="actions">
                                                <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/users.php?action=view&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-outline" 
                                                   title="View Details">
                                                    <i class="material-icons">visibility</i>
                                                </a>
                                                <?php if ($user['is_active']): ?>
                                                    <button type="submit" name="user_action" value="deactivate" class="btn btn-sm btn-warning" 
                                                       onclick="return confirm('Are you sure you want to deactivate this user?')"
                                                       title="Deactivate">
                                                        <i class="material-icons">pause</i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="user_action" value="activate" class="btn btn-sm btn-success" 
                                                       title="Activate">
                                                        <i class="material-icons">play_arrow</i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="submit" name="user_action" value="delete" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.')"
                                                   title="Delete">
                                                    <i class="material-icons">delete</i>
                                                </button>
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="bulk-actions">
                                <select name="bulk_action" class="form-control">
                                    <option value="">Bulk Actions</option>
                                    <option value="activate_selected">Activate Selected</option>
                                    <option value="deactivate_selected">Deactivate Selected</option>
                                    <option value="delete_selected">Delete Selected</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="material-icons">group_off</i>
                            <p>No recent users</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Activity -->
                <div class="dashboard-card">
                    <h2><i class="material-icons">history</i> Recent Activity</h2>
                    <ul class="activity-feed">
                        <?php foreach ($recentActivity as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <?php switch($activity['type'] ?? '') {
                                        case 'farmer': echo '<i class="material-icons">agriculture</i>'; break;
                                        case 'consumer': echo '<i class="material-icons">person</i>'; break;
                                        case 'product': echo '<i class="material-icons">shopping_basket</i>'; break;
                                        case 'order': echo '<i class="material-icons">receipt</i>'; break;
                                        default: echo '<i class="material-icons">info</i>';
                                    } ?>
                                </div>
                                <div class="activity-content">
                                    <p><?php echo htmlspecialchars($activity['title'] ?? 'No title', ENT_QUOTES, 'UTF-8'); ?></p>
                                    <small><?php echo !empty($activity['created_at']) ? date('M j, Y g:i A', strtotime($activity['created_at'])) : 'N/A'; ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($recentActivity)): ?>
                            <li class="activity-item empty">
                                <div class="activity-content">
                                    <p>No recent activity</p>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Right Column -->
            <div class="dashboard-column">
                <!-- Weather Widget -->
                <div class="dashboard-card weather-widget">
                    <h2><i class="material-icons">cloud</i> Weather Forecast 
                        <small><?php echo htmlspecialchars($weatherData['city'], ENT_QUOTES, 'UTF-8'); ?></small>
                    </h2>
                    
                    <div class="current-weather">
                        <div class="weather-main">
                            <div class="weather-icon">
                                <img src="https://openweathermap.org/img/wn/<?php echo htmlspecialchars($weatherData['icon'], ENT_QUOTES, 'UTF-8'); ?>@2x.png" 
                                     alt="<?php echo htmlspecialchars($weatherData['description'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="weather-temp">
                                <span class="temp-value"><?php echo htmlspecialchars($weatherData['temp'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="temp-unit">°C</span>
                            </div>
                        </div>
                        <div class="weather-details">
                            <p class="weather-description"><?php echo htmlspecialchars($weatherData['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="weather-stats">
                                <div class="weather-stat">
                                    <i class="material-icons">opacity</i>
                                    <span><?php echo htmlspecialchars($weatherData['humidity'], ENT_QUOTES, 'UTF-8'); ?>%</span>
                                </div>
                                <div class="weather-stat">
                                    <i class="material-icons">air</i>
                                    <span><?php echo htmlspecialchars($weatherData['wind_speed'], ENT_QUOTES, 'UTF-8'); ?> km/h</span>
                                </div>
                                <div class="weather-stat">
                                    <i class="material-icons">compress</i>
                                    <span><?php echo htmlspecialchars($weatherData['pressure'], ENT_QUOTES, 'UTF-8'); ?> hPa</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($weatherData['forecast'])): ?>
                    <div class="weather-forecast">
                        <div class="forecast-header">5-Day Forecast</div>
                        <div class="forecast-items">
                            <?php foreach ($weatherData['forecast'] as $forecast): ?>
                                <div class="forecast-item">
                                    <div class="forecast-day"><?php echo !empty($forecast['date']) ? date('D', strtotime($forecast['date'])) : 'N/A'; ?></div>
                                    <div class="forecast-icon">
                                        <img src="https://openweathermap.org/img/wn/<?php echo htmlspecialchars($forecast['icon'] ?? '01d', ENT_QUOTES, 'UTF-8'); ?>.png" 
                                             alt="<?php echo htmlspecialchars($forecast['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="forecast-temp">
                                        <span class="temp-max"><?php echo htmlspecialchars($forecast['temp_max'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>°</span>
                                        <span class="temp-min"><?php echo htmlspecialchars($forecast['temp_min'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>°</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="weather-footer">
                        <small>Last updated: <?php echo date('M j, g:i A'); ?></small>
                        <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/weather.php" class="btn btn-sm btn-outline">
                            <i class="material-icons">refresh</i> Refresh
                        </a>
                    </div>
                </div>

                <!-- Home Content Management -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="material-icons">home</i> Homepage Content</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="form-group">
                                <label for="hero_content">Hero Section Text</label>
                                <textarea name="content_value" id="hero_content" class="form-control" 
                                          placeholder="Enter hero section text"><?php echo htmlspecialchars($homepageContent['hero'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <input type="hidden" name="content_type" value="hero">
                                <button type="submit" name="update_content" class="btn btn-sm btn-primary mt-2">
                                    Update Hero Text
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label for="hero_video">Hero Section Video</label>
                                <input type="file" name="hero_video" id="hero_video" class="form-control" accept="video/mp4,video/webm,video/ogg">
                                <small class="text-muted">Max 10MB. MP4, WebM or Ogg format.</small>
                                <?php if (!empty($homepageContent['hero_video'])): ?>
                                    <div class="current-video mt-2">
                                        <small>Current video: <?php echo htmlspecialchars($homepageContent['hero_video'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <video width="100%" controls class="mt-2">
                                            <source src="<?php echo BASE_URL . '/uploads/hero/' . htmlspecialchars($homepageContent['hero_video'], ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </div>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-sm btn-primary mt-2">
                                    Update Hero Video
                                </button>
                            </div>
                        </form>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="form-group">
                                <label for="vision_content">Vision Statement</label>
                                <textarea name="content_value" id="vision_content" class="form-control" 
                                          placeholder="Enter vision statement"><?php echo htmlspecialchars($homepageContent['vision'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <input type="hidden" name="content_type" value="vision">
                                <button type="submit" name="update_content" class="btn btn-sm btn-primary mt-2">
                                    Update Vision
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label for="mission_content">Mission Statement</label>
                                <textarea name="content_value" id="mission_content" class="form-control" 
                                          placeholder="Enter mission statement"><?php echo htmlspecialchars($homepageContent['mission'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <input type="hidden" name="content_type" value="mission">
                                <button type="submit" name="update_content" class="btn btn-sm btn-primary mt-2">
                                    Update Mission
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label for="testimonials_content">Testimonials</label>
                                <textarea name="content_value" id="testimonials_content" class="form-control" 
                                          placeholder="Enter testimonials (one per line, format: Name: Testimonial text)"
                                          rows="4"><?php echo htmlspecialchars($homepageContent['testimonials'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <input type="hidden" name="content_type" value="testimonials">
                                <button type="submit" name="update_content" class="btn btn-sm btn-primary mt-2">
                                    Update Testimonials
                                </button>
                            </div>
                        </form>
                        
                        <div class="content-actions mt-3">
                            <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/partners.php" class="btn btn-secondary">
                                <i class="material-icons">groups</i> Manage Partners
                            </a>
                            <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/testimonials.php" class="btn btn-secondary">
                                <i class="material-icons">format_quote</i> Manage Testimonials
                            </a>
                        </div>
                    </div>
                </div>

                <!-- User Distribution Chart -->
                <div class="dashboard-card">
                    <h2><i class="material-icons">pie_chart</i> User Distribution</h2>
                    <div class="chart-container">
                        <canvas id="userDistributionChart"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h2><i class="material-icons">flash_on</i> Quick Actions</h2>
                    <div class="action-grid">
                        <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/farmers.php?action=add" class="action-card">
                            <i class="material-icons">person_add</i>
                            <span>Add Farmer</span>
                        </a>
                        <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/consumers.php?action=add" class="action-card">
                            <i class="material-icons">person_add</i>
                            <span>Add Consumer</span>
                        </a>
                        <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/products.php?action=add" class="action-card">
                            <i class="material-icons">add_shopping_cart</i>
                            <span>Add Product</span>
                        </a>
                        <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/insights.php?action=add" class="action-card">
                            <i class="material-icons">article</i>
                            <span>Add Insight</span>
                        </a>
                        <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/partners.php?action=add" class="action-card">
                            <i class="material-icons">group_add</i>
                            <span>Add Partner</span>
                        </a>
                        <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/testimonials.php?action=add" class="action-card">
                            <i class="material-icons">format_quote</i>
                            <span>Add Testimonial</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // User Distribution Chart
    const ctx = document.getElementById('userDistributionChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Farmers', 'Consumers', 'Admins'],
            datasets: [{
                data: [
                    <?php echo $stats['total_farmers']; ?>,
                    <?php echo $stats['total_consumers']; ?>,
                    <?php echo $stats['total_admins']; ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Bulk selection for approvals
    document.getElementById('select-all').addEventListener('change', function(e) {
        const checkboxes = document.querySelectorAll('input[name="selected_farmers[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = e.target.checked;
        });
    });
    
    // Bulk selection for users
    document.getElementById('select-all-users').addEventListener('change', function(e) {
        const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = e.target.checked;
        });
    });
    
    // Bulk form submission
    document.getElementById('bulk-approval-form').addEventListener('submit', function(e) {
        const bulkAction = this.elements['bulk_action'].value;
        const selectedCount = document.querySelectorAll('input[name="selected_farmers[]"]:checked').length;
        
        if (bulkAction && selectedCount === 0) {
            alert('Please select at least one farmer');
            e.preventDefault();
        } else if (bulkAction === 'reject_selected' && !confirm('Are you sure you want to reject the selected farmers?')) {
            e.preventDefault();
        }
    });
    
    // User management form submission
    document.getElementById('user-management-form').addEventListener('submit', function(e) {
        const bulkAction = this.elements['bulk_action'].value;
        const selectedCount = document.querySelectorAll('input[name="selected_users[]"]:checked').length;
        
        if (bulkAction && selectedCount === 0) {
            alert('Please select at least one user');
            e.preventDefault();
        } else if (bulkAction === 'delete_selected' && !confirm('Are you sure you want to delete the selected users? This cannot be undone.')) {
            e.preventDefault();
        } else if (bulkAction === 'deactivate_selected' && !confirm('Are you sure you want to deactivate the selected users?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/footer.php'; ?>