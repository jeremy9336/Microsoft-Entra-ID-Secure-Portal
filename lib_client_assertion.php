<?php
 /**
 * ============================================================================
 * File: lib_client_assertion.php
 * Purpose: Generate a signed JWT "client assertion" for certificate-based OAuth 2.0 authentication
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
 *   Microsoft Entra ID (Azure AD) supports certificate-based client authentication.
 *   Instead of sending a client secret, this script builds a short-lived JWT
 *   that proves the app’s identity using its private key.
 *
 * PROCESS SUMMARY:
 *   1. Read and parse the public certificate (.cer) file.
 *   2. Compute SHA-1 and SHA-256 thumbprints for identification.
 *   3. Build a JWT header and payload following RFC 7523.
 *   4. Sign the header+payload with the app’s private key (RS256).
 *   5. Return the completed JWT for inclusion in token requests.
 *
 * SECURITY NOTES:
 *   - JWTs are valid for 10 minutes only.
 *   - Private key should have read-only permissions (chmod 600).
 *   - Both files must match the certificate uploaded in Azure Portal.
 */

 /**
 * Build a signed JWT client assertion for Microsoft Entra ID.
 *
 * @param string $clientId        Application (client) ID registered in Azure AD.
 * @param string $tenantId        Tenant (directory) ID of your Entra ID instance.
 * @param string $privateKeyPath  Path to PEM-encoded private key (used to sign the JWT).
 * @param string $publicCertPath  Path to public certificate (.cer) uploaded to Azure.
 * @return string                 Fully-formed JWT assertion (header.payload.signature)
 * @throws Exception              If certificate or key files cannot be read.
 */
 
function build_client_assertion($clientId, $tenantId, $privateKeyPath, $publicCertPath)
{
    // -----------------------------------------------------------------------
    // Load and process the public certificate
    // -----------------------------------------------------------------------
    if (!file_exists($publicCertPath)) {
        throw new Exception("Public certificate file not found: $publicCertPath");
    }

    $certData = file_get_contents($publicCertPath);

    /**
     * Certificates may be stored in:
     *   - PEM format (Base64 with BEGIN/END CERTIFICATE headers)
     *   - DER format (binary)
     *
     * Azure accepts either, but we normalize to DER for hashing.
     */
    if (strpos($certData, '-----BEGIN CERTIFICATE-----') !== false) {
        // Remove PEM headers and decode base64 content
        $certData = preg_replace('/-----.*CERTIFICATE-----/', '', $certData);
        $certData = str_replace(["\r", "\n"], '', trim($certData));
        $certDer  = base64_decode($certData);
    } else {
        $certDer = $certData; // already binary DER
    }

    // -----------------------------------------------------------------------
    // Compute certificate thumbprints for JWT header
    // -----------------------------------------------------------------------
    // SHA-1 thumbprint (legacy "x5t") — still required by Entra ID
    $sha1   = sha1($certDer, true);
    $x5t    = rtrim(strtr(base64_encode($sha1), '+/', '-_'), '=');

    // SHA-256 thumbprint ("x5t#S256") — modern alternative
    $sha256 = rtrim(strtr(base64_encode(hash('sha256', $certDer, true)), '+/', '-_'), '=');

    // -----------------------------------------------------------------------
    // Build JWT header and payload (claims)
    // -----------------------------------------------------------------------
    $header = [
        'alg'      => 'RS256',   // Algorithm: RSA + SHA-256
        'typ'      => 'JWT',     // Token type
        'x5t'      => $x5t,      // Certificate SHA-1 thumbprint
        'x5t#S256' => $sha256    // Certificate SHA-256 thumbprint
    ];

    $now = time();
    $payload = [
        'aud' => "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token", // Audience = token endpoint
        'iss' => $clientId,   // Issuer = this application
        'sub' => $clientId,   // Subject = this application
        'jti' => bin2hex(random_bytes(8)), // Unique ID to prevent replay
        'nbf' => $now,        // Not valid before (now)
        'exp' => $now + 600   // Expires in 10 minutes
    ];

    // Encode header and payload to Base64URL per RFC 7519
    $base64UrlHeader  = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $unsigned         = "$base64UrlHeader.$base64UrlPayload";

    // -----------------------------------------------------------------------
    // Load private key and sign the JWT
    // -----------------------------------------------------------------------
    $privateKey = openssl_pkey_get_private("file://$privateKeyPath");
    if (!$privateKey) {
        throw new Exception("Unable to load private key: $privateKeyPath");
    }

    // Generate the signature using RSASSA-PKCS1-v1_5 + SHA-256
    openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    openssl_free_key($privateKey);

    // Base64URL-encode the signature
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    // -----------------------------------------------------------------------
    // Return full JWT (header.payload.signature)
    // -----------------------------------------------------------------------
    return "$unsigned.$base64UrlSignature";
}
?>
