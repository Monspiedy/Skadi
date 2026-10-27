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
