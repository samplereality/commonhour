# Scheduling Grid — Design Brief

Nov 18, 2026 · @Mark Sample

## Summary

Build a self-hosted group-availability tool that behaves like when2meet but looks entirely like its owner wants it to. The flow is the familiar one: someone creates an event, shares a link, everyone paints their free times on a grid, and the grid shows overlap as a heatmap.

One priority overrides the rest: **every visual decision must live in a theme layer the owner can edit without touching application code.** Match when2meet's behavior closely; owe nothing to its appearance.

Audience: small-group scheduling among colleagues and students. No accounts, no sign-up, no email.

**Note to Claude Code:** read this whole brief before writing code. Ask before adding any dependency not named here. Settle the open questions at the end with the owner before milestone 2.

## Core behavior

Replicate these when2meet behaviors. Anything not listed is out of scope for v1.

- **Create an event:** title; either specific dates (calendar picker) or days of the week (a generic week); earliest and latest time; slot length (15 minutes default, 30 optional); the event's time zone.
- **Share by link:** each event gets an unguessable slug, e.g. `/e/k3f9xq7r`. Anyone with the link can respond. No login.
- **Light sign-in per event:** a respondent enters a name and an optional password scoped to that event. Returning with the same name and password restores and edits their availability.
- **Paint availability:** press on a cell and drag to select a rectangle from the start cell to the current cell. The first cell's state sets the mode: starting on an empty cell adds, starting on a filled cell removes. Works with mouse, touch and pen.
- **Autosave:** every change saves in the background (debounced, about 500 ms). There is no submit button. Show a quiet saved / saving / error state.
- **Group heatmap:** a second grid shades each slot by how many people are free. Hovering or tapping a slot lists who is available and who is not. A legend shows the scale from 0 to N available.
- **Highlight one person:** hovering a name in the respondent list highlights only that person's slots.
- **View in your own time zone:** a selector, defaulting to the browser's zone, re-labels the grid.

## Theming system

The owner restyles the whole app by editing one CSS file per theme, plus plain template and copy files. Build for that from the first commit, not as a later pass.

**Tokens, not values.** Every color, font, type size, spacing step, radius, border width, shadow, transition and grid dimension is a CSS custom property defined in `themes/<name>/theme.css`. Components reference only tokens. No hex codes, `rgb()`, pixel font sizes or font names appear outside `themes/`. Add a check script that fails if they do.

**Minimum token set:**

| Group | Tokens (examples) |
| --- | --- |
| Color | `--bg`, `--surface`, `--ink`, `--ink-muted`, `--accent`, `--border`, `--focus`, `--error` |
| Heatmap | `--heat-0` through `--heat-max`, `--heat-steps`, `--cell-selected`, `--cell-highlight` |
| Type | `--font-body`, `--font-display`, `--font-mono`, `--size-xs` to `--size-xl`, `--weight-bold`, `--leading` |
| Space and shape | `--space-1` to `--space-6`, `--radius`, `--border-width` |
| Grid | `--cell-w`, `--cell-h`, `--cell-gap`, `--hour-rule`, `--half-hour-rule` |
| Motion | `--ease`, `--duration` (respect `prefers-reduced-motion`) |

**Heatmap ramp.** Default: interpolate between `--heat-0` and `--heat-max` with `color-mix(in oklch, …)` so a theme can change two colors and get a full ramp. A theme may instead define discrete stops. Cells carry `data-count` and `data-ratio` attributes so a theme can also style by count in pure CSS (e.g. `[data-count="0"]`).

**Stable, semantic markup.** Plain HTML with predictable class names (BEM-style: `.grid`, `.grid__cell`, `.grid__cell--selected`) and data attributes for state. JavaScript toggles attributes; it never sets inline styles except grid positioning. No CSS framework, no Tailwind, no CSS-in-JS.

**Templates and copy outside code.** Page shells, header, footer and wordmark live in template files the owner can edit. All user-facing strings (site name, button labels, instructions, empty states) live in one `copy` file. Voice is part of the look.

**Multiple themes.** A site-wide default set in config, plus an optional per-event theme chosen at creation. Ship two themes that differ sharply (layout density, type, palette, corner style) to prove the system works. Both support light and dark via `prefers-color-scheme`.

**Style guide page.** `/styleguide` renders every component, every button state, the sign-in panel, and a fake event at every heatmap level, with each token's current value listed. This is where the owner checks a theme edit.

**Guardrails a theme cannot break.** Availability is never conveyed by color alone (counts appear on hover and in an accessible table view). Focus states stay visible. The style guide flags text tokens that fail WCAG AA contrast.

## Tech stack and architecture

Stack: **PHP 8 + SQLite + vanilla JavaScript + plain CSS, with no build step**, deployable to a standard cPanel host (the owner uses Reclaim Hosting) by uploading files or pulling from git. This mirrors when2meet's own architecture, keeps every file readable, and needs no Node server or bundler.

&#91;embedded content: architecture · look layer over behavior\]

Editing or swapping a theme folder changes how every page looks; nothing in the bottom row needs to change.

- **Server:** a single front controller (`public/index.php`) routes page requests to templates and `/api/*` requests to JSON endpoints. Use PDO for SQLite. No framework; a tiny router is fine.
- **Client:** ES modules loaded directly by the browser. One module owns the grid (rendering, painting, autosave); one owns the heatmap; one owns time-zone conversion. No React, no jQuery.
- **Styling:** the active theme's `theme.css` loads first, then a base `app.css` that uses only tokens.
- **Storage:** one SQLite file kept outside the web root, created on first run from `schema.sql`.
- **Config:** `config.php` (copied from `config.example.php`) holds the site name, default theme, data path, retention period and limits.
- **Tests:** PHPUnit for slot and time-zone math and the API; a small browser test (Playwright) for painting is welcome but optional.

Suggested layout:

```
public/          index.php, assets/js/, assets/app.css, .htaccess
src/             Router.php, Api/, Db.php, Slots.php, TimeZones.php
templates/       layout, home, event, styleguide, partials/
themes/          default/theme.css, alt/theme.css, (fonts, wordmark)
copy/            en.php  (all user-facing strings)
data/            (SQLite file, git-ignored)
schema.sql  config.example.php  README.md  tests/
```

## Data model

Two tables. Store slots as UTC so time-zone display is a pure client concern.

**events**

| Column | Type | Notes |
| --- | --- | --- |
| `id` | integer PK |  |
| `slug` | text, unique | 8+ random URL-safe characters |
| `title` | text |  |
| `mode` | text | `dates` or `weekdays` |
| `days` | JSON | ISO dates, or weekday numbers 0–6 |
| `start_minute`, `end_minute` | integer | minutes after midnight in the event's zone |
| `slot_minutes` | integer | 15 or 30 |
| `timezone` | text | IANA name, e.g. `America/New_York` |
| `theme` | text, nullable | falls back to the site default |
| `admin_token_hash` | text | lets the creator edit or delete via a private link |
| `created_at`, `expires_at` | datetime |  |

**responses**

| Column | Type | Notes |
| --- | --- | --- |
| `id` | integer PK |  |
| `event_id` | integer FK | unique together with `name` |
| `name` | text | trimmed, compared case-insensitively |
| `password_hash` | text, nullable | `password_hash()` / bcrypt |
| `slots` | JSON | `dates` mode: UTC epoch seconds of each slot start; `weekdays` mode: `"weekday:minute"` keys in the event zone |
| `updated_at` | datetime |  |

API sketch: `POST /api/events`, `GET /api/events/{slug}` (event plus all responses, no hashes), `POST /api/events/{slug}/signin`, `PUT /api/events/{slug}/responses/me` (full slot list replaces the old one; authorized by a session cookie set at sign-in).

## Screens and interactions

Four pages. Layouts below are defaults; the theme may rearrange them through CSS alone, so keep the DOM order sensible for both a side-by-side and a stacked layout.

1. **Home / create event.** One form: title, dates-or-weekdays toggle, a month calendar for picking dates (click and drag across days), a weekday picker, earliest and latest time, slot length, time zone (defaults to the browser's), theme (if more than one exists). On submit, go to the new event and show its share link and private admin link once.
2. **Event page.** Title, share link with a copy button, then:
   - **Sign-in panel** (name, optional password) until the visitor signs in.
   - **Your availability grid** and **group heatmap**, side by side on wide screens, stacked with a tab toggle on narrow ones.
   - **Respondent list** with counts; hovering a name highlights that person.
   - **Time-zone selector** and the save-state indicator.
   - A visually hidden **table view** of the group results for screen readers.
3. **Admin view** (via the private link). Edit the title, extend dates, remove a respondent, delete the event.
4. **Style guide** at `/styleguide`, as described under Theming system.

Grid details: day columns, time rows, hour labels on the left, a heavier rule on each hour (both rule weights are tokens). Keyboard support: arrow keys move a focus cell; space toggles it; shift+arrows extend a selection.

## Hard parts

These are where clones usually go wrong. Handle each deliberately and cover it with tests.

- **Time zones.** An event is defined in its own zone; each date's slots convert to UTC separately, so a range that crosses a daylight-saving change stays correct. Weekday-mode events never convert (there is no date to anchor them); show them in the event's zone with a note. The viewer's zone only changes labels and may shift slots across day columns; handle that wrap rather than hiding it.
- **Mobile painting.** Use Pointer Events with `touch-action: none` on the grid only, so the page still scrolls outside it. Provide a visible "paint / scroll" toggle as a fallback. Minimum cell height on touch screens is a token, defaulting to 32 px.
- **Identity.** Name plus optional password per event, hashed with PHP's `password_hash()`. A signed, HTTP-only session cookie authorizes saves. No email, no recovery; a forgotten password means a new name.
- **Concurrency.** Each person writes only their own row, so last write wins per person. The group view polls every 15 seconds while the tab is visible and on focus. No websockets.
- **Abuse and limits.** Rate-limit event creation per IP; add a honeypot field; cap the date range (60 days default) and respondents per event (200 default). Event pages send `noindex`. Events auto-delete a configurable number of days after their last date (90 default).
- **Security basics.** Prepared statements everywhere, output escaping in templates, CSRF token on writes, the SQLite file and `config.php` unreachable from the web.

## Non-goals for v1

- User accounts, dashboards of "my events", or email of any kind
- Calendar import or sync (Google, Outlook, ICS)
- Real-time updates over websockets
- Recurring events or scheduling beyond picking a window
- "Preferred / if needed" availability tiers (a likely v2; keep the data model open to it)
- A JavaScript framework, a CSS framework, or a build step
- A theme editor UI; themes are edited as files

## Milestones and acceptance criteria

Build in this order and stop for the owner's review after milestones 1 and 4. The theme layer comes first so nothing is ever styled outside it.

1. **Skeleton and theme layer.** Router, layout template, copy file, default `theme.css`, base `app.css`, and `/styleguide` with a static sample grid.
   - Done when: editing five tokens visibly re-skins the whole style guide, and the check script finds no raw colors or font sizes outside `themes/`.
2. **Create an event.** Home form, `events` table, slug generation, admin link.
   - Done when: a new event's URL loads an empty grid with the right days, times and slot length.
3. **Paint and save.** Sign-in, drag painting with mouse and touch, autosave, restore on return.
   - Done when: two browsers under two names each paint, reload, and see their own availability intact.
4. **Group heatmap.** Shading by count, hover lists, respondent highlight, polling, accessible table view.
   - Done when: with three respondents, every shading level matches a hand count.
5. **Time zones.** Viewer selector and UTC conversion.
   - Done when: an event made in `America/New_York` reads correctly in `Europe/Rome` and `Asia/Tokyo`, and a date range spanning the November DST change has no missing or duplicated slots. Tests cover both.
6. **Second theme, admin view, deploy.** Ship the contrasting `alt` theme, per-event theme choice, admin edits, limits and expiry.
   - Done when: the README takes the owner from a fresh cPanel account to a working install, including how to make a new theme by copying a folder.

## Open questions for the owner

Claude Code should ask these before milestone 2, using the default in parentheses if there is no answer.

- [ ] **Address:** a subdomain or a path on an existing site? (subdomain)
- [ ] **Starting look:** is there an aesthetic, reference sites or fonts for the default theme, or should Claude Code ship a neutral, well-structured default for the owner to restyle? (neutral default)
- [ ] **Who can create events:** anyone with the URL, or only people with a shared creation passphrase? (shared passphrase)
- [ ] **Default slot length:** 15 or 30 minutes? (15)
- [ ] **Name:** what is the tool called? (placeholder "Overlap" in the copy file)
