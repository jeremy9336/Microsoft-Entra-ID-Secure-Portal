# Contributing to XNA Lab Entra Secure Portal

Thank you for considering contributing to this project!  
We welcome all types of contributions — from code and documentation to testing and issue triage.

---

## How to Contribute

### Reporting Issues
 - Use the **Issues** tab on GitHub.
 - Clearly describe the problem, expected behavior, and steps to reproduce.
 - Include relevant logs (with sensitive data redacted).

### Suggesting Features
 - Open a new issue with the **"enhancement"** label.
 - Explain how the feature benefits users and maintains project security standards.

### Submitting Code
1. **Fork** the repository.
2. **Create a branch** for your feature or fix:
git checkout -b feature/add-logging-enhancement
3. Commit changes using descriptive messages:
git commit -m "Add better token refresh logging"
4. Push to your fork and open a Pull Request (PR) against main.

### Code Standards
 - Use clear, readable, and well-documented PHP.
 - Wrap all risky operations in safe_exec() for diagnostics compatibility.
 - Maintain consistent indentation (4 spaces) and meaningful variable names.
 - All new files should include a version header and author block.

### Testing

Before submitting a PR:
 - Verify authentication flow works (index.html → login.php → home.php → logout.php).
 - Confirm no new warnings appear in /logs/debug.log.
 - Ensure you haven’t exposed any secrets, keys, or paths.

### Security Notes
If your contribution relates to security (e.g., authentication, encryption, session handling):
 - Never post actual credentials or keys.
 - Review /SECURITY.md for disclosure policies.
 - Use responsible reporting for vulnerabilities.

### Development Setup
Requirements:
 - PHP 8.1+
 - OpenSSL extension enabled
 - HTTPS web server (Apache/Nginx)

Recommended flow:
git clone https://github.com/xnalab/entra-secure-portal.git
cd entra-secure-portal

###Code Review Process
 - All Pull Requests are reviewed by at least one maintainer.
 - Code must pass internal tests and maintain session safety.
 - Approved PRs will be merged into main and included in the next release.

### License
By contributing, you agree that your contributions will be licensed under the MIT License.


