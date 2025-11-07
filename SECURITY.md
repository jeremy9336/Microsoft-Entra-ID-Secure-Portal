## Security Policy

This document describes how security issues are handled and reported for the XNALab Entra Secure Portal project.

====================

## Supported Versions
|---------|-----------------|
| Version | Supported       |
|---------|-----------------|
| 1.x.x   | Fully Supported |
| < 1.0.0 | Not supported   |

Security patches are applied to the most recent release. Older versions may not receive updates.

====================

## Reporting a Vulnerability

If you discover a security vulnerability, **please do not create a public issue**.

Instead, contact the security maintainers directly via email: jeremy@xnalab.us

Your report should include:
- A detailed description of the vulnerability.
- Steps to reproduce (if applicable).
- Any potential impact or exploit vector.
- Optional proof of concept or logs.

We aim to acknowledge receipt of security reports **within 48 hours** and provide a timeline for investigation and remediation.

====================

## Security Best Practices

For developers deploying or modifying this portal:
- Always deploy over HTTPS.
- Protect /entra_cert/privatekey.pem with strict file permissions (readable only by the web server).
- Disable DEBUG_ON in production.
- Rotate certificates regularly (recommended every 12 months).
- Keep all server software and PHP libraries up to date.

====================

## Responsible Disclosure

XNA Lab follows a Responsible Disclosure Policy:
- Please report issues privately.
- We will coordinate a fix and public disclosure timeline.
- You will be credited (if desired) in the release notes.

====================

## Additional Resources

- [Microsoft Entra Security Best Practices](https://learn.microsoft.com/en-us/entra/fundamentals/security-operations-overview)
- [OWASP PHP Security Guidelines](https://owasp.org/www-project-php-security/)
- [OAuth 2.0 Threat Model](https://datatracker.ietf.org/doc/html/rfc6819)
