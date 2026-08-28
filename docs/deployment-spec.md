# Deployment spec (draft — not decided, revisit later)

Status: **proposal, not adopted.** Current deploy mechanism for the Aug 29, 2026 draft remains `git pull` on the server, per `docs/modernization-spec.md` §3/§7/§9. This doc captures a CI/CD alternative discussed 2026-08-04, to be evaluated later — not before the go/no-go checkpoint (~Aug 24-25).

## Manual DDL step: `autodraft_priority` (docs/auto-draft-spec.md §6/§10)

The repo has no migration system, and `new/api/tests/schema.sql` is explicitly
not one — it's only ever applied wholesale to the local PHPUnit test DB. The
`autodraft_priority` table added for the auto-draft redesign **must be
created by hand** on staging and production before cutover:

```sql
CREATE TABLE autodraft_priority (
  id        INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pos       VARCHAR(2)  NOT NULL,
  rank      INT         NOT NULL,
  playerid  INT NULL,
  firstname VARCHAR(25) NULL,
  lastname  VARCHAR(25) NOT NULL,
  UNIQUE KEY uq_pos_rank (pos, rank),
  KEY idx_pos_rank (pos, rank)
) ENGINE=InnoDB;
```

Run this once against each environment's live database (same access path as
any other manual server-side step in this doc's baseline — SSH + the DB
client). `new/api/config/autodraft.json` deploys automatically with the
normal `git pull` since it's tracked in git (unlike `db.ini`), but the
priority-list feature will 500 on any environment missing this table until
it's applied.

## Webroot layout and `.htaccess` (discovered 2026-08-28)

`.htaccess` is gitignored and hand-provisioned on every environment (see
"Current state" below) — it exists nowhere in the repo, so its required
contents are documented here instead. DocumentRoot for the new app must
point at `new/web/dist/`, not the project root (keeps `.git/`,
`new/api/vendor/`, `new/api/config/db.ini`, `new/api/tests/` — which
includes the destructive `integration.php` — and `conf/wmffl.conf` all
outside the served tree entirely, rather than relying on `.htaccess` deny
rules to hide them). Required contents:

```apache
RewriteEngine On

# API: internal rewrite (not a redirect) to the proxy front controller.
# Do NOT point this at new/api/public/index.php directly — see the gotcha
# below. new/api/public/index.php reads REQUEST_URI itself and strips the
# /api prefix, so REQUEST_URI must be left intact; QSA preserves the query
# string.
RewriteRule ^api/(.*)$ api/index.php [L,QSA]

# SPA: serve real files/directories as-is...
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# ...otherwise fall back to index.html for client-side routing.
RewriteRule ^ index.html [L]

Options -Indexes
```

**Gotcha: the rewrite target must stay inside DocumentRoot.** An earlier
version of this rewrote straight to `../../api/public/index.php` (the real
front controller, which lives outside `new/web/dist/`). On staging this
produced a silent 500 on every `/api/*` request — including `/api/health`
with no query params, before any app code ran — with no PHP-level error
even with `display_errors` forced on, which is the signature of the
webserver itself refusing to execute a script outside DocumentRoot
(`open_basedir` or a suexec/suPHP-style restriction), not a PHP fault.
Confirmed by dropping a trivial `ping.php` directly in `new/web/dist/`,
which executed fine — isolating the failure to specifically the
outside-docroot rewrite target, not PHP execution in general.

The fix, now checked in: `new/web/public/api/index.php` is a proxy that
`require`s the real front controller — Vite copies it into
`new/web/dist/api/index.php` on every build (same mechanism as
`favicon.svg`), so it's a normal PHP `require` from an already-executing,
already-permitted script, which doesn't hit the same restriction. The
`.htaccess` rewrite above points at this proxy (`api/index.php`), not the
real front controller directly. Apply this exact rewrite on every
environment (staging *and* prod) — don't rediscover this on prod during
cutover.

## Current state (baseline)

- Deploy is `git pull` run manually on the server for both staging and prod.
- Node is not available on the host, so `new/web/dist/` (the Vite build output) is committed to the repo so a plain `git pull` deploys it. This means "did you rebuild `dist/` before this push" is a manual step, easy to forget — a stale `dist/` silently serves the previous SPA version after a deploy.
- `new/api/vendor/` is not committed; `composer install` is run directly on the host over SSH after any pull that touches `composer.json`/`composer.lock`.
- `.htaccess` and `new/api/config/db.ini` (and `conf/wmffl.conf` for the legacy app) are gitignored and pre-provisioned directly on the server, never deployed via git.

This baseline was chosen deliberately for the timeline: fewer moving parts to debug during a 25-day rewrite of a live multi-user event, at the cost of the manual "did you rebuild" step above.

## Proposed alternative: GitHub Actions CI/CD

SSH access to the host is already confirmed (per `docs/modernization-spec.md` §7), which makes a GitHub Actions deploy workflow viable:

1. **Trigger**: push to `staging` branch → deploy to staging; push to `main` (or a tag/manual approval) → deploy to prod. Consider a GitHub Environment with a manual approval gate for prod specifically, given this is a live draft-day event.
2. **Build**: on the runner (which has Node, unlike the host):
   - `new/web`: `npm ci && npm run build` → produces `dist/`
   - `new/api`: `composer install --no-dev --optimize-autoloader` → produces `vendor/`
3. **Deploy**: rsync or scp the built `new/web/dist/` and `new/api/vendor/` (plus any other changed application files) to the webroot over SSH, using an action like `easingthemes/ssh-deploy` or a hand-rolled `rsync -e ssh` step. SSH host/user/key stored as GitHub Actions secrets.
4. **Not touched by CI**: `.htaccess`, `db.ini`, `conf/wmffl.conf` stay server-side and gitignored, same as today — CI should never write these, only read/rely on them already being in place.

### Benefit over the baseline

Removes the "rebuild `dist/` and remember to commit it" footgun entirely — `web/dist/` and `new/api/vendor/` would no longer need to be committed at all, since CI builds them fresh on every deploy from source.

### Trade-offs / risks

- New infrastructure to configure correctly (SSH secrets, deploy script, staging/prod branch or environment split) under an already tight timeline.
- A CI failure becomes a new class of "why isn't my deploy showing up" problem to debug, potentially during draft-week crunch — this cuts against the spec's stated bias toward minimal moving parts for this specific project.
- Requires deciding now whether `web/dist/` and `new/api/vendor/` stop being committed (a change to the convention documented in `docs/modernization-spec.md` §3/§9), which would need that doc updated too if adopted.

### Recommendation (as of 2026-08-04)

Stick with manual `git pull` for the 2026-08-29 draft; treat GitHub Actions CI/CD as a post-draft-day improvement to revisit once the rewrite has shipped and stabilized, rather than adding new infrastructure risk this close to draft day.
