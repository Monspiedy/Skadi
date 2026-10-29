# Skadi presentation script

Target length: 8-10 minutes. Use a fresh `kanta.sql` import and prepare two changed-password accounts before presenting. Keep the browser at `http://localhost/Skadi/` and have the XAMPP Control Panel available only if troubleshooting is needed.

## 1. Opening (30 seconds)

“Skadi is an educational PHP game store and player community. It uses demo money only. The interface, CSS placeholders and implementation are original; no promotional game artwork is committed.”

Show the home page, the responsive navigation and the Skadi/Dark/Light selector.

## 2. Store and purchase (2 minutes)

1. Open Store and demonstrate title search, one genre/price filter, a sort and pagination.
2. Open a paid game. Point out server-rendered facts, genres, current price and community links.
3. Add the game to Cart and check out with a prepared user.
4. Open Orders, Wallet and Library to show the same purchase in three related views.
5. Add a 1-10 owner review and show the updated game rating.

Say: “The browser submits game IDs, never a trusted price. Checkout locks and rechecks the user, cart, games, discounts, ownership and balance. One transaction writes the order, item snapshots, wallet debit, library ownership and cart cleanup, so partial purchases roll back.”

## 3. Social and community (2 minutes)

1. Show a public profile and explain the public/friends-only/private decision.
2. With two prepared accounts, show an accepted friendship and Chat. Send a short message and point out the Fetch polling/unread behavior.
3. Open a guide and show safe formatting, voting/favorites/comments.
4. Open Community Center and one categorized discussion or community post.

Say: “Chat is private and only current unblocked friends can use it. Attachments are stored outside public uploads and downloaded through an authorization endpoint. Community content is public, escaped and separate from chat.”

## 4. Administration (90 seconds)

1. Sign in as the changed-password administrator and open the dashboard.
2. Show Games/Genres and explain the many-to-many `game_genres` relation.
3. Show Users and one audited wallet adjustment; then show Commerce history.
4. Show Moderation/Reports and Weekend sale inspection.

Say: “Every admin page and action checks the role again. The final active administrator cannot be removed, a wallet cannot become negative, and private messages are not available in admin tools.”

## 5. Code and database explanation (2 minutes)

Open these files in this order:

1. `config/bootstrap.php` — every page loads one bootstrap for configuration, session and focused helpers.
2. `includes/header.php`, `includes/navigation.php`, `includes/footer.php` — shared page sections use PHP `require`/`include` instead of duplicated markup.
3. `includes/commerce.php` — prepared statements, integer-cent calculations, row locks and checkout transaction.
4. `assets/js/chat.js` or `assets/js/theme-selector.js` — visible vanilla-JavaScript behavior; the server remains the security boundary.
5. `includes/uploads.php` — MIME/extension/size/dimension validation and generated filenames.
6. `kanta.sql` — foreign keys, unique/check constraints, indexes and seed data.

Explain the main relations:

- `users` 1-to-1 `user_profiles`
- `games` many-to-many `genres` through `game_genres`
- `users` 1-to-many `orders`; `orders` 1-to-many `order_items`
- `users` many-to-many `games` through `library_items`, `wishlist_items` and `cart_items`
- canonical user pairs in `friendships`, `user_blocks` and `conversations`
- `conversations` 1-to-many `messages`; messages have optional private attachments and per-user state
- games/users connect to guides, topics, replies, posts, comments and reports

Chosen PHP technique: `DateTimeImmutable` with an optional clock parameter in the weekend-sale/pricing helpers. Explain: “Production calls use the real time. Tests can pass a Friday or Monday time without changing the computer clock. Because immutable dates return new values instead of changing existing ones, Helsinki and daylight-saving boundaries are easier to reason about.”

## 6. Security summary and close (45 seconds)

“Passwords use `password_hash()` and `password_verify()`. Login regenerates the session ID. Mutations require POST plus CSRF. Request values use PDO prepared statements, public text is escaped, guide formatting is allowlisted, uploads are content-validated and renamed, and prices/roles are never trusted from the browser.”

Finish with: “A fresh isolated MariaDB import, the complete presentation workflow, responsive authenticated browser checks, all PHP lint and JavaScript syntax checks passed. Normal XAMPP startup and HTTPS still need verification on the actual presentation/deployment computer.”

## Likely teacher questions

**Why use an intermediate table for game genres?**

One game has several genres and one genre belongs to several games. `game_genres` represents that many-to-many relation without duplicating genre text.

**Why are orders and order items separate?**

The order stores buyer and totals once. Each item stores the purchased game plus immutable title/price/discount snapshots, so later catalog changes do not rewrite history.

**How do you prevent SQL injection?**

Request values are bound through PDO prepared statements. SQL directions such as sort order come only from fixed application-owned allowlists.

**How do you prevent XSS?**

Plain user content is escaped with `htmlspecialchars()`. Guide source is escaped first, then only a small documented BBCode allowlist becomes HTML. Chat uses DOM `textContent`.

**Why both CSRF and authentication?**

Authentication identifies the signed-in user; CSRF proves the state-changing request came from a form/page issued to that session. Authorization then checks whether that user may act on the target.

**What happens if checkout fails halfway?**

The transaction rolls back the order, items, wallet, ledger, ownership and cart changes together.

**Why validate MIME type and extension?**

Either one alone can be forged. Skadi compares detected content with the allowed extension, checks size/dimensions and generates a new storage name.

**Can administrators read chat?**

No. Admin pages/services do not query the private message or attachment tables.

**Why polling instead of WebSockets?**

Three-second Fetch polling works on ordinary XAMPP/PHP hosting, stays understandable for the course and needs no separate server.

**What is not production-ready?**

Real payments, email verification/password reset, high-volume/load testing and target-host HTTPS/web-server configuration are outside scope. Seed accounts must be replaced/disabled before hosting.
