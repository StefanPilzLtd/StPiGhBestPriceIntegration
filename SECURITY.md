# Security policy

Do not publish credentials, order data or customer data in issues. Report suspected vulnerabilities privately through the contact route at https://stefanpilz.ltd. Include affected version, minimal reproduction with synthetic data and expected impact. A response-time SLA is not yet agreed. Security fixes are not a paid feature.

No production deployment is approved until the security and release gates pass. See docs/security/threat-model.md. Repository secrets and access tokens must remain in the appropriate provider secret store; never commit auth.json or .env files.
