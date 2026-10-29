# Skadi

Skadi is an English-language educational game store and player community built for a PHP course presentation. It combines a searchable catalog, demo-money commerce, personal libraries, profiles, friends, private chat, guides, discussions, community posts and administration in one original ice-themed application.

Skadi does not process real payments, distribute games or copy storefront source code or artwork. The repository contains original CSS placeholders instead of third-party promotional images.

## Features

- Twelve-game catalog with featured games, search, genre/price filters, safe sorting and pagination
- Server-authoritative weekend prices, persistent cart and transactional demo-wallet checkout
- Order and wallet history, library visibility/removal, wishlist and owner-only 1-10 reviews
- Public profiles, privacy controls, Skadi ID search, friend requests, presence and blocking
- One-to-one Fetch-polled chat with unread state and authorized private attachments
- Allowlisted-BBCode guides, votes, favorites and comments
- Community Center, per-game hubs, discussions, posts, likes, comments and reports
- Administrator dashboard, catalog/media CRUD, featured ordering, account controls, audited wallet adjustments, moderation, reports and weekend-sale tools
- Skadi, Dark and Light themes with responsive local Bootstrap styling

## Technology

- PHP 8.2 with a small procedural, modular architecture
- MariaDB 10.4 / MySQL-compatible SQL through PDO prepared statements
- HTML5, locally stored Bootstrap 5.3.8 and project CSS
- Vanilla JavaScript and Fetch/AJAX
- XAMPP for local Apache and MariaDB hosting

No Composer, npm, PHP framework or external service is required at runtime.

## Installation

1. Put the repository at `D:\xampp\htdocs\Skadi`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import `kanta.sql`. The dump creates and selects the `skadi` database and resets its Skadi tables, so back up any existing Skadi data first.
4. Copy `config/database.example.php` to `config/database.local.php` and enter the local MariaDB credentials. The local file is ignored by Git.
5. Open `http://localhost/Skadi/`. Use `http://localhost/Skadi/health.php?check=db` to verify PHP and database connectivity.

The deterministic local administrator is `skadi_admin` with temporary password `ChangeMe!2026`. The first login requires an immediate password change. These are demonstration credentials, not production credentials. See `readme.txt` for exact phpMyAdmin/command-line import steps, workflows, troubleshooting and deployment security notes.

## Architecture

Every page loads `config/bootstrap.php`, which starts the secure session and loads focused helpers. Page controllers perform authorization and reads, shared layout sections live in `includes/`, form mutations live in `actions/`, and JSON chat endpoints live in `api/`. Business rules are grouped by responsibility in small services such as commerce, pricing, social, chat, guides, community and administration.

The schema demonstrates one-to-one, one-to-many and many-to-many relations, foreign keys, unique/check constraints, joins, aggregates, filtering, pagination and transactions. Checkout and administrator wallet changes lock their inputs and write all related records atomically.

## Security highlights

- Password hashing and verification through PHP password APIs
- Session ID regeneration, inactivity timeout and database-refreshed roles/status
- POST and CSRF validation for mutations
- PDO prepared statements for request values and fixed allowlists for SQL sort fragments
- Escaped public output plus an allowlisted guide formatter
- MIME, extension, size and dimension validation with generated upload names
- Participant authorization for private chat downloads
- Server-calculated prices and transaction-protected wallet changes
- Per-request administrator checks and no administrator private-message reader

## Project status

Phase 16 release documentation and the complete presentation smoke test passed independent coordinator verification. A clean `kanta.sql` import produced 39 tables/views; the final isolated workflow and authenticated browser checks passed on PHP 8.2.12 and MariaDB 10.4.32. The normal XAMPP control-panel MariaDB data directory was not repaired or verified in that test environment, and HTTPS/deployment configuration still requires verification on the target host.

This commit is the independently verified presentation release and is approved for the `v1.0-presentation` tag. It has not been deployed.

## Documentation

- `readme.txt` — complete XAMPP installation and user/admin guide
- `docs/PROJECT_SPEC.md` — approved product scope
- `docs/ARCHITECTURE.md` — request flow and module responsibilities
- `database/SCHEMA.md` — schema and relationship explanation
- `docs/TESTING.md` — verification strategy
- `docs/PRESENTATION.md` — short demonstration script and likely teacher questions

## License

Skadi is released under the [MIT License](LICENSE). Local Bootstrap files retain their upstream MIT license in `assets/bootstrap/LICENSE`.
