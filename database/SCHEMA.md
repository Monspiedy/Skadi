# Skadi database schema

## Conventions and boundaries

The schema targets MariaDB 10.4, uses InnoDB and `utf8mb4`, and stores application timestamps in UTC. Money uses `DECIMAL`; PHP must never calculate prices with floating-point values. Public identifiers and account credentials are separate: `login_name` is private and immutable, while `skadi_id` and `display_name` are public.

Foreign keys protect row relationships, unique keys prevent duplicate relations, and checks constrain values that MariaDB 10.4 can validate locally. Rules that require looking at several tables, such as “the cart cannot contain an owned game” and “only accepted, unblocked friends may chat,” remain explicit application transaction checks.

Friendships, blocks and conversations store each user pair in canonical order: the smaller user ID is `user_low_id` and the larger is `user_high_id`. A unique key plus `user_low_id < user_high_id` prevents the reverse pair from being inserted silently. A block is pair-wide and records which participant created it; a second reverse block is unnecessary because either direction already disables contact.

Deleting a chat message for everyone means a real `DELETE` from `messages`. Cascades remove its attachment and per-user state, so no tombstone row or text is shown. Admin views deliberately omit all message and attachment tables.

## Identity and profiles

- `roles`: Stable `user` and `admin` role names.
- `users`: Authentication identity, public Skadi ID/display name, role, status, wallet balance and password-change flag. Login, email and Skadi ID are unique.
- `user_profiles`: One-to-one privacy, theme, avatar, biography, friend-request and presence preferences.

## Store, sales and media

- `games`: Catalog facts, system requirements, publication status and decimal base price.
- `genres`: Reusable genre lookup values.
- `game_genres`: Many-to-many game/genre assignments.
- `game_media`: Cover and screenshot metadata. Position zero is reserved for the single cover; file validation happens before insert.
- `featured_games`: Administrator-selected games. Unique positions limited to 1-5 enforce the five-item maximum.
- `weekly_sales`: Auditable Friday-to-Monday sale generations. A regeneration creates a new generation and supersedes the old one.
- `weekly_discounts`: One valid 10-75 percent, five-point-step discount per paid game and sale generation. Application code excludes free games.

## Cart, wallet, orders and ownership

- `cart_items`: One row per user/game plus the price observed when added, used to show price-change notices.
- `orders`: Order header and immutable monetary totals/status.
- `order_items`: Purchased games with title, base price, discount and paid-price snapshots.
- `wallet_transactions`: Append-oriented ledger for initial credit, purchase debit and administrator adjustment, including resulting balance and reason.
- `library_items`: Unique ownership record with hide and permanent-removal timestamps. A removed item stays for history and can be reactivated by later policy.
- `wishlist_items`: Unique user/game wishlist relation.
- `reviews`: One rating/review per owned game. Its composite foreign key to `library_items` enforces prior ownership.

Checkout must be one database transaction: lock the user, cart and current price inputs; recheck ownership and balance; create order/items; debit the wallet; append the wallet transaction; add library rows; and clear purchased cart rows. The server calculates every price.

## Friends, blocks and direct chat

- `friendships`: One canonical pair with request actor, lifecycle status and response timestamps.
- `user_blocks`: One canonical blocked pair and the participant who initiated it.
- `conversations`: One canonical one-to-one conversation per pair.
- `messages`: Message text, sender and edit timestamp. The sender must be a conversation participant and currently allowed to message; PHP enforces this in the send transaction.
- `message_attachments`: At most one private attachment per message, including generated storage name, MIME, extension and size metadata.
- `message_user_states`: Per-participant read time and delete-for-self (`hidden_at`) state.

## Guides and discussions

- `guides`: Game guide source, category, thumbnail, author, publication and moderation state.
- `guide_votes`: One helpful/not-helpful vote per user/guide.
- `guide_favorites`: One favorite relation per user/guide.
- `guide_comments`: Comments with editable and moderated lifecycle timestamps.
- `discussion_categories`: The approved General, Help, Bug, Trading and Off-topic lookup values.
- `discussion_topics`: Game forum topics with pin, lock and moderation controls.
- `discussion_replies`: Topic replies with edit/moderation lifecycle.

## Community and moderation

- `community_posts`: Game-related text posts with an optional validated image and moderation state.
- `community_post_likes`: One like per user/post.
- `community_comments`: Comments on community posts.
- `community_comment_likes`: One like per user/comment.
- `content_reports`: A report targets exactly one supported public-content row. Generated target columns prevent one user from reporting the same item twice while real foreign keys preserve integrity.

## Admin-safe views

- `admin_user_overview`: Account administration fields without login names or password hashes.
- `admin_catalog_overview`: Catalog ownership and active-review aggregates.
- `admin_content_report_queue`: Report workflow metadata without private messages.

## ER-style relationship list

```text
roles 1 --- * users 1 --- 1 user_profiles
users * --- * games through cart_items
users * --- * games through wishlist_items
users * --- * games through library_items
users 1 --- * orders 1 --- * order_items * --- 1 games
users 1 --- * wallet_transactions; orders 0..1 --- 0..1 wallet_transactions
library_items (user_id, game_id) 1 --- 0..1 reviews

games * --- * genres through game_genres
games 1 --- * game_media
games 1 --- 0..1 featured_games
weekly_sales 1 --- * weekly_discounts * --- 1 games

users * --- * users through friendships (canonical pair)
users * --- * users through user_blocks (canonical pair)
users * --- * users through conversations (canonical pair)
conversations 1 --- * messages 1 --- 0..1 message_attachments
messages 1 --- * message_user_states * --- 1 users

games 1 --- * guides 1 --- * guide_comments
guides * --- * users through guide_votes
guides * --- * users through guide_favorites
games 1 --- * discussion_topics 1 --- * discussion_replies
discussion_categories 1 --- * discussion_topics
games 1 --- * community_posts 1 --- * community_comments
community_posts * --- * users through community_post_likes
community_comments * --- * users through community_comment_likes
users 1 --- * content_reports --- exactly one reportable public-content row
```

## Deterministic seed data

The dump creates two local accounts, twelve catalog games, genres and mappings, five featured positions, one scheduled sample weekend sale, discussion categories and opening wallet ledger entries. Both accounts use the same temporary local-only password documented in the Phase 01 handoff and have `must_change_password = 1`. Seed emails use the reserved `.invalid` domain.

Media paths are not seeded because Phase 06 owns original artwork and validated upload storage. Commerce, friendships, messages and community content start empty so later phase acceptance tests can create their own evidence cleanly.
