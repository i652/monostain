# Monostain Monopoly Release Checklist

## Infrastructure
- [ ] `docker-compose up --build` starts `nginx`, `app`, `postgres`.
- [ ] `.env` contains production secrets (`JWT_SECRET`, DB credentials, cookie settings).
- [ ] `bin/migrate.php` applies schema successfully on clean database.

## Security and RBAC
- [ ] Newly registered user gets role `player`.
- [ ] `player` cannot create/edit/delete posts/pages.
- [ ] `author`/`admin` keep CMS rights.
- [ ] `player`/`author`/`admin` can access game pages.

## Core game flows
- [ ] Create game from `/games/new`.
- [ ] Invite by link and by nickname.
- [ ] Join game by invite token.
- [ ] Real-time updates work through long-poll endpoint.
- [ ] Chat messages (group/private) persist in DB.
- [ ] Commands `roll`, `pay`, `end_turn` create events.

## UI and tabs
- [ ] Tabs work: Board, Ledger, Timeline, Stats, Trade, Rules, History.
- [ ] Player profile page updates nickname/email/password.
- [ ] Top navigation contains New game, Invites, Games.
- [ ] Role-aware navigation shows Profile and/or New post correctly.

## Admin board templates
- [ ] `/panel/game-boards` accessible only for admin.
- [ ] Admin can create board templates.

## Testing
- [ ] PHP syntax checks pass for all changed files.
- [ ] Manual multiplayer scenario: 2-8 players.
- [ ] Browser sanity: Chrome, Firefox, Safari + mobile viewport.
