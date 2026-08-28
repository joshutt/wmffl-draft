<?php
// Proxy front controller — exists only so the real front controller
// (new/api/public/index.php) is reachable via an .htaccess rewrite that
// stays inside DocumentRoot.
//
// Discovered 2026-08-28 on staging: the shared host refuses to execute a
// script located outside DocumentRoot when reached via an Apache-level
// RewriteRule (open_basedir / suexec-style docroot restriction — confirmed
// via a minimal ping.php inside DocumentRoot, which worked fine, versus a
// direct rewrite straight to new/api/public/index.php, which 500'd with no
// PHP-level error at all). A plain PHP require, from a script that's
// already executing inside the allowed docroot, doesn't hit that same
// restriction — see docs/deployment-spec.md.
//
// This file lives in new/web/public/ so Vite copies it into new/web/dist/
// verbatim on every `npm run build` (same mechanism as favicon.svg),
// surviving the dist/ rebuild-and-recommit cycle instead of needing to be
// hand-created on every server.
require __DIR__ . '/../../../api/public/index.php';
