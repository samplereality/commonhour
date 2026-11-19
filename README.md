# Common Hour

A self-hosted group-availability tool: create an event, share a link, everyone
paints their free time, and a heatmap shows the overlap. PHP 8, SQLite, vanilla
JavaScript and plain CSS, with no build step.

The original plan is in `Scheduling Grid — Design Brief.md`. All six of its
milestones are built.

## Run it locally

```
cp config.example.php config.php      # then set 'create_passphrase' in config.php
php -S 127.0.0.1:8000 -t public public/index.php
```

Open <http://127.0.0.1:8000/>. The database is created at `data/app.sqlite` on
first use, along with `data/secret.key`, which signs the sign-in cookies.

Until `create_passphrase` is set, anyone who can reach the site can create
events.

## Deploying to cPanel

These steps assume a cPanel account (Reclaim Hosting or similar) and the
address `commonhour.samplereality.com`. Menu names are those of current
cPanel; yours may differ slightly.

The one idea to hold on to: **only the `public/` folder is the website.**
Everything else (`src/`, `themes/`, `data/`, `config.php`) sits beside it,
where a browser cannot reach.

### 1. Check PHP

In **MultiPHP Manager**, the domain needs PHP 8.1 or newer. The extensions
`pdo_sqlite` and `mbstring` must be on; they are by default on most hosts, and
**Select PHP Version → Extensions** is where to tick them if not.

### 2. Put the files on the server

Put the project in a folder in your home directory, *not* inside
`public_html`. For example `/home/YOURUSER/commonhour`.

- **By upload:** zip the project folder on your computer (leave out `data/`
  and `config.php`), upload the zip with **File Manager**, and extract it
  there.
- **By git:** in **Git Version Control**, choose Create, paste the
  repository's clone URL, and set the repository path to `commonhour`.

### 3. Create the subdomain and point it at `public/`

In **Domains → Create A New Domain**:

- Domain: `commonhour.samplereality.com`
- Untick "Share document root"
- Document root: `commonhour/public`

If the subdomain already exists, use **Manage** beside it and change its
document root to `commonhour/public`.

### 4. Configure

In File Manager, copy `config.example.php` to `config.php` in the same folder
and edit the copy:

```php
'create_passphrase' => 'something only your event creators know',
```

Every other setting has a working default. The file's comments explain each
one.

### 5. Turn on HTTPS

cPanel issues a certificate automatically (AutoSSL), usually within the hour.
Once the padlock appears, switch on **Force HTTPS Redirect** for the subdomain
in **Domains**. Sign-in cookies are marked secure whenever the site is served
over HTTPS.

### 6. Check it

1. Open `https://commonhour.samplereality.com/styleguide`. You should see the
   style guide, fully styled.
2. Open the home page, create an event with your passphrase, sign in under a
   name and paint a few slots. "Saved" should appear.
3. Open `https://commonhour.samplereality.com/config.php` and
   `/data/app.sqlite`. Both must show the site's "Nothing here" page. If
   either downloads or shows its contents, the document root is wrong: go
   back to step 3.

### Optional: delete expired events on a schedule

Expired events stop being shown the moment they expire, and are deleted from
the database whenever someone creates a new event. To delete them on schedule
even when the site is quiet, add a daily job in **Cron Jobs**:

```
php /home/YOURUSER/commonhour/bin/purge.php
```

### Updating and backing up

- **Update:** upload the new files over the old ones (or use Pull in Git
  Version Control). `config.php` and `data/` are not part of the project
  files, so they are left alone. Do not delete `data/`.
- **Back up:** download `data/app.sqlite` and `data/secret.key`. Those two
  files and `config.php` are the whole state of the site.
- **Start over:** delete `data/app.sqlite`. It is recreated empty.

### If you cannot change the document root

Should the project have to live inside a web root (say
`public_html/meet/`, giving `example.com/meet/`), the `.htaccess` file at the
top of the project routes every request into `public/` and keeps the other
folders unreachable. Set the address prefix in `config.php`:

```php
'base_path' => '/meet',   // or '' if the project folder is itself the document root
```

Then run check 3 above against that address. The subdomain layout is simpler
and is the one to prefer.

### Troubleshooting

| You see | Likely cause |
| --- | --- |
| A blank page or "500" error | PHP older than 8.1, or `pdo_sqlite` missing (step 1). The domain's error log in cPanel names the problem. |
| Pages load without styles | `base_path` is set wrongly, or the document root is not `public/`. |
| "Something went wrong on the server" when creating an event | PHP cannot write to `data/`. Set the folder's permissions to 755 (or 700) in File Manager. |
| "This page has gone stale" on every save | Cookies are blocked for the site, or a cache is serving pages to everyone alike. Exclude the site from any page cache. |

## Themes

A theme is a folder in `themes/` containing `theme.css`. Two ship with the
app: `default` (plain and compact) and `alt` (warm paper, serif type, rounded
tiles), which exists to show how far a theme can go.

### Make a theme by copying a folder

1. Copy `themes/default` to `themes/yourname` (lower-case letters, digits and
   hyphens).
2. Edit the values in `themes/yourname/theme.css`.
3. Look at it: `/styleguide?theme=yourname`. Reload after each edit.
4. Use it everywhere by setting `'default_theme' => 'yourname'` in
   `config.php`.

No other step is needed: a folder with a `theme.css` in it is a theme. It
appears in the style guide's theme switcher and in the Theme menu on the
create form, where each event can pick its own. An event's theme can be
changed later from its admin page.

Fonts and images can sit in the theme folder and be referenced with relative
URLs (`url(fonts/name.woff2)`). To load fonts from another site, loosen
`csp` in `config.php`.

### Things worth knowing

- **Keep one token per line, and keep the `@group` comments.** The style guide
  reads the file to list tokens.
- **Light and dark:** the `@media (prefers-color-scheme: dark)` block
  overrides the color tokens. Check both; the style guide follows your
  system setting.
- **Any rule in a theme wins over the base stylesheet**, whatever its
  specificity, because the base styles sit in a cascade layer. So a theme can
  go beyond tokens and restyle a component directly. `themes/alt/theme.css`
  does this at the end of the file.
- **A theme may add tokens of its own** for its component rules (`alt` adds
  `--radius-pill` and `--font-label`).
- **The heatmap ramp** runs from `--heat-0` to `--heat-max` in `--heat-steps`
  shades (up to 12). For hand-picked stops instead, set `--heat-fill` per
  level: `[data-level="2"] { --heat-fill: #a9dcc3; }`. Set the fill through
  that variable, not `background-color`, so that highlighting one person
  still works. Cells also carry `data-count` and `data-ratio`.
- **Head counts in the cells** are in the markup but hidden. Show them with
  `.grid__count { display: block; }`, and set `--heat-ink` per level if the
  text needs to change color on dark shades.
- **State is in attributes**: `data-selected`, `data-highlight`, `data-level`,
  `data-inspect`, `data-gap`, `data-state`, `data-touch`, `data-locked`. Class
  names are BEM-style (`.grid`, `.grid__cell`, `.grid--heat`).
- **Three things a theme cannot switch off**: visible focus rings, the
  screen-reader-only text, and the reduced-motion setting.
- The style guide's **Contrast** table flags any text/background pair that
  fails WCAG AA, in whichever of light or dark mode is showing.

## Where things live

| You want to change | Edit |
| --- | --- |
| Colors, fonts, sizes, spacing, grid dimensions | `themes/<name>/theme.css` |
| Wording anywhere on the site | `copy/en.php` |
| Header, footer, wordmark, page shells | `templates/` |
| Site name, default theme, passphrase, limits | `config.php` |
| How the grid is built and behaves | `public/assets/js/` |

`public/` is the web root. `src/` is application code and `public/assets/` is
the base stylesheet and scripts; neither needs to change to restyle the site.

## The admin page

Creating an event shows a private admin link once. That page can:

- rename the event and change its theme;
- add dates (or weekdays, for a weekly event). Days are only ever added, so
  nothing anyone has painted is lost;
- remove a respondent, which deletes their name and times and signs them out;
- delete the event and every response to it.

The link is the only key. The server stores a hash of it, so a lost link
cannot be recovered; the event then simply expires on schedule.

## How it works

- **Slots are stored in UTC.** The server turns an event's dates and hours
  into a list of instants, one date at a time, so a range that crosses a
  clock change stays correct (`src/Slots.php`). The browser places each
  instant in a column and row for whichever zone the viewer picks
  (`public/assets/js/timezone.js`). When that moves part of a day into the
  next column, the grid shows the wrap rather than hiding it. Weekly events
  have no dates, so they are never converted and always show in the event's
  own zone.
- **Sign-in is per event**: a name and an optional password. A name saved
  without a password can be reopened by anyone who types it. A signed,
  HTTP-only cookie authorizes saves. There is no recovery.
- **Saving** happens half a second after each change; each person writes only
  their own row. The group view re-fetches every 15 seconds while the tab is
  visible, and when the window regains focus.
- **Limits** (all in `config.php`): events created per hour per address,
  longest date range, respondents per event, and days kept after the last
  date. Ten wrong passwords for one event from one address lock sign-in there
  for ten minutes. Event pages ask search engines not to index them.
- **API**: `POST /api/events`, `GET /api/events/{slug}`,
  `POST /api/events/{slug}/signin`, `POST /api/events/{slug}/signout`,
  `PUT /api/events/{slug}/responses/me`, and
  `POST /api/events/{slug}/admin/{update,remove,delete}`, which need the
  admin token in the body. Writes need the CSRF token from the page's
  `<meta name="csrf-token">`.

## Tests

```
php tests/run.php          # server: slots, time zones, validation, the API
node --test tests/js/      # browser-side time-zone layout (Node 22 or later)
php bin/check-tokens.php   # no raw visual values outside themes/
```

None of these needs anything installed beyond PHP and, for the second, Node.
The PHP tests are ordinary PHPUnit test cases: with Composer,
`composer install && vendor/bin/phpunit` runs the same files, and
`tests/run.php` is a small stand-in so they also run without it.

The token check fails if a raw color, font name, font size, length or
duration appears outside `themes/`, or if a template or script sets an inline
style. Breakpoints in `@media` conditions are exempt because CSS cannot read
tokens there.
