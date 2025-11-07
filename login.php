<?php
/**
 * ============================================================================
 * File: login.php
 * Purpose: Initiate the Microsoft Entra ID OAuth 2.0 Authorization Request
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
 *   This script begins the sign-in flow by redirecting the user’s browser to
 *   Microsoft’s authorization endpoint. It builds a URL containing all required
 *   OAuth 2.0 query parameters.
 *
 *   After the user authenticates with Microsoft Entra ID, Microsoft will redirect
 *   the browser back to the Redirect URI defined in config.php
 *   (→ `auth_callback.php`) with an authorization code.
 *
 * SECURITY NOTES:
 *   - The `state` parameter mitigates CSRF attacks by binding requests to sessions.
 *   - No credentials are handled here — login occurs on Microsoft’s servers.
 *   - Always served over HTTPS.
 */

require_once "config.php"; // Load tenant, client, redirect, and scope configuration

// ---------------------------------------------------------------------------
// Build Authorization Request Parameters
// ---------------------------------------------------------------------------
// Each parameter is defined by the OAuth 2.0 spec and Microsoft Entra’s implementation.
// When combined, these direct the user’s browser to Microsoft’s secure login screen.
$params = [
    "client_id"     => $clientId,         // Application (Client) ID registered in Azure
    "response_type" => "code",            // Request an authorization code
    "redirect_uri"  => $redirectUri,      // Where Microsoft sends the code after login
    "response_mode" => "query",           // Code will appear in query string (vs. POST)
    "scope"         => $scope,            // Permissions requested (e.g., openid, profile, email)
    "state"         => bin2hex(random_bytes(8)) // Random token to prevent CSRF attacks
];

// Optionally store the state in session for later validation
$_SESSION["oauth_state"] = $params["state"];

// ---------------------------------------------------------------------------
// Redirect the Browser to Microsoft’s Authorization Endpoint
// ---------------------------------------------------------------------------
// The URL is assembled using http_build_query() to ensure correct encoding.
// Example destination:
//   https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize?client_id=...&scope=...&state=...
header("Location: $authUrl?" . http_build_query($params));
exit; // Terminate script to prevent further output
?>
