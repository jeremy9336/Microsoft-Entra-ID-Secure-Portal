<?php
/**
 * ============================================================================
 * File: logout.php
 * Purpose: Terminate user session and log logout event
 * Author: Jeremy Rousseau, jeremy9336@gmail.com | Entra Secure Login Project
 * Date Created: 2025-11-05
 * Version control: [MAJOR.MINOR.PATCH]
 * Last Modified: 2025-11-05 
 * ============================================================================
 * CHANGE LOG:
 * [YYYY-MM-DD] [vX.X.X] [Initials] - [Description of change]
 * 2025-11-05 1.0.0 jwr - Intial release
 * ============================================================================
 *
 * OVERVIEW:
 *   This script securely ends the user's authenticated session within the
 *   XNALab Entra Portal. It removes all session variables, destroys the session,
 *   and redirects the user back to the login page.
 *
 *   An audit entry is created to record the logout event for security monitoring.
 *
 * SECURITY NOTES:
 *   - The Microsoft Entra session itself (on Microsoft's servers) remains active,
 *     but local session data and tokens are cleared.
 *   - Always ensure `session_destroy()` is called after `session_unset()`.
 *   - No user data should persist beyond this script’s execution.
 */

require_once "config.php"; // Load session handling and audit logging utilities

// ---------------------------------------------------------------------------
// Log the Logout Event
// ---------------------------------------------------------------------------
// Records the username (if available) and timestamp in the local audit log.
// If no username is found (e.g., malformed session), records as 'unknown'.
$user = $_SESSION["user"]["preferred_username"] ?? 'unknown';
audit_log("LOGOUT initiated by $user");

// --- Save logout URL (Microsoft Entra end-session endpoint) ---
$logoutRedirect = "https://xnalab.us/entra_cert/index.html"; // redirect after logout
$entraLogoutUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/logout?post_logout_redirect_uri=" . urlencode($logoutRedirect);

// --- Clear all session data (server-side) ---
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];  // remove all session variables
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_unset();
session_destroy();

// --- Optionally clear PHPSESSID from browser ---
setcookie("PHPSESSID", "", time() - 3600, "/");

// --- Redirect user to Microsoft logout (then back to your portal) ---
header("Location: $entraLogoutUrl");
exit;
?>
