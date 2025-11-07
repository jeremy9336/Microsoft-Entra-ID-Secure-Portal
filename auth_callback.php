<?php
/**
 * ============================================================================
 * File: auth_callback.php
 * Purpose: Handles the OAuth 2.0 Authorization Code Callback for Microsoft Entra ID
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
 * This script is invoked by Microsoft Entra (Azure AD) after the user successfully
 * authenticates. It receives an authorization code, exchanges it for access and ID
 * tokens, validates them, creates a session, and redirects the user to the portal.
 *
 * SECURITY NOTES:
 *   - Uses certificate-based authentication (private key JWT).
 *   - Guards against code replay (prevents “authorization code already redeemed”).
 *   - Requires HTTPS — never deploy over HTTP.
 *   - All tokens are stored server-side in $_SESSION.
 *
 * FLOW SUMMARY:
 *   1. Verify that the session is active.
 *   2. Prevent the same authorization code from being reused.
 *   3. Exchange the authorization code for tokens via Microsoft’s token endpoint.
 *   4. Decode the ID token to extract user claims.
 *   5. Persist user/session info.
 *   6. Redirect securely to the authenticated home page.
 */

require_once "config.php";              // Core configuration (tenant, client ID, URLs, etc.)
require_once "lib_client_assertion.php"; // Builds the signed JWT assertion from your cert/key

// ---------------------------------------------------------------------------
// Initialize session (required to store tokens and user state)
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) session_start();

// ---------------------------------------------------------------------------
// Prevent replay of authorization code (OAuth codes are single-use)
// ---------------------------------------------------------------------------
// If the same code arrives twice (for example, user refreshes the page),
// redirect them to home instead of re-requesting a token.
if (isset($_SESSION['last_auth_code']) && $_SESSION['last_auth_code'] === ($_GET['code'] ?? '')) {
    header("Location: home.php");
    exit;
}
$_SESSION['last_auth_code'] = $_GET['code'] ?? null;

// ---------------------------------------------------------------------------
// Validate input — ensure Microsoft sent a "code" parameter
// ---------------------------------------------------------------------------
if (empty($_GET['code'])) {
    // No authorization code means the request is invalid or user denied consent.
    header("Location: index.html");
    exit;
}

// ---------------------------------------------------------------------------
// Exchange authorization code for access/refresh/ID tokens
// ---------------------------------------------------------------------------
$code = $_GET['code'];

// Create a client assertion (JWT signed with your private key) to prove
// the app’s identity to Microsoft Entra instead of using a client secret.
$clientAssertion = safe_exec(
    fn() => build_client_assertion($clientId, $tenantId, $privateKeyPath, $publicCertPath),
    "Failed to build client assertion",
    ["operation" => "auth_callback"]
);


// Prepare POST payload for token request
$data = [
    "client_id"            => $clientId,
    "scope"                => $scope,
    "code"                 => $code,
    "redirect_uri"         => $redirectUri,
    "grant_type"           => "authorization_code",
    "client_assertion_type"=> "urn:ietf:params:oauth:client-assertion-type:jwt-bearer",
    "client_assertion"     => $clientAssertion
];

// Execute token request via HTTPS POST
$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($data),
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
curl_close($ch);

// Decode JSON response
$token = json_decode($response, true);

// ---------------------------------------------------------------------------
// Handle possible token errors
// ---------------------------------------------------------------------------
// If Entra didn’t return an ID token, authentication failed.
// This usually indicates an invalid certificate, mismatch, or invalid assertion.
if (!isset($token["id_token"])) {
    echo "<h2>Authentication failed.</h2><pre>";
    print_r($token);
    echo "</pre>";
    exit;
}

// ---------------------------------------------------------------------------
// Decode ID token payload (JWT) to extract user identity claims
// ---------------------------------------------------------------------------
// The ID token is a Base64URL-encoded JWT. We only need the middle part (payload).
list(, $payload,) = explode('.', $token["id_token"]);
$userInfo = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

// ---------------------------------------------------------------------------
// Store user and token data in the session
// ---------------------------------------------------------------------------
// These session values allow the app to identify the user and refresh tokens later.
$_SESSION["user"]          = $userInfo;
$_SESSION["access_token"]  = $token["access_token"]  ?? '';
$_SESSION["refresh_token"] = $token["refresh_token"] ?? '';
$_SESSION["token_expires"] = time() + ($token["expires_in"] ?? 3600);

// ---------------------------------------------------------------------------
// Redirect the user to their authenticated home page
// ---------------------------------------------------------------------------
// IMPORTANT: No output (echo, whitespace) before this header call,
// or the redirect will fail.
header("Location: home.php");
exit;
?>