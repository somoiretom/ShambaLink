<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

// Restrict access to admins only
requireRole('admin');

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = sanitize($_POST['csrf_token'] ?? '');
    if (!verifyCSRFToken($csrf_token)) {
        die("Invalid CSRF token");
    }

    if (isset($_POST['approve'])) {
        $farmerId = (int)$_POST['farmer_id'];
        $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
        $stmt->execute([$farmerId]);
        $_SESSION['success'] = "Farmer approved successfully";
    } elseif (isset($_POST['reject'])) {
        $farmerId = (int)$_POST['farmer_id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_approved = 0");
        $stmt->execute([$farmerId]);
        $_SESSION['success'] = "Farmer rejected successfully";
    }
}

// Get pending approvals
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'farmer' AND is_approved = 0 ORDER BY created_at DESC");
    $stmt->execute();
    $pendingApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Approvals error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading approvals";
}

$page_title = "Farmer Approvals";
include '../includes/header.php';
?>

<div class="container">
    <h1>Pending Farmer Approvals</h1>
    
    <?php if (!empty($pendingApprovals)): ?>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Farm</th>
                <th>Email</th>
                <th>Registered</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingApprovals as $farmer): ?>
            <tr>
                <td><?= htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']) ?></td>
                <td><?= htmlspecialchars($farmer['farm_name']) ?></td>
                <td><?= htmlspecialchars($farmer['email']) ?></td>
                <td><?= date('M j, Y', strtotime($farmer['created_at'])) ?></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="farmer_id" value="<?= $farmer['id'] ?>">
                        <button type="submit" name="approve" class="btn btn-success">Approve</button>
                        <button type="submit" name="reject" class="btn btn-danger" 
                                onclick="return confirm('Are you sure?')">Reject</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-info">No pending approvals</div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>