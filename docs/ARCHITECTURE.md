# Skadi architecture

## Approach

Skadi deliberately uses a small modular PHP architecture rather than a framework. Page controllers remain easy for a course student to trace, while shared responsibilities live in focused includes and services.

Planned request flow:

1. Entry page loads `config/bootstrap.php`.
2. Bootstrap starts the session, loads configuration, creates PDO and loads helpers.
3. Page-level authorization is checked before output.
4. Page queries data through focused repository/helper functions.
5. Shared header/navigation/footer are included.
6. POST mutations run in `actions/`; JSON polling endpoints run in `api/`.

## Planned folders

```text
admin/                 Admin pages
actions/               Form POST handlers and redirects
api/                   JSON endpoints used by JavaScript
assets/css/            Local Bootstrap and project CSS
assets/js/             Page-focused JavaScript modules
assets/images/         Versioned original/static images
config/                Bootstrap, app config and local DB config
database/              Schema notes and development helpers
docs/                  Specification, roadmap, prompts and handoffs
includes/              Shared layout and PHP helpers
storage/               Private runtime files, caches and logs
uploads/               Public validated images
```

## Configuration

`config/database.example.php` will be committed. The developer copies it to ignored `config/database.local.php`. No database password is committed. Base URLs are derived from a single application setting and never hard-coded across pages.

## Implemented Phase 02 PHP foundation

- `config/bootstrap.php` loads application settings, error handling, helpers, lazy PDO support and the session.
- `config/database.php` reads only the ignored local configuration and creates a strict PDO connection on demand.
- Focused helpers in `includes/` cover URLs, output escaping, flash messages, CSRF, validation and authentication/role guard stubs.
- Shared header, navigation, flash-message and footer includes provide the page shell.
- `errors/403.php`, `errors/404.php` and `errors/500.php` render the same safe layout without exposing exception details.
- `health.php` reports non-sensitive PHP requirements and tests database connectivity only when explicitly requested.

The foundation remains procedural and intentionally small. Later phases should add focused helpers instead of turning `bootstrap.php` into application logic.

## Implemented Phase 03 authentication

- `includes/auth-service.php` owns registration, login, password-change and Skadi ID database operations.
- Registration validates safe input, hashes the password and creates `users`, `user_profiles` and the EUR 100.00 `wallet_transactions` entry in one database transaction.
- Session helpers regenerate identifiers after authentication and password changes, enforce a 30-minute inactivity timeout, refresh role/status/password-change state from the database and update `last_activity_at` at a limited interval.
- `requireGuest()`, `requireAuth()` and `requireAdmin()` provide page-level guards. Seed accounts with temporary credentials are redirected to password change before normal protected access.
- Form mutations use the shared CSRF helper. Logout is POST-only, and validation state never stores password fields.
- `account.php` and `admin/index.php` demonstrate authenticated and administrator-only direct-URL protection without implementing later profile or admin CRUD scope.

Local HTTP deliberately cannot set a `Secure` cookie. The shared session configuration enables it automatically under HTTPS; production deployment must use HTTPS and production-managed PHP session storage.

## Implemented Phase 04 interface and themes

- The shared header, Bootstrap collapse navigation, page container and footer are rendered through `includes/` on every application page.
- Official Bootstrap 5.3.8 compiled CSS and bundled JavaScript are stored under `assets/bootstrap/` with the upstream MIT license, so no CDN is required.
- Theme tokens are isolated in `assets/css/themes.css`; responsive shell and component rules are isolated in `assets/css/layout.css`.
- `Skadi`, `Dark` and `Light` are applied through `data-theme`, with matching Bootstrap color mode metadata.
- A small head script restores a guest preference from `localStorage` before styles load. Signed-in users receive their database preference directly in the server-rendered `<html>` element.
- Signed-in changes use `actions/theme.php`, JSON Fetch, a CSRF header, an allowlist and a prepared `user_profiles` update. The session value changes only after the database operation succeeds.
- Reusable empty, error and loading state markup lives in `includes/ui-components.php`. Store, community and library links use explicit non-querying placeholders until their approved phases.

## Implemented Phase 05 catalog and game pages

- `includes/catalog.php` owns catalog validation, fixed sort mappings, prepared filter queries, review/ownership aggregates, genre loading, pagination helpers and read-only game detail retrieval.
- `store.php` renders up to five administrator-selected featured games and a six-item paginated catalog with title search, genre and price filters, five sort options and preserved query state.
- `game.php` accepts only a positive integer ID and renders database-backed facts, genres, base/current-price placeholders, rating summary, original cover fallback, server-rendered gallery state, system requirements and future-module links.
- Request values are bound through PDO except `ORDER BY`, which can only select from fixed application-owned SQL fragments. Invalid query values reset safely with a visible explanation.
- Media paths pass a narrow public-path allowlist before becoming image URLs. Phase 05 does not upload files, generate sales, purchase games or create reviews.

## Implemented Phase 06 media and focused catalog administration

- `admin/games.php`, `admin/game-form.php`, `admin/genres.php` and `admin/featured.php` are protected by `requireAdmin()` on every direct request.
- `includes/admin-catalog.php` owns validated game/genre CRUD, many-to-many genre updates, dependency-aware deletion and transactional featured ordering with an application maximum of five.
- `includes/uploads.php` provides reusable content-based image validation and generated storage names while keeping cover and screenshot size/dimension policies explicit.
- Covers use one position-zero `game_media` row; replacement stores the new image before the database transaction and removes only the previous managed upload after commit. Screenshots support multiple validated files with a twelve-image cap and stable sort positions.
- State-changing handlers live in `actions/admin/`, require POST plus CSRF, and never trust browser MIME values, original paths, client roles or featured positions.
- `uploads/.htaccess` disables executable handlers as defense in depth. Cleanup accepts only generated paths under `uploads/covers/` and `uploads/screenshots/`, so versioned/static assets cannot be deleted.
- `assets/js/admin-media.js` adds local image previews, duplicate featured selection feedback and destructive-action confirmation. Server validation remains authoritative when JavaScript is absent or bypassed.

## Implemented Phase 07 commerce

- `includes/commerce.php` owns integer-cent money conversion, active-sale price reads, cart persistence, wallet/history queries and transactional checkout.
- Cart add/remove and checkout mutations live in `actions/`, require authentication, POST and CSRF, and accept identifiers only; prices and balances always come from locked database rows.
- Checkout locks the user first to serialize repeated submissions, then locks cart, active sale, game, discount and ownership inputs. One transaction creates the completed order and immutable item snapshots, adds ownership, conditionally debits the wallet and ledger for paid orders, and clears the cart.
- Free acquisitions create normal zero-total orders, order items and library records. They leave the balance unchanged and do not insert a forbidden zero-amount wallet ledger row.
- `cart.php`, `wallet.php` and `orders.php` expose persistent cart state, current-price change notices, current balance, append-only wallet entries and historical paid-price snapshots.
- Current pricing is calculated in integer cents. Phase 07 consumes active persisted discount rows; Phase 09 remains responsible for generating and scheduling weekend sales.

## Implemented Phase 08 library, wishlist and reviews

- `includes/library-service.php` owns user-scoped library queries, visibility changes, transactional access removal, wishlist operations and owner-review lifecycle rules.
- `library.php` provides title search and visible/hidden/all filters. Hide, unhide and permanent removal mutations require authenticated POST requests and CSRF; permanent removal also requires explicit server-checked confirmation.
- Removed ownership stays as one historical `library_items` row. Reacquisition reactivates that row inside checkout, preventing duplicate ownership while linking the latest acquisition item. Orders, order items and wallet entries remain immutable.
- `wishlist.php` supports remove and transactional move-to-cart. Wishlist adds reject current owners, and checkout removes purchased wishlist rows in the same purchase transaction.
- Game pages list active reviews, escaped optional text, active-only average/count and author forms. Current owners can create or edit one 1-10 review; authors can soft-delete their review. Moderated hidden/deleted reviews cannot be republished around moderation, and hidden/deleted rows are excluded from public reads and aggregates.
- Shared layout CSS keeps library, wishlist and review content responsive; a small JavaScript module enhances destructive confirmations while server confirmation remains authoritative.

## Database conventions

- InnoDB tables and UTF-8 (`utf8mb4`)
- unsigned integer primary keys unless a different type is justified
- foreign keys and useful indexes
- timestamps stored in UTC and presented in `Europe/Helsinki`
- decimal money values, never floating point
- unique constraints for login, email, Skadi ID, ownership and one-vote relations
- soft status fields for moderated public content where auditability matters
- real deletion for a chat message deleted for everyone, including its attachment
- canonical low/high user pairs for friendships, blocks and conversations
- admin views that omit login names, password hashes and private-chat data

## Implemented Phase 01 schema

Identity and social:

- `users`
- `roles`
- `user_profiles`
- `friendships`
- `user_blocks`

Store:

- `games`
- `genres`
- `game_genres`
- `game_media`
- `featured_games`
- `weekly_sales`
- `weekly_discounts`
- `cart_items`
- `orders`
- `order_items`
- `wallet_transactions`
- `library_items`
- `wishlist_items`
- `reviews`

Chat:

- `conversations`
- `messages`
- `message_attachments`
- `message_user_states`

Community:

- `guides`
- `guide_votes`
- `guide_favorites`
- `guide_comments`
- `discussion_topics`
- `discussion_replies`
- `community_posts`
- `community_post_likes`
- `community_comments`
- `community_comment_likes`
- `content_reports`

The complete definitions, constraints, seed strategy and ER-style relationship list are in `database/SCHEMA.md`. Avoid adding tables ad hoc in later phases without updating that document and `kanta.sql` together.

## Database responsibility split

The database enforces row-local values, foreign-key integrity, unique relationships, ownership before review, canonical social pairs and report target integrity. PHP transactions must enforce rules that span changing data: no owned game in a cart, accepted/unblocked friendship before chat, paid games only in weekend discounts, the 100-friend maximum and atomic checkout. This split keeps the schema strict without hiding application behavior in triggers.

## Key invariants

- A user cannot own the same game twice.
- A cart cannot contain an already owned game.
- A wallet-changing purchase is atomic.
- An order item stores the paid price and discount snapshot.
- Only owners can review, with one active review per user/game.
- Only friends can chat, and blocking overrides friendship.
- A generated sale is stable for its Friday-to-Monday period.
- Private content is never exposed only because a link or numeric ID is known.

## Security boundaries

- Authentication and authorization are server-side.
- Client prices, roles, filenames and ownership claims are never trusted.
- CSRF protects every state change.
- Output encoding is the default.
- The limited guide formatter uses an allowlist and rejects raw HTML.
- Uploaded public images are validated and renamed.
- Chat attachments are stored privately and served only after authorization.
- Admin access is checked per request, not merely hidden in navigation.

## Maintainability

The project avoids a single global functions file growing without limit. Helpers will be grouped by responsibility, for example authentication, validation, uploads, pricing, pagination and flash messages. Each phase should reuse these modules instead of duplicating logic.
