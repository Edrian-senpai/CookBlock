<?php
require 'connect.php';
session_start();
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'superadmin')) {
    header('Location: login.php');
    exit;
}

// Fetch recent user logs (example: from a 'user_logs' table)
$logs = [];
if ($pdo->query("SHOW TABLES LIKE 'user_logs'")->fetch()) {
    $stmt = $pdo->query("SELECT l.*, u.username FROM user_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.timestamp DESC LIMIT 50");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Logs - CookBlock Admin</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-orange fw-bold">
            <i class="bi bi-clock-history me-2"></i>User Logs
        </h2>
        <a href="view_all.php" class="btn btn-outline-orange">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="card shadow-sm border-orange">
        <div class="card-body">
            <p class="lead">This page displays recent user activity logs.</p>
            <?php if ($logs && count($logs)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['timestamp']) ?></td>
                                <td><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No user logs found. (Table <code>user_logs</code> is empty or missing.)
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>