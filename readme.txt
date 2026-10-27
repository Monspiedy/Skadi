SKADI - INSTALLATION AND USER GUIDE
===================================

STATUS
------
Phase 03 authentication is complete. Registration, login, forced first-login
password change, protected account/admin access and CSRF-protected logout are
available. Store pages will be added in later phases.

PLANNED ENVIRONMENT
-------------------
- XAMPP: D:\xampp
- Project: D:\xampp\htdocs\Skadi
- URL: http://localhost/Skadi/
- PHP: 8.2
- Database: MariaDB/MySQL, database name "skadi"

DATABASE INSTALLATION
---------------------
1. Start Apache and MySQL in XAMPP.
2. Import kanta.sql into MariaDB/MySQL.
3. Copy config/database.example.php to config/database.local.php.
4. Enter local database credentials in config/database.local.php.
5. Open http://localhost/Skadi/.

CURRENT PAGES
-------------
- http://localhost/Skadi/ - home page
- http://localhost/Skadi/register.php - account registration
- http://localhost/Skadi/login.php - login-name/email sign-in
- http://localhost/Skadi/account.php - authenticated account page
- http://localhost/Skadi/change-password.php - authenticated password change
- http://localhost/Skadi/admin/ - admin-only guard demonstration
- http://localhost/Skadi/health.php - non-sensitive environment health check
- /errors/403.php, /errors/404.php and /errors/500.php - shared error pages

The home and error pages do not require a database connection. On the health page,
select the database connectivity test only after creating database.local.php.

The SQL dump was imported twice successfully with MariaDB 10.4.32 in an isolated
test data directory. The normal XAMPP control-panel startup was not verified because
its existing InnoDB data file was not writable in this environment. No real database
password is stored in the repository.

SESSION SECURITY
----------------
Sessions use cookies only, strict mode, HttpOnly and SameSite=Lax. The cookie is
scoped to /Skadi/ and becomes Secure automatically when HTTPS is detected. Local
HTTP development intentionally leaves Secure off so XAMPP can receive the cookie.
For production, serve only over HTTPS, ensure the Secure flag is present, and use
production PHP session storage/settings. Authenticated sessions expire after 30
minutes of inactivity and refresh database role/status information on each request.

DEVELOPMENT DATA
----------------
The dump contains twelve catalog games and two local seed accounts. Both accounts
are marked to require a password change. See the local Phase 01 handoff for temporary
seed credentials; never reuse them for a hosted installation.
