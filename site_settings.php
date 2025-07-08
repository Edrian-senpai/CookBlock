<?php
require 'connect.php';
session_start();
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'superadmin')) {
    header('Location: login.php');
    exit;
}

// Example: Handle updating a site setting (site_name)
$settingsMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $siteName = trim($_POST['site_name'] ?? '');
    if ($siteName !== '') {
        // Example: Save to a settings table (create if not exists)
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (name VARCHAR(64) PRIMARY KEY, value TEXT)");
        $stmt = $pdo->prepare("REPLACE INTO site_settings (name, value) VALUES (?, ?)");
        $stmt->execute(['site_name', $siteName]);
        $settingsMsg = '<div class="alert alert-success mb-2">Site name updated!</div>';
    } else {
        $settingsMsg = '<div class="alert alert-danger mb-2">Site name cannot be empty.</div>';
    }
}

// Fetch current settings
$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (name VARCHAR(64) PRIMARY KEY, value TEXT)");
$stmt = $pdo->prepare("SELECT value FROM site_settings WHERE name = ?");
$stmt->execute(['site_name']);
$currentSiteName = $stmt->fetchColumn() ?: 'CookBlock';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Site Settings - CookBlock Admin</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-orange fw-bold">
            <i class="bi bi-gear me-2"></i>Site Settings
        </h2>
        <a href="view_all.php" class="btn btn-outline-orange">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="card shadow-sm border-orange">
        <div class="card-body">
            <p class="lead">Change site-wide settings below.</p>
            <?= $settingsMsg ?>
            <form method="post" class="row g-3 mb-4">
                <div class="col-md-8">
                    <label for="site_name" class="form-label fw-semibold">Site Name</label>
                    <input type="text" name="site_name" id="site_name" class="form-control" value="<?= htmlspecialchars($currentSiteName) ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" name="update_settings" class="btn btn-orange w-100">
                        <i class="bi bi-save"></i> Save Settings
                    </button>
                </div>
            </form>
            <hr>
            <p class="mb-0 text-muted">
                <i class="bi bi-info-circle me-1"></i>
                More settings can be added here as needed (e.g., maintenance mode, contact email, etc.).
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.js"></script>
</body>
</html>