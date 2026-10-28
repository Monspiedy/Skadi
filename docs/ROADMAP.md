# Skadi roadmap

## Dates

- Primary presentation: 2026-11-12
- Backup presentation: 2026-11-19
- Local timezone: Europe/Helsinki

The primary date is aggressive. Core course requirements must remain more important than optional visual polish.

## Phase sequence

| Phase | Target | Outcome | Status |
|---:|---|---|---|
| 00 | Oct 26 | Repository foundation, specification and phase prompts | Complete |
| 01 | Oct 27 | Importable schema and seed strategy | Complete |
| 02 | Oct 28 | PHP bootstrap, shared includes and error handling | Complete |
| 03 | Oct 28-29 | Registration, login, sessions and security helpers | Complete |
| 04 | Oct 29 | Themes, shared Bootstrap layout and navigation | Complete |
| 05 | Oct 30 | Catalog, search, filters, pagination and game pages | Complete |
| 06 | Oct 31 | Upload pipeline, screenshots and featured games | Complete |
| 07 | Nov 1 | Cart, wallet, transactional checkout and orders | Complete |
| 08 | Nov 2 | Library, wishlist, reviews and ratings | Complete |
| 09 | Nov 3 | Weekend discounts and countdown | Complete |
| 10 | Nov 4 | Profiles, privacy, Skadi ID, friends and blocking | Complete |
| 11 | Nov 5-6 | Direct chat, edit/delete, unread state and attachments | Not started |
| 12 | Nov 7 | Player guides, voting, favorites and comments | Not started |
| 13 | Nov 8 | Community Center, discussions and community posts | Not started |
| 14 | Nov 9 | Admin panel and moderation | Not started |
| 15 | Nov 10 | Integration, security review and fixes | Not started |
| 16 | Nov 11 | Final seed data, documentation and presentation rehearsal | Not started |

## Priority gates

### Must be complete

Phases 00-10 and the essential admin portion of phase 14 satisfy the core product and course requirements.

### Strong grade / portfolio scope

Phases 11-13 add the social and community features.

### If schedule slips

Reduce animations, community sorting options and nonessential dashboard charts first. Do not remove authentication, database transactions, administration, `ai.txt`, `readme.txt`, `kanta.sql`, validation or security controls.

## Version tags

Planned tags after verified milestones:

- `v0.1-foundation`
- `v0.2-auth`
- `v0.3-store`
- `v0.4-commerce`
- `v0.5-social`
- `v0.6-community`
- `v0.9-rc`
- `v1.0-presentation`
