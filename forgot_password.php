<?php
session_start();
require_once 'connect.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_email'])) {
    $email = trim($_POST['reset_email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Always fetch user by email
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            // Always generate a new token and expiry
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $updateStmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
            $updateStmt->execute([$token, $expires, $user['id']]);

            // Build reset link
            $resetLink = "http://localhost/CookBlock/reset_password.php?email=" . urlencode($email) . "&token=" . $token;

            // Send email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'cookblock.supermoderator@gmail.com';
                $mail->Password   = 'vhnxbujzsefutotg';
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->setFrom('cookblock.supermoderator@gmail.com', 'CookBlock Admin');
                $mail->addAddress($email, $user['username'] ?? $user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'CookBlock Password Reset';
                $mail->Body    = "<h3 style='color:#ff6f00;'>🍲 CookBlock Password Reset</h3>"
                    . "<p>Hi <b>" . htmlspecialchars($user['username'] ?? $user['email']) . "</b>,</p>"
                    . "<p>Click the button below to reset your password. This link will expire in 1 hour.</p>"
                    . "<p style='margin:20px 0;'><a href='$resetLink' style='background:#ff6f00;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;font-weight:bold;'>Reset Password</a></p>"
                    . "<p>If the button above doesn't work, copy and paste this link into your browser:</p>"
                    . "<p><a href='$resetLink'>$resetLink</a></p>"
                    . "<hr><small style='color:#888;'>If you did not request a password reset, you can ignore this email.</small>";
                $mail->send();
                $success = true;
            } catch (Exception $e) {
                $error = 'Password reset email could not be sent. Mailer Error: ' . $mail->ErrorInfo;
            }
        } else {
            $error = 'No account found with that email address.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - CookBlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg p-4" style="max-width: 400px; width: 100%; background-color:rgb(255, 236, 223);">
        <div class="text-center mb-4">
            <h3 class="text-orange fw-bold">Forgot Password</h3>
            <p class="text-muted mb-0">Enter your email to reset your password</p>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success text-center">A password reset link has been sent to your email.</div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="forgot_password.php">
            <div class="mb-3">
                <label for="reset_email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="reset_email" name="reset_email" maxlength="100" required>
            </div>
            <button type="submit" class="btn btn-orange w-100">Send Reset Link</button>
        </form>
        <p class="mt-3 text-center">
            <a href="login.php" class="text-orange text-decoration-none fw-semibold">Back to Login</a>
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
