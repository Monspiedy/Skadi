SKADI - INSTALLATION AND USER GUIDE
===================================

STATUS
------
Phase 10 profiles, privacy, presence and friendships are complete. Registration, login, forced first-login
password change, protected account/admin access and CSRF-protected logout remain
available. The responsive shared shell includes local Bootstrap 5.3.8 plus Skadi,
Dark and Light themes. The store now supports featured games, title search, genre and
price filters, safe sorting and pagination. Read-only game pages show complete seeded
facts, active rating summaries, owner reviews, uploaded covers and screenshot galleries. Admins
can manage games, genres, validated image media and an ordered maximum of five featured
games. Authenticated users can keep a database-backed cart, complete demo-money purchases,
and inspect wallet and order history. Users can search and hide library games, permanently remove
access without a refund, manage a wishlist and publish one 1-10 review per owned game. From Friday
00:00 through Monday 00:00 in Europe/Helsinki, one persisted shared discount set applies to all users. Public
profiles use immutable Skadi IDs; users can search for people, manage friend requests and block accounts while
one shared privacy rule protects libraries, reviews, activity and friend lists.
Admin wallet adjustment and review moderation UI are not part of this phase.

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
- http://localhost/Skadi/game.php?id=1 - game detail, owner review and active review list
- http://localhost/Skadi/community.php - clearly marked community placeholder
- http://localhost/Skadi/people.php - public display-name and exact Skadi ID search
- http://localhost/Skadi/profile.php?id=SKD-DEMO-0001 - public profile with privacy-aware sections
- http://localhost/Skadi/profile-settings.php - authenticated profile, avatar and privacy settings
- http://localhost/Skadi/friends.php - authenticated friends, requests and created blocks
- http://localhost/Skadi/library.php - searchable authenticated library and access controls
- http://localhost/Skadi/wishlist.php - authenticated wishlist and move-to-cart controls
- http://localhost/Skadi/register.php - account registration
- http://localhost/Skadi/login.php - login-name/email sign-in
- http://localhost/Skadi/account.php - authenticated account page
- http://localhost/Skadi/cart.php - authenticated persistent cart and checkout
- http://localhost/Skadi/wallet.php - authenticated balance and wallet ledger history
- http://localhost/Skadi/orders.php - authenticated purchase history and price snapshots
- http://localhost/Skadi/change-password.php - authenticated password change
- http://localhost/Skadi/admin/ - admin-only catalog administration
- http://localhost/Skadi/admin/games.php - game, cover and screenshot management
- http://localhost/Skadi/admin/genres.php - genre management
- http://localhost/Skadi/admin/featured.php - ordered featured-game selection
- http://localhost/Skadi/admin/weekend-sale.php - sale generation audit and admin-only regeneration
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

DEMO COMMERCE
-------------
Cart mutations and checkout require an authenticated session, POST and a valid CSRF token.
The browser submits game identifiers only; current prices, active persisted discounts,
ownership and wallet balance are loaded by the server. Checkout locks the affected rows and
creates the order, immutable order-item price snapshots, wallet debit, ownership records and
cart cleanup in one database transaction. Free games create order and ownership history while
leaving the wallet unchanged. Users cannot top up demo funds.

WEEKEND SALE
------------
Sale periods use real Europe/Helsinki calendar boundaries: Friday 00:00 is inclusive and
Monday 00:00 is exclusive. The first request during a missing period creates one complete
shared discount set for every paid published game. Discounts are persisted in five-point
steps from 10 to 75 percent, so refreshes and different users see the same generation.

Catalog, cart, wishlist moves and checkout all use the same server current-price service.
Free games are excluded, prices outside the window return to base price, and completed
order-item snapshots never change. The storefront JavaScript countdown uses a server-provided
timestamp for display only; checkout does not trust it. Administrators can inspect generation
audit information and regenerate the active sale after acknowledging a warning.

LIBRARY, WISHLIST AND REVIEWS
-----------------------------
Library searches and visibility filters are scoped to the signed-in user. Hide, unhide and
permanent removal require POST plus CSRF. Permanent removal also requires explicit confirmation,
does not refund demo money and does not delete order or wallet history. Reacquiring a removed game
reactivates its unique library row instead of creating duplicate ownership.

Wishlist mutations are authenticated and CSRF-protected. Owned games cannot be wishlisted. Moving
a wishlist item to the cart is transactional, and a completed purchase removes matching wishlist
rows in the checkout transaction. Only current owners may create or edit one rating from 1 to 10
per game. Review text is escaped. Deleted and moderation-hidden reviews do not appear publicly and
do not affect the displayed average or review count.

PROFILES, PRIVACY AND FRIENDS
-----------------------------
Display names are changeable and may be duplicated. Login names remain private and immutable; the public,
immutable Skadi ID is used for profile routes and exact-ID searches. Profile settings also cover unique email,
biography, Skadi/Dark/Light theme, incoming-request preference, presence visibility and public/friends-only/private
detail visibility. Avatar uploads accept content-verified JPG, PNG or WebP images up to 2 MB and use generated names.

The same privacy decision protects library, active reviews, recent activity and accepted friends. Display name,
avatar and subtle Skadi ID remain discoverable. Presence is based on recent authenticated activity and disappears
when hidden. Friend requests can be sent, cancelled, accepted or declined; either friend can remove the relationship.
Acceptance enforces a maximum of 100 friends on the server. Blocking removes friendship, prevents new requests and
private-detail access, and remains active until the blocker removes it. Direct chat is intentionally Phase 11 scope.

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
