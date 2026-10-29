# Testing strategy

## Per-change checks

- Run `D:\xampp\php\php.exe -l <file>` for every changed PHP file.
- Validate affected HTML in the browser and inspect console/network errors.
- Test both authorized and unauthorized requests.
- Test expected validation failures, not only successful submissions.
- Record actual evidence in the current phase handoff.

## Database checks

- Import `kanta.sql` into an empty `skadi` database.
- Confirm foreign keys and unique constraints reject invalid relationships.
- Verify transaction rollback for insufficient balance and forced checkout failure.
- Verify duplicate ownership, duplicate votes and duplicate friendships are prevented.
- Inspect query plans for catalog search and major paginated lists when data volume is sufficient.

## Security scenarios

- SQL injection strings remain data, not SQL.
- Stored and reflected XSS payloads are escaped.
- CSRF-free POST requests are rejected.
- Users cannot access another user's private data by changing an ID.
- Users cannot call admin actions directly.
- Uploads reject executable/disallowed content, oversized files and mismatched MIME types.
- Chat downloads require a conversation participant.
- Blocked users cannot message or send friend requests.
- Server ignores manipulated browser prices and roles.

## Final integration runner

Run the ignored reusable Phase 15 suite from the repository root:

```powershell
& .\storage\cache\phase15-tests\run-phase15-tests.ps1
```

The runner creates a fresh isolated MariaDB data directory, imports `kanta.sql`, runs database and HTTP negative cases, captures mobile/desktop headless-Chrome screenshots, lints every PHP file, syntax-checks project JavaScript, scans all form handlers for method/CSRF guards, confirms admin code has no private-chat references, and runs `git diff --check`. It shuts down its temporary servers in `finally` blocks. The normal XAMPP data directory is not modified.

Retained focused regressions remain available under `storage/cache/phase11-tests/`, `phase13-tests/` and `phase14-tests/`. Their runners should be used when chat/attachment, community/report or administration boundaries change.

## Presentation smoke test

1. Fresh database import.
2. Register a user and confirm EUR 100.00 balance.
3. Browse/filter the store and view a game.
4. Add games to cart and complete a purchase.
5. Show order, wallet and library updates.
6. Add a review and rating.
7. Demonstrate a weekend discount or admin regeneration.
8. Use a second account for friend request and chat.
9. Demonstrate a guide or discussion.
10. Show admin CRUD and wallet adjustment.
11. Demonstrate theme switching and mobile layout.

The final phase must convert this list into a short repeatable presentation script.

The completed presentation order and concise explanation/Q&A are in `docs/PRESENTATION.md`.
The ignored local Phase 16 runner is retained at
`storage/cache/phase16-tests/run-phase16-tests.ps1` for coordinator review. It creates a fresh
isolated MariaDB data directory, imports `kanta.sql`, exercises the complete presentation workflow,
runs authenticated user/admin headless-browser checks, lints PHP, checks project JavaScript and
audits tracked runtime/secret/image hygiene. It does not modify the normal XAMPP data directory.
