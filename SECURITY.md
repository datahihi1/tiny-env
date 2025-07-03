# Security Policy

## Supported Versions

Only the latest major version of TinyEnv is actively supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.0     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability in TinyEnv, **please do not open a public issue**. Instead, report it privately by emailing:

- **datahihi1100@gmail.com**

Please include as much detail as possible to help us quickly resolve the issue (e.g., code samples, environment, version, and a description of the vulnerability).

We will respond as soon as possible and coordinate a fix and disclosure timeline.

## Security Best Practices

- **Never commit your `.env` file to public repositories.**
- Ensure your `.env` file is not accessible via the web server (e.g., place it outside the public directory or block access via server config).
- Set proper file permissions for `.env` (e.g., `600` or `640`).
- Do not hardcode sensitive keys or secrets in your codebase.
- Always use the latest version of TinyEnv for the latest security patches.

## Disclosure Policy

We aim to handle all security reports promptly and responsibly. After a fix is available, we will publish a security advisory and encourage all users to update.

Thank you for helping keep TinyEnv and its users safe!
