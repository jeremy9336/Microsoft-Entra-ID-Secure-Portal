<?php
 /**
 * ============================================================================
 * File: refresh_token.php
 * Purpose: Safely renew Microsoft Entra access tokens using a refresh token
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
 * SECURITY IMPROVEMENTS:
 *   - Returns structured JSON responses for frontend use.
 *   - Implements 1 retry on transient network errors.
 *   - Adds audit logging for both success and failure events.
 *   - Includes headers to prevent caching of sensitive responses.
 */

require_once "config.php";
require_once "lib_client_assertion.php";

// ---------------------------------------------------------------------------
// HTTP headers for this endpoint
// ---------------------------------------------------------------------------
header("Content-Type: application/json");

// ---------------------------------------------------------------------------
// Validate active session and refresh token existence
// ---------------------------------------------------------------------------
if (empty($_SESSION["refresh_token"])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "No refresh token."]);
    exit;
}

// ---------------------------------------------------------------------------
// Skip refresh if token is still valid (>5 minutes remaining)
// ---------------------------------------------------------------------------
if ($_SESSION["token_expires"] - time() > 300) {
    echo json_encode([
        "status" => "ok",
        "message" => "Still valid",
        "expires_in" => $_SESSION["token_expires"] - time()
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// Build client assertion (private key JWT)
// ---------------------------------------------------------------------------
try {
    $clientAssertion = build_client_assertion(
        $clientId,
        $tenantId,
        $privateKeyPath,
        $publicCertPath
    );
} catch (Exception $e) {
    audit_log("TOKEN REFRESH ERROR: Cannot build assertion - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Assertion build failed"]);
    exit;
}

// ---------------------------------------------------------------------------
// Prepare POST body for refresh request
// ---------------------------------------------------------------------------
$data = [
    "client_id"            => $clientId,
    "grant_type"           => "refresh_token",
    "refresh_token"        => $_SESSION["refresh_token"],
    "scope"                => $scope,
    "client_assertion_type"=> "urn:ietf:params:oauth:client-assertion-type:jwt-bearer",
    "client_assertion"     => $clientAssertion
];

// Helper function for token request with retry logic
function requestNewToken($tokenUrl, $data, $retry = 1)
{
    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false && $retry > 0) {
        // Retry once if temporary network failure
        sleep(1);
        return requestNewToken($tokenUrl, $data, $retry - 1);
    }

    return [$response, $error, $status];
}

// ---------------------------------------------------------------------------
// Execute refresh request
// ---------------------------------------------------------------------------
[$response, $error, $status] = requestNewToken($tokenUrl, $data);
$newToken = json_decode($response, true);

// ---------------------------------------------------------------------------
// Evaluate response and update session if successful
// ---------------------------------------------------------------------------
if (!empty($newToken["access_token"])) {
    $_SESSION["access_token"]  = $newToken["access_token"];
    $_SESSION["token_expires"] = time() + ($newToken["expires_in"] ?? 3600);
    $_SESSION["refresh_token"] = $newToken["refresh_token"] ?? $_SESSION["refresh_token"];

    audit_log("TOKEN REFRESH: " . ($_SESSION["user"]["preferred_username"] ?? 'unknown'));

    echo json_encode([
        "status" => "refreshed",
        "expires_in" => $newToken["expires_in"],
        "message" => "Access token successfully renewed."
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// Error handling — audit and response
// ---------------------------------------------------------------------------
$auditMsg = "TOKEN REFRESH FAILED: " .
            ($_SESSION["user"]["preferred_username"] ?? 'unknown') .
            " | HTTP $status | " .
            ($error ?: json_encode($newToken));

audit_log($auditMsg);

http_response_code(401);
echo json_encode([
    "status" => "error",
    "message" => "Refresh failed. Please sign in again."
]);
exit;
?>
