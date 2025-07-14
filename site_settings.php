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
$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (name VARCHAR(64) PRIMARY KEY, value TEXT)");

// Handle settings update
// Log manage users actions if present
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT, action VARCHAR(255), details TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $adminId = $user['id'] ?? 0;
    // Delete user
    if (isset($_POST['delete_user'], $_POST['user_id'])) {
        $targetId = (int)$_POST['user_id'];
        $logAction = 'User deleted';
        $logDetails = "Deleted user with ID {$targetId}";
        $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $logStmt->execute([$adminId, $logAction, $logDetails]);
    }
    // Promote to admin
    if (isset($_POST['make_admin'], $_POST['user_id'])) {
        $targetId = (int)$_POST['user_id'];
        $logAction = 'User promoted to admin';
        $logDetails = "Promoted user with ID {$targetId} to admin.";
        $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $logStmt->execute([$adminId, $logAction, $logDetails]);
    }
    // Demote to user
    if (isset($_POST['make_user'], $_POST['user_id'])) {
        $targetId = (int)$_POST['user_id'];
        $logAction = 'Admin demoted to user';
        $logDetails = "Demoted admin with ID {$targetId} to user.";
        $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $logStmt->execute([$adminId, $logAction, $logDetails]);
    }
    // Log site settings update (human readable)
    if (isset($_POST['update_settings'])) {
        $siteName = trim($_POST['site_name'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';
        $announcement = trim($_POST['announcement'] ?? '');
        $logoUrl = trim($_POST['logo_url'] ?? '');
        $logAction = 'Site settings updated';
        $readableDetails = "Updated site settings: " .
            ($siteName !== '' ? "Site Name set to '{$siteName}', " : '') .
            "Contact Email set to '{$contactEmail}', " .
            "Maintenance Mode " . ($maintenanceMode === '1' ? "enabled" : "disabled") . ", " .
            "Announcement set to '{$announcement}', " .
            "Logo URL set to '{$logoUrl}'.";
        $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $logStmt->execute([$adminId, $logAction, $readableDetails]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $siteName = trim($_POST['site_name'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';
    $announcement = trim($_POST['announcement'] ?? '');
    $logoUrl = trim($_POST['logo_url'] ?? '');

    $stmt = $pdo->prepare("REPLACE INTO site_settings (name, value) VALUES (?, ?)");
    $pdo->beginTransaction();
    if ($siteName !== '') {
        $stmt->execute(['site_name', $siteName]);
    }
    $stmt->execute(['contact_email', $contactEmail]);
    $stmt->execute(['maintenance_mode', $maintenanceMode]);
    $stmt->execute(['announcement', $announcement]);
    $stmt->execute(['logo_url', $logoUrl]);
    $pdo->commit();
    $settingsMsg = '<div class="alert alert-success mb-2">Settings updated!</div>';
}

// Fetch current settings
function getSetting($name, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM site_settings WHERE name = ?");
    $stmt->execute([$name]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

$currentSiteName = getSetting('site_name', 'CookBlock');
$currentContactEmail = getSetting('contact_email', '');
$currentMaintenance = getSetting('maintenance_mode', '0');
$currentAnnouncement = getSetting('announcement', '');
$currentLogoUrl = getSetting('logo_url', '');

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
                <div class="col-md-6">
                    <label for="site_name" class="form-label fw-semibold">Site Name</label>
                    <input type="text" name="site_name" id="site_name" class="form-control" value="<?= htmlspecialchars($currentSiteName) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="contact_email" class="form-label fw-semibold">Contact Email</label>
                    <input type="email" name="contact_email" id="contact_email" class="form-control" value="<?= htmlspecialchars($currentContactEmail) ?>">
                </div>
                <div class="col-md-6">
                    <label for="logo_url" class="form-label fw-semibold">Site Logo URL</label>
                    <input type="text" name="logo_url" id="logo_url" class="form-control" value="<?= htmlspecialchars($currentLogoUrl) ?>" placeholder="Paste image URL">
                </div>
                <div class="col-md-6">
                    <label for="announcement" class="form-label fw-semibold">Announcement Banner</label>
                    <input type="text" name="announcement" id="announcement" class="form-control" value="<?= htmlspecialchars($currentAnnouncement) ?>" placeholder="Site-wide message">
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?= $currentMaintenance === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="maintenance_mode">
                            Enable Maintenance Mode
                        </label>
                    </div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" name="update_settings" class="btn btn-orange w-100">
                        <i class="bi bi-save"></i> Save Settings
                    </button>
                </div>
            </form>
            <hr>
            <p class="mb-0 text-muted">
                <i class="bi bi-info-circle me-1"></i>
                You can now set maintenance mode, contact email, logo, and announcement banner. These settings can be used site-wide.
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.js"></script>
</body>
</html>