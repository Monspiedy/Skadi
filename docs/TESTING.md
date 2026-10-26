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
