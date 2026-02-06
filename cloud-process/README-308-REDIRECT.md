# Cloud Service API - Troubleshooting

## HTTP 308 Permanent Redirect Error

If clients are receiving HTTP 308 errors when calling the cloud service, this is typically caused by web server redirects (nginx or Apache).

### Common Causes

1. **HTTP to HTTPS redirect**: Server is redirecting HTTP requests to HTTPS
2. **www to non-www redirect**: Server is redirecting www.domain.com to domain.com (or vice versa)
3. **Trailing slash redirect**: Server is redirecting URLs with/without trailing slashes

### Solutions

#### For Apache (with .htaccess)
The included `.htaccess` file in this directory disables rewrites for the cloud service API.

#### For Nginx
Add this to your nginx configuration for the cloud service location:

```nginx
location /cloud-process/ {
    # Disable redirects for API endpoint
    if ($scheme = http) {
        return 200;
    }
    
    # Or ensure the same protocol is used
    # Prefer HTTPS if available
    try_files $uri $uri/ /cloud-process/index.php?$query_string;
}
```

#### For Cloudflare or CDN
- Ensure SSL/TLS setting is set to "Full" or "Full (strict)", not "Flexible"
- Check Page Rules to ensure no redirects are applied to the API path
- Disable "Always Use HTTPS" for the cloud-process path

### Client-Side Fix
Ensure the client is using the correct URL:
- Use `https://` if the server requires HTTPS
- Use the exact domain format (with or without www) as configured
- Include or exclude trailing slashes as expected by the server

### Debugging
Check the error logs for entries like:
```
Cloud Service Request: Method=POST Content-Type=application/json Length=xxx
```

This will help identify if requests are reaching the PHP code or being redirected beforehand.
