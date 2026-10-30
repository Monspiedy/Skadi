SKADI - XAMPP INSTALLATION, USE AND SECURITY GUIDE
==================================================

1. PURPOSE AND VERIFIED STATUS
------------------------------
Skadi is an educational game store and community application. All money is demonstration
money; there is no real payment processing or game download service.

The Phase 16 release rehearsal used PHP 8.2.12 and the XAMPP MariaDB 10.4.32 binaries.
A fresh isolated import of kanta.sql produced 39 tables/views. Registration, first-login
password changes, catalog browsing, checkout, order/wallet/library updates, reviews, a
weekend-sale regeneration, friends/chat, a guide, a discussion, admin game CRUD and an
audited wallet adjustment all passed. Authenticated browser checks also passed at 390 px
and 1440 px with no console errors or horizontal overflow.

The normal XAMPP control-panel MariaDB data directory was not repaired or verified in that
test environment because its existing ibdata1 file was not writable. Follow the checks in
section 3, step 7 on the presentation computer before relying on the site.

2. REQUIREMENTS
---------------
- Windows with XAMPP installed at D:\xampp
- PHP 8.2 and MariaDB/MySQL
- Project folder D:\xampp\htdocs\Skadi
- Local URL http://localhost/Skadi/
- Apache modules that allow the committed .htaccess rules

No Composer, npm, framework, CDN or external account is required. Bootstrap 5.3.8 is stored
locally under assets/bootstrap/.

3. CLEAN XAMPP SETUP
--------------------
IMPORTANT: kanta.sql resets the Skadi schema. Back up an existing skadi database before import.

1. Copy/clone the project to:
   D:\xampp\htdocs\Skadi

2. Open the XAMPP Control Panel and start Apache and MySQL.

3. Import the database using ONE of these methods.

   phpMyAdmin:
   a. Open http://localhost/phpmyadmin/.
   b. Choose Import.
   c. Select D:\xampp\htdocs\Skadi\kanta.sql.
   d. Keep UTF-8/utf8mb4-compatible defaults and run the import.
   e. Confirm that database skadi exists. The dump creates/selects it automatically.

   MariaDB command line from PowerShell (prompts for the MariaDB password):
   & D:\xampp\mysql\bin\mysql.exe --user=root --password --execute="SOURCE D:/xampp/htdocs/Skadi/kanta.sql"

   If the local root account intentionally has a blank password, use:
   & D:\xampp\mysql\bin\mysql.exe --user=root --password= --execute="SOURCE D:/xampp/htdocs/Skadi/kanta.sql"

4. Create the ignored local configuration:
   Copy-Item D:\xampp\htdocs\Skadi\config\database.example.php D:\xampp\htdocs\Skadi\config\database.local.php

5. Edit config/database.local.php. For a default local XAMPP installation it normally uses:
   host     127.0.0.1
   port     3306
   database skadi
   charset  utf8mb4
   username root
   password the local MariaDB password (often blank only on a local XAMPP setup)

6. Open http://localhost/Skadi/.

7. Verify the environment:
   - http://localhost/Skadi/health.php should show the PHP checks.
   - http://localhost/Skadi/health.php?check=db should report successful DB connectivity.
   - http://localhost/Skadi/store.php should show twelve seeded games.

4. INITIAL ACCOUNTS AND FIRST LOGIN
-----------------------------------
The clean dump contains two local-only demonstration accounts:

Administrator
  Login:    skadi_admin
  Password: ChangeMe!2026

User
  Login:    demo_player
  Password: ChangeMe!2026

Both accounts have must_change_password enabled. After signing in, enter the temporary
password and choose a new strong password before any protected page can be used.

For a hosted installation, do not keep the seed credentials. Register a separate account,
use the administrator account to promote it to admin, verify the new administrator works,
and only then demote/block the seed accounts. The final-active-admin protection prevents
accidentally removing the last active administrator.

5. USER WORKFLOWS
-----------------
Create and sign in
  1. Open register.php and enter an immutable login name, email, public display name and password.
  2. Registration creates the profile, public Skadi ID and EUR 100.00 demo balance atomically.
  3. Later sign in with the login name or email. Public pages never show the login name.

Browse and purchase
  1. Open Store; search, filter by genre/price, choose a safe sort and browse pages. With
     JavaScript enabled, the title field offers up to five published-game suggestions; the
     same server-rendered search remains available without JavaScript.
  2. Open a game, add it to Cart and review the current server price.
  3. Complete checkout. The server rechecks price, ownership and balance in one transaction.
  4. Confirm the same purchase in Orders, Wallet and Library.

Library, wishlist and reviews
  - Add non-owned games to Wishlist and move them to Cart.
  - Hide/unhide library entries. Permanent removal requires confirmation, gives no refund
    and preserves order/wallet history.
  - Current owners can create or edit one 1-10 review per game.

Profile, friends and chat
  - Edit display name, email, biography, theme, avatar and privacy choices in Profile settings.
  - Find users by partial display name or exact Skadi ID; send/accept/decline/cancel requests.
  - Accepted unblocked friends can chat. Polling refreshes about every three seconds.
  - Chat supports text plus one JPG, PNG, WebP, PDF or TXT attachment up to 5 MB.
  - Hide removes a message only for the current participant. Delete for everyone physically
    removes the sender's message and attachment without a tombstone.
  - Blocking removes/prevents friendship and immediately disables chat/private access.
  - Signed-in navigation shows authoritative pending incoming-request and unread-chat totals.

Guides and community
  - Any signed-in user can draft/publish a guide for a published game. The editor supports
    a small safe BBCode subset, votes, favorites and comments.
  - Community Center aggregates discussions, guides, screenshots and posts with a game filter.
  - Game hubs reuse the canonical Overview, Discussions, Guides, Screenshots and Reviews data.
  - Users can create categorized topics/replies and text/image posts, like/comment and report
    supported public content. Private chat never appears in community/admin feeds.

Themes and JavaScript
  - Choose Skadi, Dark or Light in the navigation. Guest choice uses localStorage; signed-in
    choice is also stored in user_profiles.
  - JavaScript visibly powers theme persistence, countdown, live catalog suggestions, chat
    polling/unread updates, upload previews, confirmations, guide preview and dismissible
    success toasts. Core catalog search and escaped flash feedback remain server-rendered.

6. ADMINISTRATOR WORKFLOWS
--------------------------
Every admin page and handler rechecks the current database-backed administrator role.

- Dashboard: inspect user, game, order, report and hidden-content counts.
- Games/Genres: create and edit catalog facts and many-to-many genre assignments. Referenced
  records are not silently deleted.
- Media: upload JPG/PNG/WebP covers and screenshots. Files are checked by content, extension,
  size and dimensions, then receive generated server filenames.
- Featured: select/order at most five published games.
- Users: search safe account fields, change role/status and make signed wallet adjustments.
  Adjustments require a reason, cannot produce a negative balance and create ledger/audit rows.
- Commerce: inspect read-only order and wallet histories.
- Moderation/Reports: hide, restore or delete supported public content, pin/lock topics and
  resolve/dismiss reports with notes. Deleted moderation records remain auditable.
- Weekend sale: inspect persisted generations/discounts. During an active Friday-to-Monday
  Helsinki window, acknowledge the warning to regenerate the shared demonstration sale.

Administrators never receive password hashes, private login names or a private-message reader.

7. DATA, UPLOAD AND SECURITY NOTES
----------------------------------
- config/database.local.php, .env files, logs, caches and runtime uploads are ignored by Git.
- Public images are stored under uploads/* after allowlist validation and generated naming.
- Chat attachments are stored in storage/chat-attachments and served only by an endpoint that
  confirms the current participant and friendship/block state.
- uploads/.htaccess and storage/chat-attachments/.htaccess add local Apache defense in depth.
- Passwords use password_hash()/password_verify(); sessions regenerate after authentication.
- Session cookies are HttpOnly, SameSite=Lax and scoped to /Skadi/. Secure is intentionally off
  on local HTTP and turns on when PHP detects HTTPS.
- Every mutation uses POST and CSRF validation. Request values use PDO prepared statements.
- Checkout and wallet changes use transactions, row locks and server-calculated values.
- Production must use HTTPS, production session storage, least-privilege DB credentials and
  web-server rules that disable executable handlers in every upload directory.
- The included twelve game names/factual seed summaries are demonstration data. No promotional
  artwork is tracked; the interface uses original CSS placeholders and local licensed Bootstrap.

8. TROUBLESHOOTING
------------------
Apache will not start
  - Check the XAMPP log for a port 80/443 conflict. Stop the conflicting service or configure
    Apache consistently, then use the matching URL.

MySQL will not start / ibdata1 is not writable
  - Stop MySQL before changing anything. Check Windows permissions and the XAMPP MySQL log.
  - Do not delete/reset an existing data directory without a backup. The release tests used a
    separate isolated data directory and did not repair the normal XAMPP directory.

"Local database configuration is missing"
  - Copy config/database.example.php to config/database.local.php and enter local values.

Database connectivity fails
  - Confirm MySQL is running, the host/port/user/password are correct and database skadi exists.
  - Re-import kanta.sql only if resetting Skadi data is intended.

404 or broken links under another folder name
  - The application is configured for /Skadi in config/app.php and the committed .htaccess paths.
    Keep the folder name Skadi, or update both settings consistently.

Uploads fail
  - Confirm PHP file_uploads is enabled, upload/post limits exceed the documented feature limit,
    and the relevant uploads/* or storage/chat-attachments directory is writable by Apache.

Login returns to password change
  - This is expected for seed accounts until the temporary password is replaced.

Sale discount is not active
  - The real Helsinki window is Friday 00:00 through Monday 00:00. Outside it the base price is
    authoritative, even if historical/scheduled sale rows exist.

9. PRESENTATION AND RELEASE
---------------------------
Use docs/PRESENTATION.md for the repeatable demonstration order and teacher Q&A.
This commit passed independent coordinator verification and is approved for the
v1.0-presentation tag. It has not been deployed. The Phase 16 implementation chat itself did
not commit, push, deploy or create the tag; those release actions remain with the coordinator.
