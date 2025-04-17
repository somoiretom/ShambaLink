<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

requireRole('admin');

// Handle backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = sanitize($_POST['csrf_token'] ?? '');
    if (!verifyCSRFToken($csrf_token)) {
        die("Invalid CSRF token");
    }

    try {
        $backupFile = 'backups/farmer_platform_' . date("Y-m-d_H-i-s") . '.sql';
        
        // Get all tables
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $output = "";
        foreach ($tables as $table) {
            $output .= "DROP TABLE IF EXISTS $table;\n";
            $stmt = $pdo->query("SHOW CREATE TABLE $table");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $output .= $row[1] . ";\n\n";
            
            $stmt = $pdo->query("SELECT * FROM $table");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $output .= "INSERT INTO $table VALUES(";
                $values = array_map(function($value) use ($pdo) {
                    return $pdo->quote($value);
                }, array_values($row));
                $output .= implode(',', $values) . ");\n";
            }
            $output .= "\n";
        }
        
        // Save to file
        if (!is_dir('backups')) {
            mkdir('backups', 0755, true);
        }
        
        file_put_contents($backupFile, $output);
        
        // Offer download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($backupFile).'"');
        header('Content-Length: ' . filesize($backupFile));
        readfile($backupFile);
        exit();
        
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage());
        $_SESSION['error'] = "Error creating backup: " . $e->getMessage();
    }
}

$page_title = "Database Backup";
include '../includes/header.php';
?>

<div class="container">
    <h1>Database Backup</h1>
    
    <div class="alert alert-warning">
        <strong>Warning:</strong> This will generate a complete backup of the database.
    </div>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <button type="submit" name="create_backup" class="btn btn-primary">
            <i class="fas fa-download"></i> Download Backup
        </button>
    </form>
    
    <div class="mt-4">
        <h3>Recent Backups</h3>
        <?php if (is_dir('backups') && count(glob('backups/*.sql')): ?>
        <ul>
            <?php foreach (glob('backups/*.sql') as $file): ?>
            <li>
                <?= basename($file) ?> 
                (<a href="<?= BASE_URL . '/admin/backups/' . basename($file) ?>">download</a>)
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p>No backups found</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>