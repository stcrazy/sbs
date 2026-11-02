# Super Bowl Squares · thetrenhailes.com/sbs

A private, phone-based football pool for Hostinger PHP/MySQL hosting, supporting multiple independently managed games. Players open a game link, enter the phone number on that game’s roster, and stay signed in on that device. They need no ChatGPT account, email address, password, or automated verification text. The organizer has a separate password-protected dashboard.

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

In **Organizer → Players & ledger**, enter a player's name and phone number; email is optional. U.S. numbers receive the +1 country code automatically. Each phone belongs to one player. In the collapsed Invite a player form, choose starting credits (default 5). These are granted once per player per game; replacing a link never grants them again. For existing players who already have credits, set the invitation grant to 0 if no extra credits are needed. Additional credits can be adjusted manually. Unused picking credits cost nothing. Amount owed is picked squares × square price minus payments received, floored at zero. Overpayments remain visible for a manual refund; square erasure never deletes payment receipts. Recording payment defaults to 0 extra credits.

Use **Share link** on a player row to open the device share sheet, or Text/Copy options where native sharing is unavailable. The link includes the invited phone number and signs into that player automatically; sharing does not issue credits.

Players sign in with their invited phone number only: no password, OTP, or private token. Anyone who knows a roster phone number can sign in as that player. Organizer access remains password-protected. Phone formats normalize to E.164; ten-digit U.S. numbers receive +1. A number must be on the selected game’s roster. Each game has an independent session. Changing a player’s phone revokes their existing sessions for that game. Player sign-in exits organizer access on that device; use **Player sign-in / Switch player** in the header.

## Multiple games and direct links

Open `/sbs/` to choose among games signed into on this device. Organizers see every game; players see only games for which this browser has a valid session. Open another game link to add a game to the player's selection screen. The **Games** link on each board returns to the selector.

The organizer can go directly to `/sbs/?admin=1` to sign in, even while a player session is active. From the selector, use **Create game** and choose its display name, year, and optional date. Codes are allocated sequentially within that year: `2026_01`, `2026_02`, and so on. The display name may be anything the organizer chooses. Rename it under that game's **Organizer → Pool setup & logos**; its code and existing links remain unchanged.

Examples:

```text
Game selection: https://thetrenhailes.com/sbs/
Organizer login: https://thetrenhailes.com/sbs/?admin=1
Game board:     https://thetrenhailes.com/sbs/?game=2026_01
Player invite:  https://thetrenhailes.com/sbs/?game=2026_01&player=1
```

Game links select a board; the invited phone number selects the player. Signing into one game does not sign into another automatically. Each game has a separate session cookie, allowing a player to switch among games already signed into without mixing balances.

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

Links with player=1 always open the player sign-in screen, even if this browser is already signed in as organizer. A successful player sign-in revokes the current organizer session on that device. Players have no credit-account selector and can spend only their own credits or personalize their own squares. The server rejects another player ID in either request. Organizer access is labeled explicitly and retains assignment controls.

Board artwork occupies the top 80% of each picked square; the chosen name or nickname appears in the bottom 20%, including printed boards. The compact payout strip below the board always uses the full 100-square pool, independent of picked squares or received payments. The last payout/result is Final and uses the final score including overtime. Existing house rules are organizer-editable and are not overwritten by interface updates.

## Credit requests and Home Screen alerts

Use **Issue credits** in any player row to add a chosen amount at any time; it records a zero-dollar ledger entry. Players use **My credits → Request more credits**. One pending request per player per game is allowed. The organizer sees a request count across active games and reviews each under **Organizer → Credit requests**. Approval grants once in the same locked transaction; decline grants nothing. Player responses include only their own request history. Archived games are read-only.

The site includes a Home Screen manifest, icons, and a service worker. In iPhone Safari: Share → Add to Home Screen → Add. Open the installed icon, sign in as organizer, then choose **Enable notifications** and Allow. Requires iOS 16.4+. A test notification reports whether the push service accepted delivery; actual device receipt must be checked on the iPhone. Push endpoints and VAPID keys are stored in the private database, never GitHub. Only organizer sessions may subscribe. At most 10 devices are supported. Notifications use the standard Web Push library shipped in vendor/, requiring PHP OpenSSL, cURL, and Mbstring. If Hostinger disables an extension, enable it under PHP Configuration. No Apple developer subscription or Twilio is needed. Push payloads contain a generic request alert and game link, without player names or phone numbers. Notifications are best-effort; pending requests remain in the ledger view if a push fails. Devices can be disabled from the organizer alert panel. No authenticated pages or API responses are cached for offline access. The Home Screen badge updates on foreground refresh and incoming push; resolved counts on other closed devices refresh when those devices next open.

New square picks require a name or nickname and one image or signature. The picker shows available credits in a white box and enables Confirm (1 credit) only after both steps are complete. Editing an owned square costs zero additional credits. Stored square labels are nicknames and stay in the bottom name strip; images/signatures occupy the top portion. Existing name-only squares remain displayed, but need artwork if edited.

PHP dependencies are locked in composer.lock and vendor/ is deployed with the repository. To update them intentionally, use composer install --no-dev --prefer-dist --no-scripts --no-plugins with the documented PHP extensions enabled, then run the project checks. Protect vendor/ and Composer files with the committed .htaccess.

## Compact player ledger

**Invite a player** is collapsed until opened. Enter name, phone, and starting credits (default 5); optionally enable **Record a payment now**, enter the amount and a payment note. The server saves the roster entry, credits, and optional receipt in one pool transaction. Starting credits are marked granted so preparing older invitations cannot issue them twice. Existing players keep their current balances.

Each row has **Record payment**, **Issue credits**, and **Share link**. Recording a payment adds a receipt with zero credit adjustment. **Balance** is red when picked squares exceed payments and green at zero or with an overpayment credit; unused picking credits do not create a charge. Payment forms remain hidden until selected.

Share link uses the device share sheet when supported, otherwise offers Text link and Copy link. It does not issue more credits or record payments. Links contain the invited phone number in a phone query parameter and automatically sign into that player’s account for the selected game. The application removes that parameter from the address bar before sending the phone in the sign-in request body. Anyone with the link can open that player account, just as anyone knowing an invited phone number can sign in manually. Share only with the intended player. Opening a player link on an organizer device exits that device’s organizer session after successful player sign-in.

Player ledger rows now show only Name, Squares picked, Credits, and Balance. Tap a row or activate its name button to expand phone editing, payment totals, Issue credits, Record payment, and Share link. One player opens at a time. Pool totals and transaction history/export are collapsed behind details controls. The four-column table is tested at phone width without horizontal scrolling.

Unassigned row and column cells use staggered CSS scoreboard reels. They are decorative previews and never determine the real number draw. Assigning numbers briefly shuffles each reel before settling on the saved digit. Pause rolling shows static question marks. Reduced-motion preferences and printed boards use static placeholders or saved digits; screen-reader labels remain stable while previews animate.
