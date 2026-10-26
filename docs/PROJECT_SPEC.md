# Skadi project specification

## 1. Purpose

Skadi is a course demonstration project that combines an online game store, personal game library and player community. It is inspired by familiar store patterns but uses original branding and implementation. The application is educational and uses demo money only; it never accepts real payments.

The public interface and code are written in English so the repository can later be published as a portfolio project.

## 2. Required technology

- PHP 8.2 without a full-stack framework
- MariaDB 10.4 / MySQL-compatible SQL
- PDO prepared statements
- HTML5 and valid semantic markup
- CSS and locally stored Bootstrap
- Vanilla JavaScript and Fetch/AJAX
- XAMPP at `D:\xampp`
- Local URL `http://localhost/Skadi/`

## 3. Visual identity

Skadi uses an original ice and Nordic-inspired identity. It may reference the cold blue, cyan and glowing atmosphere associated with the idea of the Eye of Skadi, but it must not copy Valve/Dota artwork, icons or the Steam interface exactly.

Three selectable themes are required:

- `Skadi`: branded dark navy, ice blue and cyan accents
- `Dark`: neutral dark theme
- `Light`: accessible light theme

Guest theme selection is stored in `localStorage`. A signed-in user's selection is also stored in the database.

## 4. Visitors and authentication

Visitors can browse the catalog, game pages, public profiles and public community content. Purchasing, rating, posting, adding friends and chatting require an account.

Registration uses:

- unique immutable `login_name`, used only for sign-in
- unique email address without email verification
- password and password confirmation
- changeable public `display_name`, which does not need to be unique
- automatically generated immutable public Skadi ID such as `SKD-7F4M-29QX`

Passwords are hashed. Sessions are regenerated after login. The initial demo balance is EUR 100.00.

Roles:

- `user`
- `admin`

A moderator role is not required.

## 5. Profiles and privacy

Users can change their display name, email, password, avatar, biography, theme, friend-request preference and online-status preference. The private login name cannot be changed and is never shown publicly.

Profile visibility options:

- `public`
- `friends_only`
- `private`

The public display name, avatar and subtle Skadi ID remain visible so the account can be found. Library, reviews, activity and friends respect the selected privacy level.

## 6. Catalog and game pages

The store includes:

- featured games selected by an administrator, maximum five
- twelve recognizable demonstration games
- original/placeholder artwork rather than copyrighted promotional images committed to Git
- search by title
- genre and price filters
- sort by title, price, rating, newest and popularity
- pagination
- free and paid games
- base price and calculated current price
- genres through a many-to-many relation
- description, developer, publisher, release date and system requirements
- cover image and screenshot gallery
- average user score from 1 to 10
- links to the game's Community Hub, guides, discussions and reviews

The planned twelve demonstration games are:

1. Counter-Strike 2
2. Dota 2
3. Cyberpunk 2077
4. Baldur's Gate 3
5. Red Dead Redemption 2
6. The Witcher 3: Wild Hunt
7. Grand Theft Auto V
8. Elden Ring
9. HELLDIVERS 2
10. Rust
11. Stardew Valley
12. Terraria

Counter-Strike 2 and Dota 2 were explicitly selected by the user. The remaining titles provide recognizable free/paid games, genres and price points. Only factual seed data and original placeholder artwork are committed.

## 7. Weekly sale

A weekend sale runs in the `Europe/Helsinki` timezone from Friday 00:00 until Monday 00:00.

- Each paid game receives one random discount between 10% and 75%, in 5% steps.
- Free games are excluded.
- One shared discount set applies to all users.
- Discounts are persisted and never recalculated on page refresh.
- If no scheduler is available, the first request in a new sale period generates the missing sale rows safely.
- The storefront shows a JavaScript countdown.
- An administrator can regenerate the current sale for demonstration.

## 8. Cart, wallet and purchasing

- The cart is stored in the database and requires login.
- The cart always displays the current server price.
- If a price changes after an item was added, the user sees a clear notice.
- Final prices are calculated and fixed at checkout, not when an item enters the cart.
- A purchase transaction verifies ownership, prices and wallet balance; deducts the balance; creates an order and order items; writes a wallet transaction; and adds games to the library atomically.
- The wallet cannot be topped up by users.
- Administrators can add or subtract balance with a required reason.
- Users can view wallet and purchase history.
- Real payment processing is explicitly out of scope.

## 9. Library and wishlist

Users can:

- browse and search owned games
- hide and unhide games
- permanently remove a game without a refund
- add and remove wishlist items
- move from wishlist to cart

Permanent removal requires confirmation. Order history remains intact.

## 10. Reviews and ratings

- Only owners can review a game.
- Each user has at most one review and one 1-10 rating per game.
- The review and rating can be edited.
- The author can delete their review.
- Administrators can hide or delete any review.
- Game averages and review counts are calculated from active reviews.

## 11. Friends and presence

Users can search by partial display name or exact Skadi ID. They can send, accept, decline and cancel friend requests; remove friends; block users; and disable incoming friend requests.

- Maximum 100 accepted friends
- A blocked account cannot send requests, message the blocker or see private profile details
- Presence is based on recent activity: online, recently online or offline
- Users can hide their online status
- Friend lists respect profile privacy

## 12. Direct chat

Chat is one-to-one and available only to accepted friends. New data is retrieved with Fetch/AJAX polling, approximately every three seconds, so it works on XAMPP and ordinary PHP hosting without a WebSocket server.

Required behavior:

- conversation list and unread counters
- text and standard emoji
- edit own messages; edited messages show `edited`
- delete for self
- delete for everyone; the message disappears completely for both users, with no replacement text or tombstone in the UI
- blocking immediately prevents further messages
- safe attachment support for JPG, PNG, WebP, PDF and TXT, maximum 5 MB
- one attachment per message
- inline preview for images and authorized download for documents
- generated storage filenames, MIME/extension allowlists and non-executable private storage

The admin panel does not expose private message contents.

## 13. Player guides

Any registered user may create a guide for any game. Ownership can be displayed as an `Owner` badge but is not required to publish.

A guide includes title, summary, category, thumbnail and body. A small safe formatting toolbar supports headings, lists, emphasis, links and code through an allowlisted renderer; raw HTML is not accepted.

Users can mark guides helpful/not helpful, add them to favorites and comment. Guides can be sorted by score, popularity and publication date. Authors can edit/delete their work; admins can hide or delete it.

## 14. Community Center and discussions

The global Community Center aggregates:

- recent and popular discussions
- popular guides
- new screenshots and community posts
- game filters

Each game has a Community Hub with Overview, Discussions, Guides, Screenshots and Reviews tabs.

Discussions contain categorized topics (`General`, `Help`, `Bug`, `Trading`, `Off-topic`) and replies. Authors may edit their content. Admins can pin, lock, hide and delete topics or replies. Search, pagination and reporting are required.

Community posts are text posts with an optional safe image, assigned to a game. Other users can like and comment on them.

## 15. Administration

Only admins can enter `/admin/`. The panel provides:

- dashboard statistics
- CRUD for games, genres, covers and screenshots
- featured-game selection, maximum five
- user search, role/status management and account blocking
- wallet adjustments with reason and audit history
- order and wallet history inspection
- review, guide, discussion, report and community-content moderation
- current weekend sale inspection and regeneration

Admins never see passwords or password hashes and do not receive a private-chat reader.

## 16. JavaScript usage

JavaScript is used for visible course-demonstration features, including:

- theme selection and local persistence
- sale countdown
- asynchronous chat polling, sending, editing and deletion
- unread counters
- image previews
- confirmation dialogs
- guide formatting preview
- selected search/filter enhancements

Core catalog browsing remains usable without JavaScript where practical.

## 17. Upload rules

Avatar, cover, screenshot, community-image and chat-attachment uploads use separate allowlists and size limits. SVG, HTML, JavaScript, PHP, PHAR, PHTML, EXE and archives are forbidden. User filenames are metadata only; storage names are generated by the server.

Chat attachments are not directly public. An authorized download endpoint checks the requesting participant before returning the file.

## 18. Explicit non-goals

- real payments or balance top-ups by users
- game downloads or a desktop launcher
- achievements
- cloud saves
- voice/video calls
- group chats
- arbitrary executable/archive uploads
- email verification and password-reset email delivery
- copying Steam/Epic source code, trademarks or visual assets

## 19. Course deliverables

- commented and consistently styled source code
- shared sections implemented with PHP `include`/`require`
- JavaScript functionality
- varied database operations
- authentication and registration
- administration features
- `readme.txt`
- complete chronological `ai.txt`
- importable `kanta.sql`
- functional folder structure and server deployment
