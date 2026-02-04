# mycurator
MyCurator WordPress Plugin code repository

This code repository includes the MyCurator WordPress plugin code as well as the code for the Cloud Process (cloud-process subdirectory).  The tgtinfo-admin directory is a simple admin plugin that you load on your WordPress site to add API keys for users (Add Payment to add a user, Admin for changing user details) as well as a few other tools.  

The cloud process is a standalone php program that is called from the MyCurator plugin through PHP Curl. The file "Tables for Cloud Service" contains the SQL to create the tables as well as notes on which tables are needed.  The cloud process runs as a php program that is initiated by an Apache server at a specific website by placing the index.php file in the root directory of that server.  You will need to set the URL in the mycruator/Mycurator_local_proc.php in the mct_ai_callcloud subroutine by replacing the 'YourURL'.  

The cloud process receives a URL and uses the Diffbot service to grab the web page text. It needs a Diffbot API key to work (enter in the mycurator_cloud_functions.php program).  The cloud service also does keyword checking on the returned page as well as running it through a simple Bayesian algorithm to decide whether it passes the preferences of the client through the thumbs up/down mechanism.  It then returns the page with appropriate success or error codes.  It works in an asynchronous way with the Request process, but can be set up to run synchronously through an Option in the plugin.

## Version 3.81 - WordPress 6.9.1 Compatibility Update

### Requirements
- **PHP**: 8.0 or higher
- **WordPress**: 5.0 or higher (tested up to 6.9.1)

### Changelog

#### Version 3.81 (February 2026)
- Updated and tested compatibility with WordPress 6.9.1
- Verified all functionality with latest WordPress release

#### Version 3.80 - PHP 8.4 Compatibility
- Removed deprecated `mysql_query()` function calls (removed in PHP 7.0)
- Fixed dynamic property declarations in `class-mct-tw-api.php` for PHP 8.2+ compatibility
- All class properties now have explicit visibility modifiers

#### WordPress Compatibility
- Removed all deprecated `screen_icon()` function calls (deprecated since WP 3.8)
- Updated plugin headers with PHP and WordPress version requirements
- Tested and verified compatibility with WordPress 6.9.1

#### Code Quality
- Verified no usage of deprecated `create_function()` or `each()` functions
- Maintained proper WordPress sanitization and escaping throughout
- Uses mysqli and WordPress $wpdb methods for database operations

### Files Modified
- `mycurator/MyCurator.php` - Updated version, removed screen_icon() calls, added version requirements
- `mycurator/README.txt` - Updated compatibility information
- `mycurator/lib/class-mct-tw-api.php` - Fixed property declarations
- `cloud-process/mycurator_cloud_process.php` - Commented deprecated mysql_query()

### Upgrade Notes
When upgrading from previous versions, please ensure your hosting environment meets the minimum requirements:
- PHP 8.0 or higher
- WordPress 5.0 or higher

No database changes or data migration required.
