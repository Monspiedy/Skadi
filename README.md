# Skadi

Skadi is an educational PHP game store and community platform inspired by the broad structure of Steam and Epic Games Store, with an original ice-themed visual identity.

The planned application includes a game catalog, demo wallet and cart, purchases, library, wishlist, ratings, profiles, friends, direct chat, player guides, discussions, community posts and an administration panel. It does not process real payments.

## Current status

The database foundation, reusable PHP bootstrap and authentication phase are implemented. Registration creates the account, profile and auditable EUR 100.00 wallet credit atomically. Login, forced first-login password change, protected access and CSRF-protected logout use the shared session helpers.

See:

- `docs/PROJECT_SPEC.md` for approved functionality.
- `docs/ROADMAP.md` for the delivery sequence.
- `docs/ARCHITECTURE.md` for the planned technical structure.
- `docs/TESTING.md` for the verification strategy.

## Planned local environment

- XAMPP in `D:\xampp`
- PHP 8.2
- MariaDB 10.4 / MySQL-compatible SQL
- Project URL: `http://localhost/Skadi/`
- Database name: `skadi`

Full installation and usage instructions will be maintained in `readme.txt` before the presentation release.

## License

Skadi is released under the [MIT License](LICENSE).
