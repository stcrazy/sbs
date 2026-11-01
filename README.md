# Super Bowl Squares · thetrenhailes.com/sbs

A private, phone-based football pool for Hostinger PHP/MySQL hosting, supporting multiple independently managed games. Players open an individual game invitation that the organizer texts manually, enter their phone number, and stay signed in on that device. They need no ChatGPT account, email address, password, or automated verification text. The organizer has a separate password-protected dashboard.

## Deployment status

The standalone build has been tested locally. Hostinger deployment, MySQL credentials, and organizer setup still require configuration. The previous ChatGPT-hosted pool remains separate; its database was checked during preparation and contained no players, purchases, ledger entries, or uploaded images. Do not treat pushing this repository as confirmation that the domain is live.

## Hostinger setup

Requirements: PHP 8.2 or newer, MySQL with InnoDB, PDO MySQL, Fileinfo, HTTPS, and Apache/LiteSpeed `.htaccess` support. Node.js is needed only to rebuild the interface on a development machine, not to run this application on Hostinger. Built `index.html` and `assets/` are committed with the PHP API.

1. In hPanel, open the existing **thetrenhailes.com** website and its **Git** section. Connect `https://github.com/stcrazy/sbs.git` and select branch **codex/hostinger-pool**. In the deployment settings, set **Root directory** to **public_html/sbs**. The default `public_html` publishes the pool at the main website's root and is not the target for this app. Keep the main website's existing files and deployment unchanged.
2. Create a dedicated MySQL database and user for the pool in hPanel. Record its database name, host, username, and password privately.
3. Copy the contents of `scripts/config.example.php` into a file named `.sbs-config.php` **outside `public_html`**, one level above it. Typical placement:

   ```text
   domains/thetrenhailes.com/.sbs-config.php
   domains/thetrenhailes.com/public_html/sbs/index.html
   domains/thetrenhailes.com/public_html/sbs/api/index.php
   ```

   Set `site_url` to `https://thetrenhailes.com/sbs/` and fill in the database connection. Set `setup_key` to a unique random value of at least 32 characters. A password manager can generate it. Do not put actual database credentials, setup keys, passwords, or invitation links in GitHub or chat. If the host requires another private configuration location, set the PHP environment variable `SBS_CONFIG_PATH` to its absolute path.
4. Visit `https://thetrenhailes.com/sbs/#setup=YOUR_SETUP_KEY` yourself. Choose an organizer password of at least 12 characters. The app creates its own database tables and initial pool. Setup works only once; after completing it, remove `setup_key` from the private configuration file.
5. Open **Organizer → Pool setup & logos**. Set the actual square price (the initial $25 is a placeholder), team names, logos, Venmo username if wanted, quarter percentages, and rules.
6. Verify login, an invitation, a test purchase and release, uploads, and print preview on the live domain before inviting real players. Local checks used SQLite; the actual Hostinger MySQL connection and `.htaccess` behavior still need live verification.

The private configuration file and database are outside the Git update cycle. Deploying new interface/API files does not overwrite ledger records, square ownership, player sessions, or images. Back up the MySQL database regularly; it contains pool records and uploaded images.

If `/sbs/` returns 403 or 404 after a successful deployment, check the Git deployment's **Root directory** first. If the pool's HTML appears at the main homepage instead, the repository was published to `public_html`; reconnect the repository with `public_html/sbs` as its target. A further GitHub push or manual redeploy to the wrong target will not correct that path. Once the target and auto-deployment are configured, pushes to the selected branch trigger deployment automatically.

## Player invitations and texting

In **Organizer → Players & ledger**, enter a player's name and phone number; email is optional. U.S. numbers receive the +1 country code automatically. Each phone belongs to one player. When creating an invitation, choose picking credits (default 5). These are granted once per player per game; replacing a link never grants them again. For existing players who already have credits, set the invitation grant to 0 if no extra credits are needed. Additional credits can be adjusted manually. Unused picking credits cost nothing. Amount owed is picked squares × square price minus payments received, floored at zero. Overpayments remain visible for a manual refund; square erasure never deletes payment receipts. Recording payment defaults to 0 extra credits.

Click **Create invite**, then **Text invitation** on your iPhone, or **Copy invite** and paste it into Messages. Review the number and message and tap Send yourself. Private links expire after 30 days for new sign-ins. A successful sign-in lasts up to 14 days on that device. Keep invitation links private: possession of the link plus the matching phone number grants access. This is not carrier-based phone verification.

Creating or replacing an invitation revokes that player's previous links and sessions for the selected game. Changing a player's phone number also revokes their access to that game; create and text a new invitation afterward. Invitation tokens and session secrets are stored only as hashes in the database. New links use `?game=2026_01&login=PRIVATE_KEY`. The interface removes `login` from the address bar after capturing it and sends no referrer header. Query links can still appear in web-server logs or browser history, so keep them private. Older `#invite=...` links remain supported for the original game.

## Multiple games and direct links

Open `/sbs/` to choose among games signed into on this device. Organizers see every game; players see only games for which this browser has a valid session. Open another private invitation to add a game to the player's selection screen. The **Games** link on each board returns to the selector.

The organizer can go directly to `/sbs/?admin=1` to sign in, even while a player session is active. From the selector, use **Create game** and choose its display name, year, and optional date. Codes are allocated sequentially within that year: `2026_01`, `2026_02`, and so on. The display name may be anything the organizer chooses. Rename it under that game's **Organizer → Pool setup & logos**; its code and existing links remain unchanged.

Examples:

```text
Game selection: https://thetrenhailes.com/sbs/
Organizer login: https://thetrenhailes.com/sbs/?admin=1
Game board:     https://thetrenhailes.com/sbs/?game=2026_01
Player invite:  https://thetrenhailes.com/sbs/?game=2026_01&login=PRIVATE_KEY
```

`PRIVATE_KEY` is a generated random invitation secret, not a phone number. The player still enters their invited phone number. A link for one game cannot authenticate to another game, even if the same phone number appears in both rosters. Each game has a separate session cookie, allowing a player to switch among games already signed into without mixing balances.

Each game has its own roster, ledger, credits, square owners/artwork, team and event logos, rules, price, payout percentages, notices, row/column draw, scores, winners, and images. Creating a game may copy contact details from another game, but never payments, credit balances, squares, invitations, scores, or uploaded artwork. Fresh invitations must be created for the new game.

In **Invite a player**, paste a name and phone together into **Paste contact details**, then click **Fill player details** and review before adding. You may also import a shared `.vcf` contact card under **Or import a shared contact card**; choose one number if the contact has several. Files are parsed in the browser; only the reviewed name/number are saved when you add the player. iPhone sharing requires saving the card to Files and uploading it; direct iOS share-sheet delivery to the website is not implemented.

Use **Archive** on a game card to make its board and ledger read-only. Archived games can still be viewed and printed. **Restore** reopens editing. The selector hides archived games until **Show archived** is checked; no records are deleted.

The multi-game migration copies the original standalone pool into `2026_01`, preserving its records, images, and valid player sessions. Legacy tables remain intact. This migration is transactional and runs only once. Back up the database before updating an existing production installation.

In **Organizer → Text players**, write one message, select recipients, then tap each individual Text button. Each link contains one recipient. The site does not automatically send, charge for, or track delivery of texts. Message drafts are retained in that browser tab. Safari/iPhone prefilled-body support requires an actual phone check; Copy Message is the fallback.

## Board, game, and printing

One credit buys one square. Payment receipts and credits are recorded separately with a reason. A purchase records its owner and debit in the same transaction. Competing requests cannot both own the same square. Players can personalize their square before numbers lock; the organizer can release a square and return its credit.

Once all 100 squares are filled, enter your drawn digits or randomize each axis. Each axis must contain 0–9 once. Locking numbers is permanent and prevents player ownership/artwork changes. Before any scores are entered, the organizer may erase a square (returning its credit and reducing the owner’s amount owed), then assign that square to another credited player. Drawn digits stay unchanged. Refill erased squares before scoring. Once scores begin, square ownership is frozen. Enter cumulative quarter scores in order; winning intersections use each score's last digit. Payouts are marked paid manually. State any overtime rule in the house rules.

Print Board targets landscape US letter with all three logos, the board, rules, and quarter results. Inspect the native print preview, disable browser headers/footers, and adjust scaling if your printer requires it. Actual printer behavior has not been verified. House rules are limited to 1,200 characters.

## Updating the repository

With Node.js 22.13+ installed:

```sh
npm ci
npm run typecheck
npm test
php tests/domain.php
npm run build
```

Commit both authored changes and the updated `index.html` and `assets/` files, then push the configured deployment branch. Hostinger can pull it through its normal Git deployment workflow. The repository must be installed at `/sbs`, because the client, cookies, and rewrite rules use that path.

For a local API preview, create a private, ignored config with `site_url` `http://127.0.0.1:8091/sbs/` and a SQLite DSN. Set `SBS_CONFIG_PATH`, then run:

```sh
php -S 127.0.0.1:8091 scripts/dev-router.php
```

`tests/http-standalone.mjs` and `tests/http-multigame.mjs` use only that loopback endpoint and write synthetic data. Their expected test setup key/password are deliberately local-only and are never production defaults. The multi-game checks cover stable codes, copied contacts, game-specific links and cookies, independent balances and squares, image separation, concurrent purchases, renaming, archive/restore, scoped revocation, and logout. `php tests/migration.php` requires PDO SQLite and verifies preservation of an existing single-game installation using a disposable fixture under `.local/`.

## Access and data

The server authorizes each request. Player responses include display names, board state, game progress, notices, and only that player's ledger activity. Other players' phone numbers, optional emails, payment activity, and organizer controls are not returned. Login attempts are limited by IP. Production session cookies are Secure, HttpOnly, and SameSite=Lax. Writes require the configured same origin. MySQL transactions lock the pool record before applying purchases or ledger changes.

The `api/domain.php` implementation is authoritative for the standalone server; the original TypeScript model retained under `src/` supplies shared UI types/helpers. No ChatGPT sign-in endpoint or Cloudflare binding is required by this deployment.
