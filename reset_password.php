<?php
session_start();
require_once 'connect.php';
require_once 'validation.php';

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if ($email && $token) {
    // Fetch token and expiry for this email
    $stmt = $pdo->prepare('SELECT id, reset_token, reset_token_expires FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $tokenValid = false;
    if ($user && $user['reset_token'] === $token && $user['reset_token_expires'] && strtotime($user['reset_token_expires']) > time()) {
        $tokenValid = true;
    }
    if ($tokenValid) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'], $_POST['confirm_password'])) {
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            $passwordError = validatePassword($newPassword);
            if ($passwordError) {
                $error = $passwordError;
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                $updateStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                if ($updateStmt->execute([$newPassword, $user['id']])) {
                    // Expire the token after success
                    $expireStmt = $pdo->prepare('UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
                    $expireStmt->execute([$user['id']]);
                    $success = true;
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            }
        }
        // Always show form if token is valid
    } else {
        $error = 'Invalid or expired password reset link.';
    }
}
else {
    $error = 'Invalid password reset request.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - CookBlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg p-4" style="max-width: 400px; width: 100%; background-color:rgb(255, 236, 223);">
        <div class="text-center mb-4">
            <h3 class="text-orange fw-bold">Reset Password</h3>
            <p class="text-muted mb-0">Enter your new password below</p>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success text-center">Your password has been reset! This tab will close shortly.</div>
            <script>
            setTimeout(function() {
                window.close();
            }, 3000); // Close after 3 seconds
            </script>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!$success && $user && $error !== 'Invalid or expired password reset link.'): ?>
        <form method="POST" action="reset_password.php?email=<?= urlencode($email) ?>&token=<?= urlencode($token) ?>">
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" pattern="^[A-Za-z0-9@#%&!_\-.]{8,100}$" minlength="8" maxlength="100" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" maxlength="100" required>
            </div>
            <button type="submit" class="btn btn-orange w-100">Reset Password</button>
        </form>
        <?php endif; ?>
        <p class="mt-3 text-center">
            <a href="login.php" class="text-orange text-decoration-none fw-semibold">Back to Login</a>
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
