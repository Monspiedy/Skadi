SKADI - INSTALLATION AND USER GUIDE
===================================

STATUS
------
Phase 01 database foundation is complete. Application pages have not been built,
so the website itself is not yet usable.

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

The SQL dump was imported twice successfully with MariaDB 10.4.32 in an isolated
test data directory. The normal XAMPP control-panel startup was not verified because
its existing InnoDB data file was not writable in this environment. No real database
password is stored in the repository.

DEVELOPMENT DATA
----------------
The dump contains twelve catalog games and two local seed accounts. Both accounts
are marked to require a password change. See the local Phase 01 handoff for temporary
seed credentials; never reuse them for a hosted installation.
