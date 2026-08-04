# WMFFL Draft SPA (new/web)

React + TypeScript + Vite front end for the draft rewrite
(`docs/modernization-spec.md`). Two routes: `/` is the public draft board,
`/commish` is the commissioner console. All data comes from the PHP JSON API
in `new/api` via polling (no websockets, by design).

## Building

On this repo's WSL host the checkout sits on a 9p mount where Node's file
syscalls fail — **always build with `./build.sh`**, which mirrors sources to
a native directory, builds there, and copies `dist/` back. On a normal
filesystem `npm install && npm run build` works directly.

`dist/` is committed on purpose: the shared host has no Node, deploys are a
plain `git pull`, so the build output must be in the repo. **Rebuild before
every deploy commit** (spec §3).

## Local development

1. Seed the local test DB: `php new/api/bin/seed-draft.php`
   (logins `owner1..owner12` / `pw1..pw12`, `commish` / `cpw`)
2. Run app + API together (from the repo root):
   `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t new/web/dist new/dev-server.php`
3. Open http://127.0.0.1:8080 — `new/dev-server.php` mimics the production
   .htaccess: SPA from `dist/` at the root, `/api/*` to the front controller,
   SPA-route fallback to index.html. (Serving `new/api/public/index.php`
   directly gives you the API only — `/` will be a JSON 404.)

For SPA work with hot reload, additionally run `npm run dev` (from the
native mirror dir on WSL) — `vite.config.ts` proxies `/api` to :8080.

`php new/api/tests/integration.php [base-url]` runs the full §4 parity
checklist against a running API (destructive — seeded/staging DBs only).
