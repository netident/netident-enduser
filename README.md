# netident/otel-enduser

Enriches OpenTelemetry **SERVER** spans with end-user identity — device id,
session id, client address — for a PHP app that already has OpenTelemetry
auto-instrumentation running (CodeIgniter 4, Laravel, Slim, Symfony, any
PSR-15 app). **No code changes in your app.** `composer require` it and it
wires itself in through Composer's `files` autoload.

| attribute | source, in order |
|---|---|
| `app.device.id` | `baggage` header member `app.device.id` → `X-Device-Id` header → cookie `_nid_dev` → minted on this request (`NETIDENT_ISSUE_COOKIES`) |
| `app.device.id.source` | `baggage` \| `header` \| `cookie` \| `issued` (omitted when no id resolved) |
| `session.id` | `baggage` header member `session.id` (OTel semantic-convention name) → cookie `_nid_ses` → minted on this request |
| `client.address` | `REMOTE_ADDR`, or the first untrusted hop of `X-Forwarded-For` when `REMOTE_ADDR` is inside `NETIDENT_TRUSTED_PROXIES`; hashed instead when `NETIDENT_HASH_CLIENT_IP=true` |

The companion browser package sends `baggage: app.device.id=<uuid>,session.id=<uuid>`
on every same-origin `fetch`/`XHR`, which is how the identity gets from the
browser to your PHP server in the first place.

## Prerequisites

- PHP `>=8.1`
- `ext-opentelemetry` (the OpenTelemetry PHP extension) enabled
- `open-telemetry/sdk` and an auto-instrumentation package for your framework
  already installed and working — i.e. you already see SERVER spans for your
  requests without this package. This package only adds attributes to spans
  that already exist; it does not create a tracer, exporter or instrumentation
  on its own.

## Install

```bash
composer require netident/otel-enduser
```

### Without internet access

A host that cannot reach Packagist installs the same package from your
NETiDENT platform: download the zip and point Composer at it with an
`artifact` repository:

```bash
mkdir -p ./netident-sdk
curl -fsSL -o ./netident-sdk/netident-otel-enduser-0.1.1.zip \
  https://<your-platform-host>/sdk/netident-otel-enduser-0.1.1.zip
```

```json
{
    "repositories": [
        { "type": "artifact", "url": "./netident-sdk" }
    ]
}
```

```bash
composer require netident/otel-enduser
```

## Where the ids come from

This package only **reads** an id that arrives with the request — it cannot
tell two browsers apart on its own. Something has to create the ids:

- **The NETiDENT browser script** (`rum.js` / `@netident/rum`) on your pages.
  It sends them as `baggage` on fetch/XHR and keeps them in the `_nid_dev` /
  `_nid_ses` cookies, which is what a page load or a form post carries.
  It also gives you real-user page data (Web Vitals, JS errors), and honours
  its consent mode.
- **This package itself**, with `NETIDENT_ISSUE_COOKIES=true` — for an app
  that renders its own pages and runs no browser script. See below.

### Without a browser script

With `NETIDENT_ISSUE_COOKIES=true`, a browser **page load** (`Sec-Fetch-Dest:
document`, or `Accept: text/html` from a browser without Fetch Metadata) that
arrives without `_nid_dev` / `_nid_ses` gets them minted and set on the
response (`path=/`, `SameSite=Lax`, `Secure` on https, not HttpOnly so the
browser script can continue the same ids). The session cookie is re-set on
every page load and expires 15 minutes after the last one. That request's own
span already carries the new ids (`app.device.id.source=issued`); later
requests send the cookies back.

Things to know before turning it on:

- It sets an identifying cookie **without asking for consent**. If your site
  must ask first (PDPA / GDPR), leave it off and use the browser script's
  consent mode instead.
- Page-load responses carry `Set-Cookie`, which most CDNs and reverse-proxy
  caches treat as uncacheable.
- API clients, curl and health checks are never issued a cookie — they would
  not keep it, and every request would become a new "device".
- Needs a classic request-per-process runtime (php-fpm, mod_php). Worker
  runtimes (Swoole, RoadRunner, Octane) do not populate `$_SERVER` /
  `$_COOKIE` per request, which this package reads.

## Environment variables

| variable | default | meaning |
|---|---|---|
| `NETIDENT_ISSUE_COOKIES` | `false` | `true` makes this package create the ids itself — see "Without a browser script" below |
| `NETIDENT_DEVICE_COOKIE` | `_nid_dev` | cookie holding the device id, read when no `baggage` member and no `X-Device-Id` header are present; `off` stops reading it |
| `NETIDENT_SESSION_COOKIE` | `_nid_ses` | cookie holding the session id, read when `baggage` carries none; `off` stops reading it |
| `NETIDENT_TRUSTED_PROXIES` | empty | comma-separated list of IPv4/IPv6 CIDRs (or bare IPs) trusted to set `X-Forwarded-For`; empty means `X-Forwarded-For` is never trusted |
| `NETIDENT_HASH_CLIENT_IP` | `false` | when `true`, send `sha256(salt + ip)` truncated to 32 hex chars instead of the raw IP (for PDPA / GDPR-sensitive deployments) |
| `NETIDENT_IP_HASH_SALT` | empty | salt used when `NETIDENT_HASH_CLIENT_IP=true`; an empty salt is allowed but a real deployment should set one |
| `NETIDENT_ENDUSER_DISABLED` | `false` | set to `true` to disable this package entirely without removing it |

Standard OpenTelemetry variables are also honoured: setting
`OTEL_PHP_DISABLED_INSTRUMENTATIONS` to a comma list containing
`netident-enduser` or `all` disables this package the same way any other
auto-instrumentation is disabled.

## Verify

Send a request with a `baggage` header, e.g.:

```bash
curl -H 'baggage: app.device.id=test-device-1,session.id=test-session-1' \
  https://your-app.example/some/route
```

Then look at the SERVER span for that request in your tracing backend — it
should carry `app.device.id=test-device-1`, `app.device.id.source=baggage`
and `session.id=test-session-1`, plus `client.address`.

## Disable

Set `NETIDENT_ENDUSER_DISABLED=true`, or add `netident-enduser` (or `all`) to
`OTEL_PHP_DISABLED_INSTRUMENTATIONS`.

## Non-browser clients

A mobile app, a service-to-service call, or any client that cannot send a
`baggage` header can instead send the device id in a plain `X-Device-Id`
header — it is used whenever no `baggage` member is present.
