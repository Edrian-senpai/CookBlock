<?php
session_start();           // Start the session to access session variables
session_destroy();         // Destroy all session data (log out the user)
header("Location: login.php"); // Redirect the user to the login page
exit;                     // Ensure no further code is executed
?>
