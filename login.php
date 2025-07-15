<?php
session_start();
require_once 'connect.php';
require_once 'validation.php';
require_once 'vendor/autoload.php'; // For Google API

$error = '';

// Initialize Google Client
$googleClient = new Google_Client();
$googleClient->setClientId('1085945655091-oqcsqvrlv1ugqrgnpqj5gvqqg7j7fk55.apps.googleusercontent.com');
$googleClient->setClientSecret('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');
$googleClient->setRedirectUri('http://localhost/CookBlock/google-callback-main.php');
$googleClient->addScope('email');
$googleClient->addScope('profile');

$googleAuthUrl = $googleClient->createAuthUrl();

// Process regular login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve submitted form values
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate email and password
    $emailError = validateEmail($email, 'login');
    $passwordError = validatePassword($password, 'login');

    // If any validation fails, show the corresponding error
    if ($emailError || $passwordError) {
        $error = $emailError ?: $passwordError;
    } else {
        // Check if user exists in the database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Validate credentials (in this case, plain-text comparison — not secure in production)
        if ($user && $user['password'] === $password) {
            if (isset($user['is_verified']) && !$user['is_verified']) {
                // Redirect to verify.php with email parameter
                header("Location: verify.php?email=" . urlencode($user['email']));
                exit;
            } else {
                // Store user info in session, including is_verified
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'] ?? 'user',
                    'is_verified' => $user['is_verified'] ?? 0,
                    'auth_provider' => $user['auth_provider'] ?? 'local'
                ];
                header("Location: view_all.php");
                exit;
            }
        } else {
            // Show error if credentials don't match
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CookBlock Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
</head>
<body class="d-flex justify-content-center align-items-center vh-100">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg p-4" style="max-width: 400px; width: 100%; background-color:rgb(255, 236, 223);">
        <div class="text-center mb-4">
            <h3 class="text-orange fw-bold">CookBlock</h3>
            <p class="text-muted mb-0">Login to your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email" 
                    maxlength="100"
                    pattern="^[^\s@]+@[^\s@]+\.[^\s@]{2,}$"
                    title="Please enter a valid email address without emojis or spaces."
                    required
                >
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input 
                        type="password" 
                        class="form-control" 
                        id="loginPassword" 
                        name="password" 
                        pattern="^[A-Za-z0-9@#%&!_\-\.]{8,100}$"
                        title="Password must be 8-100 characters long. Letters, numbers, and basic symbols only. No emojis."
                        minlength="8" 
                        maxlength="100"
                        required
                    >
                    <button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword" tabindex="-1">
                        <span id="loginPasswordIcon" class="bi bi-eye"></span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-orange w-100">Login</button>
            <div class="mb-2 text-center">
                <a href="forgot_password.php" class="text-orange text-decoration-none fw-semibold">Forgot Password?</a>
            </div>
                    <!-- Google Login Button -->
                    <div class="text-center mt-3">
                        <p class="mb-2">Or</p>
                        <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn btn-light border d-flex align-items-center justify-content-center gap-2 w-100" style="height: 45px;">
                            <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo" style="height: 20px; width: 20px;">
                            <span class="fw-semibold text-dark">Log in with Google</span>
                        </a>
                    </div>
            <p class="mt-3 text-center">
                Don't have an account?
                <a href="signup.php" class="text-orange text-decoration-none fw-semibold">Sign up</a>
            </p>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Show/hide password toggle for login
        var passwordInput = document.getElementById('loginPassword');
        var toggleBtn = document.getElementById('toggleLoginPassword');
        var icon = document.getElementById('loginPasswordIcon');
        if (passwordInput && toggleBtn && icon) {
            toggleBtn.addEventListener('click', function () {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        }
    });
</script>
</body>
</html>
