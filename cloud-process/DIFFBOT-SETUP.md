# DiffBot API Token Configuration

## Overview
The MyCurator cloud service uses DiffBot API to extract article content from web pages. This requires valid DiffBot API tokens.

## Configuration Methods

The system supports two methods for DiffBot token configuration:

### Method 1: Admin-Configured Token (Primary/Recommended)
**Use this method if all users share the same DiffBot account.**

1. Open `mycurator_cloud_init.php`
2. Locate these lines:
   ```php
   define('DIFFBOT_TOKEN_BUSINESS', '');
   define('DIFFBOT_TOKEN_INDIVIDUAL', '');
   ```
3. Choose **ONE** and enter your DiffBot API token:

   **Option A - Business Token:**
   ```php
   define('DIFFBOT_TOKEN_BUSINESS', 'your_business_token_here');
   define('DIFFBOT_TOKEN_INDIVIDUAL', '');  // Leave empty
   ```

   **Option B - Individual Token:**
   ```php
   define('DIFFBOT_TOKEN_BUSINESS', '');  // Leave empty
   define('DIFFBOT_TOKEN_INDIVIDUAL', 'your_individual_token_here');
   ```

**Important Notes:**
- **Enter ONLY ONE token** - this is an either/or scenario, not both
- Use Business token if you have a DiffBot Business/Pro plan
- Use Individual token if you have a DiffBot Individual/Free plan
- The system will use whichever token is configured (non-empty)
- Leave the other token as empty string ''

### Method 2: User-Specific Tokens (Optional/Advanced)
**Use this method if different users have their own DiffBot accounts.**

Store tokens in the WordPress `wp_usermeta` table:
- **Key**: `tgtinfo_apikey`
- **Value**: User's DiffBot token (single string)

Example:
```php
update_user_meta($user_id, 'tgtinfo_apikey', 'user_diffbot_token_here');
```

**Note**: Each user typically has ONE token from their DiffBot account.

**Priority**: User-specific tokens (if present) override admin-configured tokens.

## Token Priority Flow

1. Check if user-specific token exists in `wp_usermeta` → Use it
2. If not, check admin-configured token (Business OR Individual) → Use it
3. If none available → Return error

## Getting DiffBot API Tokens

1. Sign up at [DiffBot.com](https://www.diffbot.com/)
2. Navigate to your dashboard
3. Copy your API token
4. Different plan levels may have different tokens

## Testing Configuration

After configuration, you can test by:
1. Running a manual DiffBot API call test (as mentioned in your testing)
2. Checking cloud service logs at `FPATH/page_log`
3. Looking for "No DiffBot API token configured" errors

## Troubleshooting

### Error: "No DiffBot API token configured"
- Check that tokens are set in `mycurator_cloud_init.php`
- Verify tokens are not empty strings
- Ensure the file has been saved

### Error: "Invalid JSON Object Returned" or TypeError on client
- **Common Cause**: Incorrect database name in `mycurator_cloud_init.php`
- Check `CS_DB` constant matches your actual database name
- Verify database connection credentials (CS_SERVER, CS_USER, CS_PWD)
- Check cloud service error logs for database connection errors
- Ensure database exists and user has proper permissions

### Error: "Diffbot Error" or HTTP errors
- Verify your DiffBot token is valid
- Check your DiffBot account is active and not expired
- Confirm the token has correct permissions

### Error: "Error Rendering Page"
- The DiffBot API successfully connected but couldn't extract content
- Check if the URL is accessible to DiffBot
- Some sites block DiffBot's crawler

## Database Migration

If you want to use user-specific tokens (Method 2), ensure the database is updated:

```sql
-- Add user ID tracking to requests table
ALTER TABLE `wp_cs_requests` 
ADD COLUMN `rq_userid` int DEFAULT NULL
AFTER `rq_dbkey`;
```

See `migrate_add_userid.sql` for details.

## Security Notes

- Keep your DiffBot API tokens secure
- Do not commit `mycurator_cloud_init.php` with actual tokens to version control
- Consider using environment variables for production deployments
- Rotate tokens periodically for security

## Support

For DiffBot API issues, contact DiffBot support: https://www.diffbot.com/support/
