<?php
// Validates the username
function validateUsername($username, $context = 'signup') {
    $username = trim($username); // Remove whitespace from beginning and end

    // Check if the username is empty
    if (empty($username)) {
        // Provide a specific message based on the context
        return $context === 'signup' ? "Username is required to create an account." : "Please enter your full name.";
    }

    // Ensure only letters, numbers and spaces are allowed
    if (!preg_match("/^[a-zA-Z0-9\s]+$/", $username)) {
        return "Username must contain only letters, numbers and spaces.";
    }

    // Enforce character length limits
    if (strlen($username) < 3 || strlen($username) > 50) {
        return "Username must be between 3 and 50 characters.";
    }

    return ''; // Return empty string if valid
}

// Validates the email address
function validateEmail($email) {
    // Check if email is empty
    if (empty($email)) {
        return "Email is required.";
    }

    // Strict email validation regex
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        return "Please enter a valid email address (e.g., user@example.com).";
    }

    // Check if there's anything after .com (or other TLDs)
    if (preg_match('/\.[a-zA-Z]{2,}\./', $email) || 
        preg_match('/\.[a-zA-Z]{2,}[^a-zA-Z]/', $email)) {
        return "Please enter a valid email address (e.g., user@example.com).";
    }

    return false; // No error
}

// Validates the password
function validatePassword($password, $context = 'signup') {
    $password = trim($password); // Trim whitespace

    // Check if password is provided
    if (empty($password)) {
        return $context === 'signup' ? "Create a password to secure your account." : "Please enter your password.";
    }

    // For signup: enforce minimum password length
    if ($context === 'signup' && strlen($password) < 6) {
        return "Password must be at least 6 characters long.";
    }

    return ''; // Valid password
}
?>
