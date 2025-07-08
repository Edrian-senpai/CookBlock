<?php
session_start();
require_once 'connect.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

$alert = '';
$success = false;

// --- Resend cooldown logic ---
$resendCooldown = 30; // seconds
$canResend = true;
$remaining = 0;

if (!isset($_SESSION['last_resend_time'])) {
    $_SESSION['last_resend_time'] = [];
}

if (isset($_SESSION['last_resend_time'][$email])) {
    $elapsed = time() - $_SESSION['last_resend_time'][$email];
    if ($elapsed < $resendCooldown) {
        $canResend = false;
        $remaining = $resendCooldown - $elapsed;
    }
}

if ($email && $token) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verification_token = ?");
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);
        $alert = "<div class='alert alert-success text-center'>Your email has been verified!<br>This tab will close automatically.</div>";
        $success = true;
    } else {
        $alert = "<div class='alert alert-danger text-center'>Invalid or expired verification link.</div>";
    }
} elseif ($email) {
    // Check if user exists and get their verification status
    $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userVerified = $stmt->fetchColumn();

    if ($userVerified === false) {
        $alert = "<div class='alert alert-danger text-center mb-3'>
            <strong>No account found for this email address.</strong>
        </div>";
    } elseif ($userVerified == 1) {
        $alert = "<div class='alert alert-success text-center mb-3'>
            <strong>Your email is already verified.</strong><br>
            You may now <a href='login.php' class='text-orange fw-semibold'>log in</a>.
        </div>";
    } else {
        $alert = "<div class='alert alert-warning text-center mb-3'>
            <strong>Your account is not verified.</strong><br>
            Please check your email (<b>" . htmlspecialchars($email) . "</b>) for the verification link.<br>
        </div>";
    }

    // Only allow resend if not verified and user exists
    if ($userVerified == 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_email']) && $canResend) {
        $email = $_POST['resend_email'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && !$user['is_verified']) {
            $token = bin2hex(random_bytes(32));
            $update = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
            $update->execute([$token, $user['id']]);

            $verifyLink = "http://localhost/HCI-L_GROUP2_OE6/verify.php?email=" . urlencode($email) . "&token=" . $token;

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
                $mail->addAddress($email, $user['username']);

                $mail->isHTML(true);
                $mail->Subject = "CookBlock Email Verification";
                $mail->Body    = "
                    <h3 style='color:#ff6f00;'>🍲 CookBlock Email Verification</h3>
                    <p>Hi <b>" . htmlspecialchars($user['username']) . "</b>,</p>
                    <p>Thank you for signing up! Please verify your email address by clicking the button again:</p>
                    <p style='margin:20px 0;'>
                        <a href='$verifyLink' style='background:#ff6f00;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;font-weight:bold;'>Verify Email</a>
                    </p>
                    <p>If the button above doesn't work, copy and paste this link into your browser:</p>
                    <p><a href='$verifyLink'>$verifyLink</a></p>
                    <hr>
                    <small style='color:#888;'>If you did not create an account, you can ignore this email.</small>
                ";

                $mail->send();
                $alert .= '
                <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055">
                    <div id="resentToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                Verification email sent. Please check your inbox.
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>';
            } catch (Exception $e) {
                $alert .= "<div class='alert alert-danger text-center mt-3'>Verification email could not be sent. Mailer Error: {$mail->ErrorInfo}</div>";
            }
        }

        $_SESSION['last_resend_time'][$email] = time();
        $canResend = false;
        $remaining = $resendCooldown;
    }
} else {
    $alert = "<div class='alert alert-danger text-center'>Invalid request.</div>";
}

$userVerified = false;
if ($email) {
    $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userVerified = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification - CookBlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .fade-out { transition: opacity 0.5s; }
        .opacity-0 { opacity: 0 !important; }
    </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg p-4" style="max-width: 430px; width: 100%;">
        <div class="text-center mb-4">
            <h3 class="text-orange fw-bold">Email Verification</h3>
            <p class="text-muted mb-0">Verify your CookBlock account</p>
        </div>

        <?= $alert ?>

        <?php if (!$success && $email && $userVerified == 0): ?>
            <form method="post" class="mt-3" id="resendForm">
                <input type="hidden" name="resend_email" value="<?= htmlspecialchars($email) ?>">
                <button type="submit" class="btn btn-orange w-100" id="resendBtn" <?= !$canResend ? 'disabled' : '' ?>>
                    <?= isset($_SESSION['last_resend_time'][$email]) ? 'Send Verification Email' : 'Send Verification Email' ?>
                </button>
            </form>
            <div class="text-center text-muted mt-2" id="cooldownMsg" style="<?= $canResend ? 'display:none;' : '' ?>">
                You can resend in <span id="cooldown"><?= $remaining ?></span> seconds.
            </div>
            <div class="text-center text-muted mt-2" id="resendMsg" style="<?= !$canResend ? 'display:none;' : '' ?>">
                Didn't receive the email? You can resend it again.
            </div>
            <div class="text-center mt-3">
                <a href="login.php" class="text-orange text-decoration-none fw-semibold">Back to Login</a>
            </div>
        <?php endif; ?>
        
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var resendForm = document.getElementById('resendForm');
    var resendBtn = document.getElementById('resendBtn');
    var cooldownMsg = document.getElementById('cooldownMsg');
    var resendMsg = document.getElementById('resendMsg');
    var cooldownSpan = document.getElementById('cooldown');
    var resentToast = document.getElementById('resentToast');
    let cooldown = <?= $remaining ?>;
    let timer = null;

    function showResendBtn() {
        if (resendBtn) resendBtn.disabled = false;
        if (cooldownMsg) cooldownMsg.style.display = 'none';
        if (resendMsg) resendMsg.style.display = '';
    }

    function startCountdown(seconds) {
        cooldown = seconds;
        if (resendBtn) resendBtn.disabled = true;
        if (cooldownMsg) cooldownMsg.style.display = '';
        if (resendMsg) resendMsg.style.display = 'none';
        if (cooldownSpan) cooldownSpan.textContent = cooldown;
        if (timer) clearInterval(timer);
        timer = setInterval(function () {
            cooldown--;
            if (cooldownSpan) cooldownSpan.textContent = cooldown;
            if (cooldown <= 0) {
                resendBtn.textContent = 'Resend Verification Email';
                clearInterval(timer);
                showResendBtn();
            }
        }, 1000);
    }

    // Start countdown if needed on page load
    if (cooldown > 0) {
        startCountdown(cooldown);
    }

    // If the toast is shown, show and auto-hide after 2 seconds
    if (resentToast) {
        const toast = new bootstrap.Toast(resentToast, {
            autohide: true,
            delay: 4000
        });
        toast.show();
    }


    if (resendForm && resendBtn) {
        resendForm.addEventListener('submit', function () {
            setTimeout(function() {
                // Button text update handled by PHP/JS elsewhere
            }, 100);
        });
    }
});
</script>

<?php if ($success): ?>
<script>
    setTimeout(function() {
        // Optionally, notify the opener to refresh or redirect
        if (window.opener && !window.opener.closed) {
            window.opener.location.href = "view_all.php";
        }
        window.close();
    }, 2500); // 2.5 seconds delay for user to read the message
</script>
<?php endif; ?>


</body>
</html>


