<?php
require_once 'vendor/autoload.php';
require_once 'connect.php';
session_start();

$client = new Google_Client();
$client->setClientId('1085945655091-oqcsqvrlv1ugqrgnpqj5gvqqg7j7fk55.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/CookBlock/google-callback-main.php');
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (!isset($token['error'])) {
            $client->setAccessToken($token['access_token']);
            $oauth = new Google_Service_Oauth2($client);
            $profile = $oauth->userinfo->get();

            $email = $profile->email;
            $name = $profile->name; // Full name with space
            $givenName = $profile->givenName; // First name
            $familyName = $profile->familyName; // Last name
            $picture = $profile->picture;
            $googleId = $profile->id;
            
            // Create username with space between given and family name
            $username = trim($givenName . ' ' . $familyName);
            
            // Fallback if the combined name is empty
            if (empty($username)) {
                $username = $name;
            }
            
            // Final fallback if still empty
            if (empty($username)) {
                $username = explode('@', $email)[0]; // Use email prefix
            }

            // Check if user exists in the unified users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR google_id = ?");
            $stmt->execute([$email, $googleId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Update user if they previously signed up with email but now using Google
                if (empty($user['google_id'])) {
                    $updateStmt = $pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'google', picture = ?, username = ? WHERE id = ?");
                    $updateStmt->execute([$googleId, $picture, $username, $user['id']]);
                }
                // Always check verification
                if (isset($user['is_verified']) && !$user['is_verified']) {
                    $_SESSION['error'] = "Please verify your email before logging in. Check your inbox.";
                    header("Location: login.php");
                    exit;
                }
                
                $_SESSION['message'] = "Welcome back, $username!";
            } else {
                // Create new user with username
                $insertStmt = $pdo->prepare("INSERT INTO users (email, username, picture, google_id, auth_provider, is_verified, verification_token) VALUES (?, ?, ?, ?, 'google', 1, NULL)");
                $insertStmt->execute([$email, $username, $picture, $googleId]);

                // No need to send verification email or block login

                // Get the latest user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Set session variables (only if verified)
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'] ?? $username,
                    'email' => $email,
                    'picture' => $picture,
                    'role' => $user['role'] ?? 'user',
                    'auth_provider' => 'google',
                    'is_verified' => $user['is_verified'] ?? 0
                ];

                header("Location: view_all.php");
                exit;
            }

            // Get the latest user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // ADD THIS CHECK:
            if (isset($user['is_verified']) && !$user['is_verified']) {
                $_SESSION['error'] = "Please verify your email before logging in. Check your inbox.";
                header("Location: login.php");
                exit;
            }

            // Check if the auth_provider is not google and the user is not verified
            if (
                ($user['auth_provider'] ?? 'local') !== 'google' &&
                isset($user['is_verified']) && !$user['is_verified']
            ) {
                $_SESSION['error'] = "Please verify your email before logging in. Check your inbox.";
                header("Location: login.php");
                exit;
            }

            // Set session variables (only if verified)
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'] ?? $username,
                'email' => $email,
                'picture' => $picture,
                'role' => $user['role'] ?? 'user',
                'auth_provider' => 'google',
                'is_verified' => $user['is_verified'] ?? 0
            ];

            header("Location: view_all.php");
            exit;
        } else {
            $_SESSION['error'] = "Error authenticating with Google: " . $token['error'];
            header("Location: login.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error during Google authentication: " . $e->getMessage();
        header("Location: login.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Google authentication failed: no authorization code received";
    header("Location: login.php");
    exit;
}
?>