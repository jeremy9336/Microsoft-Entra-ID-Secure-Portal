<?php
/**
 * ============================================================================
 * File: home.php
 * Purpose: Authenticated landing page for XNALab Entra Portal
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
 * This page is shown only after a successful Microsoft Entra ID login.
 * It displays user information retrieved from the ID token and maintains
 * the session through periodic silent token refreshes.
 *
 * SECURITY NOTES:
 *   - Requires an active session set during `auth_callback.php`.
 *   - If no valid session exists, the user is redirected back to login.
 *   - Includes client-side token refresh every 5 minutes to avoid expiry.
 */

require_once "config.php";  // Imports Entra configuration and session timeout policy

// ---------------------------------------------------------------------------
// Verify active authenticated session
// ---------------------------------------------------------------------------
// If the "user" object is missing from the session, assume the user
// is not authenticated or their session expired. Redirect to login page.
if (!isset($_SESSION["user"])) {
    header("Location: index.html");
    exit;
}

// Extract user claims (name, email, etc.) for display
$user = $_SESSION["user"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>XNA Lab Portal</title>
  <link rel="stylesheet" href="style.css">

  <script>
    /**
     * =========================================================================
     * Token Auto-Refresh Script
     * =========================================================================
     * To keep sessions valid without user interruption, the client browser
     * silently calls `refresh_token.php` every 5 minutes.
     *
     * This ensures:
     *   - The access token remains fresh for API requests (if used later).
     *   - The user avoids an unexpected logout after 1 hour token expiry.
     *
     * Refresh results are not displayed (fire-and-forget).
     */
    setInterval(() => { fetch('refresh_token.php'); }, 300000); // 300,000 ms = 5 min
  </script>
</head>
<body>
  <div class="home-container">
    <!--
      Display personalized greeting using data from the ID token.
      htmlspecialchars() ensures that no malicious HTML can be injected
      into the page from token values.
    -->
    <h1>
      Welcome, <?= htmlspecialchars($user["name"]) ?> 👋
    </h1>

    <p>Email: <?= htmlspecialchars($user["preferred_username"]) ?></p>
    <p>Session will expire after 2 hours of inactivity.</p>

    <!--
      Logout button
      Redirects user to logout.php, which destroys the session and logs an
      audit entry before returning to the login page.
    -->
    <a class="logout-button" href="logout.php">Sign out</a>
  </div>
</body>
</html>
