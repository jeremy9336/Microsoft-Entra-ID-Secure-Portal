# Microsoft-Entra-ID-Secure-Portal
A production-ready PHP web portal integrated with Microsoft Entra ID (OAuth 2.0) using certificate-based authentication.

## Overview
## Entra ID Secure Portal

A secure PHP-based web authentication and session management framework for Microsoft Entra ID (formerly Azure AD).  
Implements modern OAuth 2.0 authorization with certificate-based client assertions, full session lifecycle control, and developer-safe diagnostics.

## Features
 - Microsoft Entra ID Integration | OAuth 2.0 single-tenant login with Entra ID authorization endpoint.
 - Certificate-based Auth | Uses private key & public certificate for client assertion (no shared secrets).
 - Automatic token refresh and 2-hour idle session timeout | Silent renewal of access tokens before expiration.
 - Safe Diagnostics System | safe_diagnostics.php intercepts all errors, logs securely, and redirects users to a branded error page.
 - User-friendly error and logout pages (no white screens), Audit Logging | Tracks login and logout events in logs/login_audit.log.
 - Full Logout Flow | Ends local session, clears cookies, and logs out of Entra ID SSO.
 - Production-hardened against credential leaks and path exposure
 - Optional developer debug toggle (DEBUG_ON file or IP whitelist)
 - Modern UX | Styled login and home portal pages, with optional dark mode toggle.

## Designed For
 - Organizations and developers integrating PHP web applications with Microsoft Entra ID who need enterprise-grade security and reliability without external dependencies.

## File Structure
| File                     | Description                                  |
| ------------------------ | -------------------------------------------- |
| index.html               | Login landing page                           |
| login.php                | Initiates OAuth flow                         |
| auth_callback.php        | Handles Entra callback & token exchange      |
| config.php               | Global configuration & session policy        |
| home.php                 | Authenticated user dashboard                 |
| logout.php               | Session termination + audit                  |
| lib_client_assertion.php | JWT assertion builder (certificate-based)    |
| refresh_token.php        | Secure token auto-refresh logic              |
| safe_diagnostics.php     | Unified diagnostics & safe execution handler |
| error.html               | User-friendly branded error fallback page    |
| style.css                | Unified light/dark theme UI styling          |

## Directory Structure
<img width="512" height="768" alt="Directory_structure" src="https://github.com/user-attachments/assets/7796cbed-a531-4f0d-aba3-55d36cd558e1" />

## Authentication lifecycle:
 login.php → start Entra sign-in
 auth_callback.php → exchange code, start session
 home.php → secure user area
 refresh_token.php → keep tokens valid
 logout.php → safely terminate session
 
<img width="768" height="512" alt="Authentication Flow" src="https://github.com/user-attachments/assets/2d473488-eed6-4c24-afa1-162e1f27a80a" />

====================

## Example Deployment
1. Configure app registration in Entra ID 
2. Upload /entra_cert/ directory to your HTTPS-enabled web server.
3. Verify PHP 8.1+ and OpenSSL extension are active.
4. Update config.php with Tenant ID, Client ID, and certificate paths.
5. Visit /entra_cert/index.html to test login.
6. Sign in → redirected to home.php.
7. Logout → redirected to Microsoft logout → returns to portal login page.

====================

## Microsoft Entra Setup

1. Register a **Single-tenant** app in [Microsoft Entra ID](https://entra.microsoft.com).
2. Add a redirect URI:  
3. Upload your public certificate (`xnalab-public.cer`) in the app registration.
4. Note your:
	- Tenant ID  
	- Application (Client) ID  
	- Redirect URI  
5. Create your private key in PEM format:
# Using PowerShell to create cert and export keys
	New-SelfSignedCertificate -CertStoreLocation "cert:\CurrentUser\My" -Subject "CN=YOURPORTAL"
	Export-Certificate -Cert "cert:\CurrentUser\My\<Thumbprint>" -FilePath "public.cer"
	Export-PfxCertificate -Cert "cert:\CurrentUser\My\<Thumbprint>" -FilePath "private.pfx" -Password (ConvertTo-SecureString -String "Password123" -Force -AsPlainText)
	openssl pkcs12 -in private.pfx -out privatekey.pem -nocerts -nodes
6. Store privatekey.pem and public.cer in /entra_cert/

====================

## Requirements
| Component           | Version / Requirement                          |
| ------------------- | ---------------------------------------------- |
| PHP                 | 8.1+                                           |
| OpenSSL             | Enabled                                        |
| HTTPS               | Required for Entra callbacks                   |
| Microsoft Entra App | Single-tenant, v2.0 endpoints                  |
| File permissions    | `/logs/` writable by PHP (for debug and audit) |

====================
## INDIVIDUAL FILE BREAKDOWN

index.html
Purpose: Login landing page

OVERVIEW:
 This page is the initial landing screen that users see before authenticating.
 It provides a single entry button that triggers the OAuth 2.0 / Microsoft Entra
 sign-in process. Once the button is clicked, the browser is redirected to 
 'login.php', which constructs and sends the authorization request to Microsoft.

DESIGN NOTES:
 - Uses a lightweight structure for quick page load.
 - Delegates authentication entirely to Microsoft (no credentials handled here).
 
Summary of What This File Does
| Section         | Description                                              |
| --------------- | -------------------------------------------------------- |
| Logo & Branding | Reinforces portal identity and trust                     |
| Intro Text      | Informs users that authentication is via Microsoft Entra |
| Sign-in Button  | Initiates the OAuth 2.0 flow by linking to login.php     |
| No Credentials  | Page never handles usernames or passwords directly       |

====================

login.php
Purpose: Initiate the Microsoft Entra ID OAuth 2.0 Authorization Request

OVERVIEW:
 This script begins the sign-in flow by redirecting the user’s browser to
 Microsoft’s authorization endpoint. It builds a URL containing all required
 OAuth 2.0 query parameters.

 After the user authenticates with Microsoft Entra ID, Microsoft will redirect
 the browser back to the Redirect URI defined in config.php
 (auth_callback.php) with an authorization code.

SECURITY NOTES:
 - The state parameter mitigates CSRF attacks by binding requests to sessions.
 - No credentials are handled here — login occurs on Microsoft’s servers.
 - Always served over HTTPS.
 
Summary of Parameters Sent to Microsoft
| Parameter     | Description                                                         |
| ------------- | ------------------------------------------------------------------- |
| client_id     | Identifies your app registration in Entra ID                        |
| response_type | Requests an authorization code (for server-side token exchange)     |
| redirect_uri  | Must match exactly what’s registered in Azure                       |
| response_mode | Specifies that Entra will append parameters in the query string     |
| scope         | Defines which identity information and permissions the app needs    |
| state         | Randomly generated string to prevent CSRF and replay attacks        |

====================

auth_callback.php
Purpose: Handles the OAuth 2.0 Authorization Code Callback for Microsoft Entra ID

This script is invoked by Microsoft Entra (Azure AD) after the user successfully authenticates. It receives an authorization code, exchanges it for access and ID tokens, validates them, creates a session, and redirects the user to the portal.

SECURITY NOTES:
- Uses certificate-based authentication (private key JWT).
- Guards against code replay (prevents “authorization code already redeemed”).
- Requires HTTPS — never deploy over HTTP.
- All tokens are stored server-side in $_SESSION.

FLOW SUMMARY:
1. Verify that the session is active.
2. Prevent the same authorization code from being reused.
3. Exchange the authorization code for tokens via Microsoft’s token endpoint.
4. Decode the ID token to extract user claims.
5. Persist user/session info.
6. Redirect securely to the authenticated home page.
 
Summary of What This File Controls
| Section          | Purpose                                                      |
| -----------------| ------------------------------------------------------------ |
| Session          | Makes sure PHP can store login data securely                 |
| Replay Guard     | Prevents “OAuth2 Authorization code already redeemed” errors |
| Input Validation | Handles invalid callback requests gracefully                 |
| Token Exchange   | Talks to Microsoft’s /token endpoint                         |
| Error Handling   | Displays the raw error array if token exchange fails         |
| Decode Token     | Extracts claims (user name, email, etc.) from the ID token   |
| Store Session    | Saves user/tokens to $_SESSION for use in home.php           |
| Redirect         | Sends the user to their authenticated home page              |

====================

config.php
Purpose: Central configuration and session management for Logon Entra Portal
This configuration file defines all constants and environment values used by the authentication scripts (auth_callback.php, home.php, etc.).

FUNCTIONS PROVIDED:
- Initializes session handling.
- Applies a 2-hour inactivity timeout for active user sessions.
- Defines core Microsoft Entra ID endpoints and credentials.
- Provides a reusable audit_log() function for security logging.

SECURITY NOTES:
- This file contains sensitive identifiers (Client ID, Tenant ID).
- Never commit this file to a public repository.
- The private key is stored locally in PEM format and referenced by path.
- Always deploy over HTTPS (never HTTP).

Summary of What This File Controls
| Section             | Description                                                         |
| ------------------- | ------------------------------------------------------------------- |
| Entra Configuration | Core tenant/app settings and endpoints used by OAuth 2.0            |
| Certificate Paths   | Points to your private/public key pair for certificate auth         |
| Session Handling    | Starts sessions if none exist                                       |
| Timeout Management  | Enforces 2-hour inactivity logout to mitigate session hijacking     |
| Audit Logging       | Logs login/logout/refresh events for monitoring and security review |

====================

home.php
Purpose: Authenticated landing page for Logon Entra Portal

This page is shown only after a successful Microsoft Entra ID login. 
It displays user information retrieved from the ID token and maintains the session through periodic silent token refreshes.
 
SECURITY NOTES:
- Requires an active session set during auth_callback.php.
- If no valid session exists, the user is redirected back to login.
- Includes client-side token refresh every 5 minutes to avoid expiry.

Summary of What This File Controls
| Section            | Function                                                           |
| ------------------ | ------------------------------------------------------------------ |
| Session Validation | Ensures user must be logged in to view the page.                   |
| Auto Refresh       | Keeps tokens valid by calling refresh_token.php every 5 minutes.   |
| User Display       | Shows name and preferred_username claims from Entra ID token.      |
| Logout             | Provides a link to terminate the current session.                  |

====================

logout.php
Purpose: Terminate user session and log logout event

OVERVIEW:
 This script securely ends the user's authenticated session within the
 Logon Entra Portal. It removes all session variables, destroys the session,
 and redirects the user back to the login page.

 An audit entry is created to record the logout event for security monitoring.

SECURITY NOTES:
 - The Microsoft Entra session itself (on Microsoft's servers) remains active, but local session data and tokens are cleared.
 - Always ensure session_destroy() is called after session_unset().
 - No user data should persist beyond this script’s execution.
 
Summary of Actions
| Step          | Function                             | Reason                                |
| --------------| ------------------------------------ | ------------------------------------- |
| Audit Log     | Records who logged out and when      | Enables security traceability         |
| Session Clear | Deletes all server-side session data | Prevents residual access              |
| Redirect      | Returns user to login page           | Clean UX and prevents back navigation |

====================

lib_client_assertion.php
Purpose: Generate a signed JWT "client assertion" for certificate-based OAuth 2.0 authentication

OVERVIEW:
 Microsoft Entra ID (Azure AD) supports certificate-based client authentication.
 Instead of sending a client secret, this script builds a short-lived JWT
 that proves the app’s identity using its private key.

PROCESS SUMMARY:
 1. Read and parse the public certificate (.cer) file.
 2. Compute SHA-1 and SHA-256 thumbprints for identification.
 3. Build a JWT header and payload following RFC 7523.
 4. Sign the header+payload with the app’s private key (RS256).
 5. Return the completed JWT for inclusion in token requests.

SECURITY NOTES:
 - JWTs are valid for 10 minutes only.
 - Private key should have read-only permissions (chmod 600).
 - Both files must match the certificate uploaded in Azure Portal.
 
Summary of Key Operations
| Stage               | Purpose                                                      | Output          |
| ------------------- | ------------------------------------------------------------ | ----------------|
| Load Certificate    | Reads and normalizes public certificate to DER binary        | $certDer        |
| Compute Thumbprints | Produces Base64-URL encoded SHA-1/256 fingerprints           | $x5t, $sha256   |
| Build JWT Claims    | Defines token audience, issuer, and expiration               | $payload        |
| Sign Assertion      | Uses RSA private key to sign the token                       | $signature      |
| Return JWT          | Combines header, payload, and signature into final assertion | Returned string |

Security Recommendations
 - Keep privatekey.pem on the web server outside the public web root if possible.
 - Restrict permissions to the PHP process user only (chmod 600).
 - Rotate certificates annually (your PowerShell renewal script automates this).
 - Verify certificate thumbprint matches the one visible in Azure Portal → Certificates & Secrets.

====================

refresh_token.php
Purpose: Safely renew Microsoft Entra access tokens using a refresh token

OVERVIEW:
 This script refreshes Microsoft Entra access tokens before they expire.
 It is typically called in the background by authenticated pages (like
 home.php) every few minutes via JavaScript fetch() calls.
 
 The token refresh ensures that:
 - Users remain signed in seamlessly (no re-login prompt).
 - The access token for Microsoft Graph or APIs stays valid.
 - The session lifespan stays within the defined inactivity timeout.

SECURITY NOTES:
 - Requires an active session with a valid refresh token.
 - Uses certificate-based authentication (private key JWT).
 - Responds only with simple text — never exposes token data.
 - Must be accessed over HTTPS.
 
Summary of Key Stages
| Step                   | Function                                             |
| -----------------------| ---------------------------------------------------- |
| Validate Refresh Token | Ensures a refresh token exists in the session        |
| Expiry Check           | Avoids unnecessary refreshes if token is still fresh |
| Build Client Assertion | Authenticates your app via private key JWT           |
| Construct Request      | Prepares POST body for Azure token endpoint          |
| Send HTTPS Request     | Executes secure call to /token endpoint              |
| Handle Response        | Updates stored tokens or logs error if refresh fails |

====================

safe_diagnostics.php
Purpose: Unified diagnostics, logging, and safe execution handler

SECURITY NOTES:
 - Never exposes certs, tokens, or secrets in output.
 - Full internal logs stored in /logs/debug.log.
 - Debug visibility controlled via constants or flag file.
 
| Section                             | Purpose / Behavior                                                                                                                            |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------|
| Global Constants                    | Defines runtime diagnostics behavior: enables debug output, whitelists dev IPs, sets log file path.                                           |
| secure_log()                        | Safely writes messages and context to /logs/debug.log. Automatically filters sensitive keywords (secret, key, cert, token, password).         |
| debug_show()                        | Displays green-on-black debug info *only* if DEBUG_ENABLED = true or user IP is whitelisted. Also calls secure_log() to persist same message. |
| safe_exec()                         | Executes a function in a protected try/catch. Logs all exceptions, prevents leaks, and triggers a friendly redirect to the error page.        |
| Redirect Logic (inside safe_exec()) | Handles graceful user experience after errors: tries HTTP 302 first, then <meta> and JavaScript fallback.                                     |
| Global Error Handler                | Catches PHP warnings and errors not handled elsewhere. Logs them and optionally displays a minimal warning box in developer mode.             |
| Global Exception Handler            | Catches all uncaught exceptions. Logs details (type, message, file, line, stack trace). In debug mode, displays minimal exception message.    |
| Output Filtering                    | Removes sensitive keywords from logs before writing to disk (protects cert/key/token data).                                                   |
| Error Page Rendering                | Ensures user-friendly visual experience instead of white screen; uses brand styling.                                                          |
| Optional Flag File Check            | Enables debug output automatically if /entra_cert/DEBUG_ON exists — no code changes needed.                                                   |


====================

error.html
Purpose: Error redirection page for the Entra ID Portal

OVERVIEW:
 This page is the error landing screen that users see if they are authenticating
 encounters an error during the sign-in process.

====================

style.css
Purpose: Core visual styles for Logon Entra Secure Login Portal

DESIGN GOALS:
 - Clean, modern aesthetic with soft gradients and elevation.
 - Consistent look across login and home views.
 - Focused layout: minimal distractions, centered content.
 - Accessible color contrast and scalable typography.

UX NOTES:
 - Neutral background emphasizes trusted branding.
 - No external fonts loaded directly (to avoid CSP issues).
 - Rounded corners and shadows provide visual hierarchy.
 
Summary of Design Intent
| Area                   | Purpose                                                                 |
| ---------------------- | ----------------------------------------------------------------------- |
| Flexbox layout         | Keeps content vertically & horizontally centered regardless of viewport |
| Gradient background    | Adds visual depth without distraction                                   |
| Box shadow             | Establishes focus and depth hierarchy                                   |
| Neutral color scheme   | Consistent with Microsoft Entra’s branding                              |
| Rounded corners        | Softens UI; improves visual accessibility                               |
| Hover transition       | Provides modern interaction feedback                                    |
| Optional focus outline | Helps users navigating via keyboard                                     |

END - INDIVIDUAL FILE BREAKDOWN
====================

## Debugging Tips
| Symptom                      | Likely Cause                       | Resolution                                  |
| ---------------------------- | ---------------------------------- | ------------------------------------------- |
| White screen                 | Output before header redirect      | Check safe_diagnostics.php redirect block   |
| Invalid client secret        | Wrong cert or private key mismatch | Verify certificate thumbprint               |
| No redirect after error      | Headers already sent               | Remove whitespace before <?php              |
| Still logged in after logout | Browser Entra session cached       | Test in incognito/private mode              |

====================

Logon Example <br>
<img width="448" height="424" alt="Landing" src="https://github.com/user-attachments/assets/65f958ae-966c-4109-a600-e46be55a6dac" />
<br>
<img width="458" height="459" alt="Sign In" src="https://github.com/user-attachments/assets/9d626b56-e81d-4b4a-a93c-611e9ca9daf0" />
<br>
<img width="455" height="458" alt="Authentication" src="https://github.com/user-attachments/assets/fa3aa5af-87d9-4cd6-9d14-a5ff4c251336" />
<br>
<img width="444" height="363" alt="Restricted Area" src="https://github.com/user-attachments/assets/47b1f430-71ba-440c-939c-dc6eec64ae0b" />
