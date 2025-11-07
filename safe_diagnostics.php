<?php
/**
 * ============================================================================
 * Project: XNALab Entra Secure Portal
 * File: safe_diagnostics.php
 * Description: Unified diagnostics, logging, and safe execution handler
 * Author: Jeremy Rousseau, jeremy9336@gmail.com | Entra Secure Login Project
 * Date Created: 2025-11-06
 * Version control: [MAJOR.MINOR.PATCH]
 * Last Modified: 2025-11-05 
 * ============================================================================
 * CHANGE LOG:
 * [YYYY-MM-DD] [vX.X.X] [Initials] - [Description of change]
 * 2025-11-05 1.0.0 jwr - Intial release
 * ============================================================================
 * SECURITY NOTES:
 *   - Never exposes certs, tokens, or secrets in output.
 *   - Full internal logs stored in /logs/debug.log.
 *   - Debug visibility controlled via constants or flag file.
 * ============================================================================
 */

if (!defined('DEBUG_ENABLED'))       define('DEBUG_ENABLED', false);
if (!defined('DEBUG_IP_WHITELIST'))  define('DEBUG_IP_WHITELIST', ['127.0.0.1']);
if (!defined('DEBUG_LOG_PATH'))      define('DEBUG_LOG_PATH', __DIR__ . '/logs/debug.log');

/* ---------------------------------------------------------------------------
   1️⃣ Core Logger
--------------------------------------------------------------------------- */
function secure_log(string $message, array $context = []): void
{
    $timestamp = date("Y-m-d H:i:s");
    $dir = dirname(DEBUG_LOG_PATH);
    if (!file_exists($dir)) mkdir($dir, 0755, true);

    // Filter out sensitive keys
    $filtered = array_filter($context, function ($k) {
        return !preg_match('/(secret|key|cert|token|password)/i', $k);
    }, ARRAY_FILTER_USE_KEY);

    $entry = "[$timestamp] $message";
    if ($filtered) $entry .= " | " . json_encode($filtered, JSON_UNESCAPED_SLASHES);
    file_put_contents(DEBUG_LOG_PATH, $entry . PHP_EOL, FILE_APPEND);
}

/* ---------------------------------------------------------------------------
   2️⃣ Conditional On-Screen Debug Display
--------------------------------------------------------------------------- */
function debug_show(string $message, array $context = []): void
{
    $clientIp   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $authorized = DEBUG_ENABLED || in_array($clientIp, DEBUG_IP_WHITELIST, true);

    if ($authorized) {
        echo "<pre style='background:#111;color:#0f0;padding:10px;border-radius:6px;'>";
        echo "<strong>🔍 DEBUG:</strong> " . htmlspecialchars($message) . "\n";
        if ($context) print_r($context);
        echo "</pre>";
    }
    secure_log($message, $context);
}

/* ---------------------------------------------------------------------------
   3️⃣ Safe Execution Wrapper
--------------------------------------------------------------------------- */
function safe_exec(callable $callback, string $errorMessage = "Internal error", array $context = [])
{
    try {
        return $callback();
    } catch (Throwable $e) {
        $user     = $_SESSION["user"]["preferred_username"] ?? "unknown";
        $clientIp = $_SERVER["REMOTE_ADDR"] ?? "unknown";

        secure_log("SAFE_EXEC FAILURE: $errorMessage", [
            "user"    => $user,
            "ip"      => $clientIp,
            "type"    => get_class($e),
            "message" => $e->getMessage(),
            "file"    => basename($e->getFile()),
            "line"    => $e->getLine()
        ] + $context);

        $authorized = DEBUG_ENABLED || in_array($clientIp, DEBUG_IP_WHITELIST, true);
        if ($authorized) {
            echo "<pre style='color:#0f0;background:#111;padding:10px;border-radius:6px;'>";
            echo "🔍 SAFE EXEC DEBUG: " . htmlspecialchars($errorMessage) . "\n";
            echo htmlspecialchars($e->getMessage()) . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")";
            echo "</pre>";
        }

    // --- Redirect user to friendly error page ---
// --- Redirect user to friendly error page ---
if (!headers_sent()) {
    // Always send a proper 302 redirect
    header("Location: /entra_cert/error.html", true, 302);
    exit;
} else {
    // Fallback: show inline error if headers already sent
    echo '<script>window.location.href="/entra_cert/error.html";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=/entra_cert/error.html"></noscript>';
    exit;
}



    
    }
}

/* ---------------------------------------------------------------------------
   4️⃣ Global Error & Exception Handlers
--------------------------------------------------------------------------- */
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    secure_log("PHP ERROR", compact('errno', 'errstr', 'errfile', 'errline'));
    if (DEBUG_ENABLED) {
        echo "<pre style='color:#f55;background:#222;padding:10px;'>
              ⚠️ PHP ERROR [$errno]: $errstr in " . basename($errfile) . " on line $errline
              </pre>";
    }
});

set_exception_handler(function ($e) {
    secure_log("UNCAUGHT EXCEPTION", [
        'type'    => get_class($e),
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
        'trace'   => $e->getTraceAsString(),
    ]);
    if (DEBUG_ENABLED) {
        echo "<pre style='color:#f55;background:#222;padding:10px;'>
              💥 EXCEPTION: " . htmlspecialchars($e->getMessage()) . "</pre>";
    }
});
?>