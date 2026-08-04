# WMFFL Draft App — Future Spec: WebSocket Push via Secondary Server

Status: deferred / not started. Post-launch enhancement, not part of the `docs/modernization-spec.md` critical path. Captured 2026-08-04.

## 1. Context

`docs/modernization-spec.md` §2 explicitly excludes WebSockets as a non-goal and keeps polling (§1 "Real-time" decision), because the production host is shared hosting: no persistent background processes, no non-standard port binding, no root, no vhost-level reverse-proxy config, no Node. Those constraints make running a WebSocket daemon directly on the production server impractical (see discussion below).

Since then, a second server has become available with **full root access**, on its own domain. That changes the calculus: a WebSocket layer becomes feasible **without touching the production server's constraints at all**, by using the second server purely as a broadcast relay rather than trying to run WebSockets on the shared host.

This document captures that architecture as a possible future phase. It is explicitly **not required for draft day 2026-08-29** — the polling-based SPA from the main spec is the load-bearing plan, and this is an optional enhancement layered on top of it afterward.

## 2. Why WebSockets can't run on the production server

- PHP's request lifecycle (mod_php/php-fpm) has no persistent process to hold an open socket — a WebSocket daemon needs a separate long-running process outside the normal request/response cycle.
- Shared hosts generally forbid the things such a daemon needs: long-lived background processes, binding non-standard TCP ports, root/systemd for process supervision, and PECL extensions (e.g. Swoole) that can't be installed without root.
- Even with SSH/Composer access (confirmed per modernization-spec §7), routing `wss://` traffic requires vhost-level reverse-proxy config (`Upgrade`/`Connection` header forwarding), which typical shared-host `.htaccess`-only access can't do.
- Node isn't available on the host at all (modernization-spec §7.4), ruling out Node-based WS servers (Soketi, plain `ws`, etc.) on that box specifically.

## 3. Proposed architecture: broadcast relay on the second server

Keep all writes and authoritative state exactly as in the main spec — this only adds a push notification layer on top.

```
Browser (React SPA, served from main domain)
   |
   |-- HTTP: POST /api/draft/pick, /api/commish/*, GET /api/draft/board  --> main server (unchanged, per modernization-spec)
   |
   `-- WSS: wss://ws.<second-domain>/draft  --------------------------------> second server (new)

main server (on pick/clock commit)
   `-- outbound HTTP POST /broadcast (shared-secret auth) --> second server daemon
                                                                    |
                                                                    `-- rebroadcasts to all connected WS clients
```

**Flow:**
1. React SPA connects to `wss://ws.<second-domain>/draft` on load, in addition to its normal REST calls to the main API. Cross-origin WebSocket connections don't hit CORS restrictions the way `fetch`/XHR do, so a different domain is fine.
2. Writes (pick submission, hold, commish actions) go through the main API exactly as specced — no change to `PickService`, the §6 transactional race fix, or any endpoint contracts.
3. When a write commits on the main server, it fires an outbound `curl` POST to the second server: `POST https://ws.<second-domain>/broadcast`, authenticated with a shared secret / HMAC header.
4. A daemon on the second server (Workerman is the natural choice — pure PHP, no PECL extension needed, can run both the WS listener and a small internal HTTP endpoint for the webhook in one process) receives the webhook and rebroadcasts to all connected WebSocket clients.
5. The broadcast payload should be a **minimal invalidation signal** (e.g. `{"type": "board-changed"}`), not authoritative state. On receipt, the client refetches from the real `/api/draft/board`. This keeps the WS layer as a "wake up and refetch now" nudge rather than a second source of truth that could drift from the DB or be spoofed into showing fake state.
6. Keep the existing polling as a fallback (e.g. slowed down, not removed) so a stalled or unreachable relay degrades to "picks appear a bit later," never "draft board stops updating."

## 4. What's needed on the second server

- A domain/subdomain pointed at it (e.g. `ws.wmffl-draft.example.com`) with its own TLS cert (Let's Encrypt/certbot).
- Workerman (or similar pure-PHP async server) running the WS listener plus the `/broadcast` HTTP endpoint.
- nginx (or equivalent) on that box terminating TLS and reverse-proxying `wss://` traffic (with `Upgrade`/`Connection` headers) to the local Workerman socket.
- systemd unit for the daemon (`Restart=always`) — trivial now that root is available, unlike on the shared host.
- Firewall: only 443 open publicly; the `/broadcast` endpoint should be locked down further (shared secret/HMAC, optionally IP-allowlist the main server's outbound IP).
- Origin check on the WS handshake, verifying the `Origin` header matches the main site's domain, so the relay isn't open to arbitrary cross-site connections.

## 5. Security notes

- The `/broadcast` endpoint is internet-reachable and must be authenticated — without it, anyone could POST fake "pick made" events to all connected clients.
- Treat all WS payloads as untrusted hints, never authoritative — the client must still fetch real state from `/api/draft/board` (main server, already authenticated via session) before acting on anything a WS message implies. This bounds the blast radius of a spoofed or malformed relay message to "someone sees a spurious refetch," not "someone sees fake draft state."
- No need to expose MySQL externally in this design — the second server never talks to the DB directly, only to the main server's webhook. Avoids the (likely unavailable) remote-MySQL question entirely.

## 6. Tradeoffs / when to build this

- Adds a second server, domain, and cert to operate and keep alive — real ongoing complexity versus the current zero-infrastructure polling approach.
- Should be built and tested **after** the go/no-go checkpoint in modernization-spec §9, as a progressive enhancement, not a dependency for draft day 2026-08-29. The fallback-to-polling behavior in §3.6 is what makes that safe: if the relay isn't ready or breaks, the SPA works exactly as it would without this document existing.
- Reasonable trigger to actually build it: draft day performance/UX feedback suggesting polling latency is a real problem, or simply having spare time after the 2026 draft to improve UX for 2027.
