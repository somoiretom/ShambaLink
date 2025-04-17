<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

requireRole('admin');

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = sanitize($_POST['csrf_token'] ?? '');
    if (!verifyCSRFToken($csrf_token)) {
        die("Invalid CSRF token");
    }

    $reportType = $_POST['report_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    try {
        switch ($reportType) {
            case 'sales':
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_date BETWEEN ? AND ?");
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'farmers':
                $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'farmer' AND created_at BETWEEN ? AND ?");
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            default:
                $reportData = [];
        }
        
        // Generate CSV if requested
        if (isset($_POST['generate_csv'])) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="report_'.date('Ymd').'.csv"');
            
            $output = fopen('php://output', 'w');
            if (!empty($reportData)) {
                fputcsv($output, array_keys($reportData[0]));
                foreach ($reportData as $row) {
                    fputcsv($output, $row);
                }
            }
            fclose($output);
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("Report error: " . $e->getMessage());
        $_SESSION['error'] = "Error generating report";
    }
}

$page_title = "Reports";
include '../includes/header.php';
?>

<div class="container">
    <h1>Generate Reports</h1>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        
        <div class="form-group">
            <label>Report Type</label>
            <select name="report_type" class="form-control">
                <option value="sales">Sales Report</option>
                <option value="farmers">New Farmers</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" required>
        </div>
        
        <button type="submit" name="generate_report" class="btn btn-primary">Generate Report</button>
        <button type="submit" name="generate_csv" class="btn btn-success">Download CSV</button>
    </form>
    
    <?php if (!empty($reportData)): ?>
    <div class="report-results mt-4">
        <h3>Report Results</h3>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <?php foreach (array_keys($reportData[0]) as $column): ?>
                        <th><?= ucfirst(str_replace('_', ' ', $column)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $row): ?>
                    <tr>
                        <?php foreach ($row as $value): ?>
                        <td><?= htmlspecialchars($value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>