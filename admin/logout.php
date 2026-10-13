<?php
/**
 * THREAD&CO - Administrator Logout Handler (admin/logout.php)
 * 
 * Safely terminates the current administrator session:
 * - Clears all admin authentication credentials from session
 * - Regenerates the session identifier to prevent session fixation/hijacking
 * - Preserves customer cart if testing in the same browser
 * - Dispatches a confirmation flash message
 * - Redirects safely back to the admin login page
 */

// Initialize core helper utilities
require_once __DIR__ . '/../includes/functions.php';

// Unset administrative authentication session keys
unset(
    $_SESSION['user_id'],
    $_SESSION['user_name'],
    $_SESSION['user_email'],
    $_SESSION['user_role']
);

// Regenerate session ID to issue a clean session token
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

// Set feedback alert for the login screen
setFlashMessage('info', 'You have been safely logged out of the admin portal.');

// Redirect back to login screen
header('Location: login.php');
exit;
