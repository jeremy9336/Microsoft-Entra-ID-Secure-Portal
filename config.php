<?php
/**
 * ============================================================================
 * File: config.php
 * Purpose: Central configuration and session management for XNALab Entra Portal
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
 * This configuration file defines all constants and environment values used
 * by the authentication scripts (`auth_callback.php`, `home.php`, etc.).
 *
 * FUNCTIONS PROVIDED:
 *   - Initializes session handling.
 *   - Applies a 2-hour inactivity timeout for active user sessions.
 *   - Defines core Microsoft Entra ID endpoints and credentials.
 *   - Provides a reusable `audit_log()` function for security logging.
 *
 * SECURITY NOTES:
 *   - This file contains sensitive identifiers (Client ID, Tenant ID).
 *   - Never commit this file to a public repository.
 *   - The private key is stored locally in PEM format and referenced by path.
 *   - Always deploy over HTTPS (never HTTP).
 */

// Require safe debugging on every PHP file that includes config.php
define('DEBUG_ENABLED', true);
require_once __DIR__ . "/safe_diagnostics.php";

// ---------------------------------------------------------------------------
// Microsoft Entra ID / Azure AD Configuration
// ---------------------------------------------------------------------------
// Tenant and Application registration information.
// These values must match your Azure App Registration setup.
$tenantId     = "YOUR_TENANT_ID"; // Directory (tenant) ID
$clientId     = "YOUR_APP_ID"; // Application (client) ID

// The URL where Entra redirects after successful authentication.
// Must exactly match the redirect URI registered in Azure.
$redirectUri  = "https://xnalab.us/entra_cert/auth_callback.php";

// Entra ID OAuth 2.0 endpoints for authorization and token retrieval.
$authUrl      = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/authorize";
$tokenUrl     = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";

// The access scopes requested — determines what user information is available.
// `offline_access` is required to obtain a refresh token.
$scope        = "openid profile email offline_access";

// ---------------------------------------------------------------------------
// Paths to Local Certificates (Certificate-based authentication)
// ---------------------------------------------------------------------------
// These paths must point to the private and public key files that correspond
// to the certificate uploaded to Azure under App → Certificates & Secrets.
$privateKeyPath = __DIR__ . "/YOUR_PRIVATE_KEY.pem";     // PEM-encoded private key
$publicCertPath = __DIR__ . "/YOUR_PUBLIC_CERT.cer";  // Public certificate (.cer)

// ---------------------------------------------------------------------------
// Session Initialization
// ---------------------------------------------------------------------------
// Ensures PHP has an active session for storing user and token data.
if (session_status() === PHP_SESSION_NONE) session_start();

// ---------------------------------------------------------------------------
// Enforce Security & No-Cache Headers on All Authenticated Pages
// ---------------------------------------------------------------------------
// These headers tell browsers and proxies to avoid caching any page that may
// contain user data. They also add clickjacking and MIME-type protections.
// Extra strict (especially for compliance environments like HIPAA or FedRAMP)
// These reinforce browser security without breaking normal operation.
//
// NOTE: Headers must be sent before any HTML or echo output.
if (isset($_SESSION["user"])) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Referrer-Policy: no-referrer");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
}

// ---------------------------------------------------------------------------
// Session Inactivity Timeout (2 hours)
// ---------------------------------------------------------------------------
// This block automatically logs out a user after 7200 seconds (2 hours) of
// inactivity. It resets session timestamps and redirects to the login page
// with a timeout query parameter (?timeout=1).
if (isset($_SESSION['LAST_ACTIVE']) && (time() - $_SESSION['LAST_ACTIVE'] > 7200)) {
    session_unset();                          // Clear all session variables
    session_destroy();                        // Destroy the session entirely
    header("Location: index.html?timeout=1"); // Redirect to login page
    exit;
}
$_SESSION['LAST_ACTIVE'] = time(); // Update timestamp on every page load

// ---------------------------------------------------------------------------
// Audit Logging Function
// ---------------------------------------------------------------------------
// A simple utility to record login, logout, and refresh events for auditing.
// Each entry is timestamped and appended to `logs/login_audit.log`.
function audit_log($message) {
    $file = __DIR__ . "/logs/login_audit.log";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
}
?>