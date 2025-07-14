<?php
session_start();
require_once 'connect.php';
require_once 'validation.php';
require_once 'vendor/autoload.php'; // For Google API
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = false;

// Initialize Google Client
$googleClient = new Google_Client();
$googleClient->setClientId('1085945655091-p4vda4jljae5rtu4vsrpgn0m24ksi2js.apps.googleusercontent.com');
$googleClient->setClientSecret('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');
$googleClient->setRedirectUri('http://localhost/HCI-L_GROUP2_OE6/google-callback-main.php');
$googleClient->addScope('email');
$googleClient->addScope('profile');

$googleAuthUrl = $googleClient->createAuthUrl();

// Process regular signup form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Run input validations
    $usernameError = validateUsername($username);
    $emailError = validateEmail($email);
    $passwordError = validatePassword($password);

    if ($usernameError) $errors[] = $usernameError;
    if ($emailError) $errors[] = $emailError;
    if ($passwordError) $errors[] = $passwordError;

    $role = 'user'; // Default role
    if (isset($_POST['role']) && $_SESSION['user']['role'] === 'admin') {
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
    }

    if (empty($errors)) {
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, auth_provider, role, verification_token) VALUES (?, ?, ?, 'local', ?, ?)");
        try {
            $stmt->execute([$username, $email, $password, $role, $token]);
            $success = true;

            // Send verification email
            $verifyLink = "http://localhost/HCI-L_GROUP2_OE7/verify.php?email=" . urlencode($email) . "&token=" . $token;
            $subject = "CookBlock Email Verification";
            $message = "Hi $username,\n\nPlease verify your email by clicking the link below:\n$verifyLink\n\nThank you!";
            $headers = "From: no-reply@cookblock.com\r\n";

            // PHPMailer setup
            $mail = new PHPMailer(true);
            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'cookblock.supermoderator@gmail.com';
                $mail->Password   = 'vhnxbujzsefutotg'; // <-- your app password, no spaces
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('cookblock.supermoderator@gmail.com', 'CookBlock Admin');
                $mail->addAddress($email, $username);

                // Content
                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body    = $message;

                $mail->send();
            } catch (Exception $e) {
                $errors[] = "Verification email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }

        } catch (PDOException $e) {
            $errors[] = "Username or email already exists.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - CookBlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg p-4" style="max-width: 450px; width: 100%; background-color:rgb(255, 236, 223);">
        <div class="text-center mb-4">
            <h3 class="text-orange fw-bold">Create Account</h3>
            <p class="text-muted mb-0">Join CookBlock today</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        

        <!-- Success Toast -->
        <div aria-live="polite" aria-atomic="true" class="position-relative">
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
                <div id="signupSuccessToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            Account created successfully! You can now <a href="login.php" class="alert-link text-white text-decoration-underline">log in</a>.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regular Signup form -->
        <form method="POST" action="signup.php">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input 
                    type="text" 
                    class="form-control" 
                    name="username" 
                    pattern="^[A-Za-z0-9\s]{3,50}$" 
                    title="Username must be 3-50 characters only, without special characters or emojis."
                    maxlength="50"
                    required 
                />
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input 
                    type="email" 
                    class="form-control" 
                    name="email" 
                    maxlength="100"
                    required 
                />
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input 
                    type="password" 
                    class="form-control" 
                    name="password" 
                    pattern="^[A-Za-z0-9@#%&!_\-\.]{8,100}$" 
                    title="Password must be 8-100 characters long. Letters, numbers, and basic symbols only. No emojis."
                    minlength="8" 
                    maxlength="100"
                    required 
                />
            </div>
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-orange w-100 mb-3">Sign Up</button>
        </form>
                    <!-- Google Login Button -->
                    <div class="text-center mt-3">
                        <p class="mb-2">Or</p>
                        <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn btn-light border d-flex align-items-center justify-content-center gap-2 w-100" style="height: 45px;">
                            <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo" style="height: 20px; width: 20px;">
                            <span class="fw-semibold text-dark">Sign in with Google</span>
                        </a>
                    </div>
        <p class="mt-3 text-center">
            Already have an account?
            <a href="login.php" class="text-orange text-decoration-none fw-semibold">Log in</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        <?php if ($success): ?>
            var toastEl = document.getElementById('signupSuccessToast');
            var toast = new bootstrap.Toast(toastEl);
            toast.show();
        <?php endif; ?>
    });
</script>
</body>
</html>