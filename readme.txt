SKADI - INSTALLATION AND USER GUIDE
===================================

STATUS
------
Phase 06 game media and focused catalog administration is complete. Registration, login, forced first-login
password change, protected account/admin access and CSRF-protected logout remain
available. The responsive shared shell includes local Bootstrap 5.3.8 plus Skadi,
Dark and Light themes. The store now supports featured games, title search, genre and
price filters, safe sorting and pagination. Read-only game pages show complete seeded
facts, rating and price placeholders, uploaded covers and screenshot galleries. Admins
can manage games, genres, validated image media and an ordered maximum of five featured
games. User administration, wallet tools and moderation are not part of this phase.

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
- http://localhost/Skadi/store.php - searchable and filterable game catalog
- http://localhost/Skadi/game.php?id=1 - read-only game detail page
- http://localhost/Skadi/community.php - clearly marked community placeholder
- http://localhost/Skadi/library.php - authenticated library placeholder
- http://localhost/Skadi/register.php - account registration
- http://localhost/Skadi/login.php - login-name/email sign-in
- http://localhost/Skadi/account.php - authenticated account page
- http://localhost/Skadi/change-password.php - authenticated password change
- http://localhost/Skadi/admin/ - admin-only catalog administration
- http://localhost/Skadi/admin/games.php - game, cover and screenshot management
- http://localhost/Skadi/admin/genres.php - genre management
- http://localhost/Skadi/admin/featured.php - ordered featured-game selection
- http://localhost/Skadi/health.php - non-sensitive environment health check
- /errors/403.php, /errors/404.php and /errors/500.php - shared error pages

The home and error pages do not require a database connection. On the health page,
select the database connectivity test only after creating database.local.php.

THEMES AND OFFLINE ASSETS
-------------------------
Use the Theme selector in the main navigation to choose Skadi, Dark or Light.
Guest preferences remain in that browser's localStorage. Signed-in preferences are
saved in user_profiles through a CSRF-protected JSON endpoint and are written into
the next page before CSS loads. With JavaScript disabled, the server-rendered Skadi
theme and all navigation/content remain readable; interactive switching is disabled.

Bootstrap 5.3.8 CSS and JavaScript are stored in assets/bootstrap/. Its upstream
MIT license is retained at assets/bootstrap/LICENSE. The application does not load
presentation assets from a CDN.

GAME MEDIA UPLOADS
------------------
Administrators can upload JPG, PNG and WebP covers and screenshots from a saved game's
edit page. Covers are limited to 5 MB and screenshots to 8 MB. The server validates the
actual MIME type, extension, readable image dimensions and context-specific dimensions,
then generates a random storage filename. Replaced and deleted managed files are cleaned
from uploads/covers or uploads/screenshots. Static assets are never deleted by this path.
The uploads/.htaccess defense prevents script execution; production hosting should also
disable executable handlers in the upload directory at the virtual-host level.

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
