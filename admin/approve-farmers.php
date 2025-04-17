<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Admin role check
requireRole('admin');

// Get pending farmers
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'farmer' AND is_approved = 0");
$stmt->execute();
$pendingFarmers = $stmt->fetchAll();

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $userId = (int)$_POST['user_id'];
    $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
    $stmt->execute([$userId]);
    
    // In production, you would send an approval email here
    
    header("Location: approve-farmers.php?approved=1");
    exit();
}

$page_title = "Approve Farmers";
include __DIR__ . '/../../includes/header_admin.php';
?>

<div class="container">
    <h1>Pending Farmer Approvals</h1>
    
    <?php if (isset($_GET['approved'])): ?>
        <div class="alert alert-success">Farmer approved successfully!</div>
    <?php endif; ?>
    
    <?php if (empty($pendingFarmers)): ?>
        <div class="alert alert-info">No pending farmer approvals at this time.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Farm Name</th>
                    <th>Location</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingFarmers as $farmer): ?>
                    <tr>
                        <td><?= htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']) ?></td>
                        <td><?= htmlspecialchars($farmer['email']) ?></td>
                        <td><?= htmlspecialchars($farmer['farm_name']) ?></td>
                        <td><?= htmlspecialchars($farmer['farm_location']) ?></td>
                        <td><?= date('M j, Y', strtotime($farmer['created_at'])) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?= $farmer['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <button type="submit" name="approve" class="btn btn-success">Approve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer_admin.php'; ?>