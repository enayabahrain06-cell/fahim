# Private Wedding Invitation System — Laravel + SQLite

A complete build specification. Section 1 is the prompt to paste into your AI coding assistant (Claude Code, Cursor, etc.). Sections 2 onward are the detailed spec the prompt refers to; keep this whole file in the project root as `SPEC.md` so the assistant can read it.

---

## 1. The prompt

> Copy everything inside the box below and paste it to your coding assistant, with this file saved as `SPEC.md` in the project root. If you still have the working single-file prototype (`index.php`), put it in `reference/index.php` too: it shows the exact look, copy and animations to reproduce.

```
You are a senior Laravel engineer. Build a production-ready, private wedding invitation
system in Laravel 11 (PHP 8.2+) using SQLite, exactly as described in SPEC.md in the
project root. Read SPEC.md completely before writing any code, and treat it as the
source of truth. If reference/index.php exists, it is a working prototype: match its
visual design, wording, animations and music, but rebuild the logic the Laravel way.

What the system is:
- A mobile-first wedding invitation website that ONLY invited guests can open, each
  through a personal link (/i/{token}). Anyone without a valid link sees a polite
  "this invitation is private" page.
- A password-protected dashboard (/admin) for the couple to: manage the guest list
  (add one or many, set seats per invitation, WhatsApp number), send each guest their
  link on WhatsApp, track replies live (coming / declined / waiting, guests coming,
  seats filled), edit every invitation detail, upload the couple and venue photos,
  and export the guest list to PDF.
- Guests open their link, see an interactive invitation (envelope that opens with a
  wax seal, music, gentle auto-scroll, countdown, venue with Google Maps and
  add-to-calendar), and reply: accept with up to their allowed number of guests (and
  each guest's name) or decline. They can come back and change their reply.

How to work:
1. Start with `laravel new wedding` (no starter kit), configure SQLite, then build in
   this order: migrations and models -> invitation details settings -> guest links and
   private gate -> RSVP -> admin auth -> dashboard -> photos -> PDF -> polish.
2. Follow SPEC.md for the data model, routes, validation rules, security rules, UI
   design tokens, copy and acceptance criteria. Do not add features that are not in
   the spec, and do not drop any that are.
3. Use Blade + vanilla JS + plain CSS (no React/Vue, no Tailwind build step required).
   Keep the guest page fast: one CSS file, one JS file, fonts from Google Fonts.
4. Write Pest (or PHPUnit) feature tests for every item in "Acceptance criteria".
5. When done, give me: the file tree, the commands to run locally, and a deployment
   checklist for shared cPanel hosting and for a VPS.
Ask me only if something in SPEC.md is truly contradictory; otherwise make the
sensible choice, note it in README.md, and continue.
```

---

## 2. Overview

| Part | URL | Who |
|---|---|---|
| Invitation (personal) | `/i/{token}` | Invited guest with their link |
| Private notice | `/` and any invalid token | Everyone else |
| Invitation preview | `/admin/preview` | Signed-in couple |
| Dashboard | `/admin` | Signed-in couple |
| First-run setup | `/admin/setup` | Only while no admin exists |
| Sign in / out | `/admin/login`, `POST /admin/logout` | Couple |

One couple, one wedding per installation. All copy on the guest page is in English, with Arabic for the basmala, the couple's Arabic names and the closing verse.

Current wedding data (use as seed / defaults):

- Groom: **Fahim** (Arabic **فهيم**), son of **Hamza and Rahiyanath**
- Bride: **Marwa** (Arabic **مروة**), daughter of **Rafeek and Souda**
- Date and time: **Thursday, 19 November 2026, 7:30 PM onwards** (Asia/Bahrain, UTC+03:00)
- Venue: **Adhari New Seasons**, **Hall No. 1**
- Intro text: "In a moment filled with joy and gratitude, we invite you to share in our happiness as we begin our new journey together."
- Join line: "Join us as we celebrate our nikah"
- Closing verse: وَمِن كُلِّ شَيْءٍ خَلَقْنَا زَوْجَيْنِ لَعَلَّكُمْ تَذَكَّرُونَ

---

## 3. Tech stack and project setup

- Laravel 11, PHP 8.2+, SQLite (`DB_CONNECTION=sqlite`, `database/database.sqlite`).
- Sessions and cache: `database` driver (works on shared hosting). Queue not required.
- Storage: `public` disk for uploaded photos (`php artisan storage:link`).
- PDF: `barryvdh/laravel-dompdf`. Embed Rubik and Cormorant Garamond (TTF in `resources/fonts`) and Amiri or Aref Ruqaa for any Arabic text, so names render correctly.
- Images: `intervention/image` v3 to resize uploads to max 1600 px and re-encode as JPEG/WebP (this also strips EXIF and anything hidden in the file).
- Front end: Blade, `resources/css/app.css`, `resources/js/invite.js`, `resources/js/admin.js`. Vite is fine; also provide a prebuilt `public/build` so shared hosting without Node works.
- Timezone: `APP_TIMEZONE=Asia/Bahrain`. Store all timestamps in UTC; display in Asia/Bahrain.
- Tests: Pest.

---

## 4. Data model (SQLite)

### `invitees`
| column | type | notes |
|---|---|---|
| id | integer pk | |
| token | string(16) unique, indexed | 12 hex chars from `bin2hex(random_bytes(6))`; never guessable, never sequential |
| name | string(120) | as it should appear: "Ahmed Ali & family" |
| phone | string(20) nullable | digits only, international without + (e.g. 97333334444) |
| seats | unsigned tinyint, 1–20, default 1 | max guests this invitation may bring, including the invitee |
| status | enum `pending`, `yes`, `no`, default `pending` | |
| guests | unsigned tinyint, default 0 | confirmed count when `yes`, else 0 |
| guest_names | json nullable | array of names; first is the invitee |
| replied_at | timestamp nullable | last reply time |
| opened_at | timestamp nullable | first time the link was opened |
| open_count | unsigned int default 0 | |
| notes | string(255) nullable | private note for the couple |
| timestamps | | |

### `rsvp_logs` (history, append-only)
id, invitee_id (fk cascade), status, guests, guest_names (json), ip_hash (sha256 of IP + app key), user_agent (string 255), created_at. Used to show "changed reply" history and to investigate abuse.

### `settings` (key–value)
key (string pk), value (text, json). Keys:
- `details`: the invitation details object (section 7)
- `photo_couple`, `photo_venue`: stored path + cache-busting version
- `music`: `on` / `off` and the chosen theme (`nahawand` default)

Create a `Settings` service with `get(key, default)` / `set(key, value)` and cache it (forget on write).

### `users`
Standard Laravel users table. Exactly one admin user is allowed (see setup flow).

Models: `Invitee` (casts: `guest_names` array, dates), `RsvpLog`, `Setting`. Scopes: `coming()`, `declined()`, `waiting()`.

---

## 5. Routes

```php
// Guest side
Route::get('/', PrivateNoticeController::class)->name('private');
Route::get('/i/{token}', [InvitationController::class, 'show'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:60,1')->name('invite');
Route::post('/i/{token}/rsvp', [RsvpController::class, 'store'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:10,1')->name('rsvp');
Route::get('/i/{token}/calendar.ics', [InvitationController::class, 'calendar'])->name('invite.ics');

// Admin
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->middleware('no.admin')->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware(['no.admin', 'throttle:5,1']);
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('preview', [InvitationController::class, 'preview'])->name('preview');
        Route::get('stats', [DashboardController::class, 'stats'])->name('stats');          // JSON for live refresh
        Route::get('invitees', [InviteeController::class, 'index'])->name('invitees');      // JSON
        Route::post('invitees', [InviteeController::class, 'store']);                        // one
        Route::post('invitees/bulk', [InviteeController::class, 'bulk']);                    // many
        Route::patch('invitees/{invitee}', [InviteeController::class, 'update']);
        Route::post('invitees/{invitee}/reset', [InviteeController::class, 'reset']);
        Route::post('invitees/{invitee}/regenerate', [InviteeController::class, 'regenerate']);
        Route::delete('invitees/{invitee}', [InviteeController::class, 'destroy']);
        Route::put('details', [DetailsController::class, 'update']);
        Route::post('photos/{slot}', [PhotoController::class, 'store'])->whereIn('slot', ['couple', 'venue']);
        Route::delete('photos/{slot}', [PhotoController::class, 'destroy'])->whereIn('slot', ['couple', 'venue']);
        Route::get('export/pdf', [ExportController::class, 'pdf'])->name('export.pdf');
        Route::get('export/csv', [ExportController::class, 'csv'])->name('export.csv');
    });
});
```

- `no.admin` middleware: aborts 404 once any user exists.
- If no admin exists, `/admin` and `/admin/login` redirect to `/admin/setup`.
- All admin JSON routes return `{ ok: true, ... }` or `{ ok: false, error: "Human readable message" }` with a proper status code. CSRF via Laravel's token (`X-CSRF-TOKEN` header from a meta tag).

---

## 6. Guest experience (`/i/{token}`)

Valid token → render the invitation with the invitee injected as JSON in a `<script type="application/json" id="config">` tag (never inline untrusted data into JS directly; escape with `@json`). Invalid token → the private notice with HTTP 404 (not 403, to avoid confirming anything). Add `X-Robots-Tag: noindex, nofollow` and `<meta name="robots" content="noindex">` on every guest page. Set `opened_at` on first open and increment `open_count`.

### 6.1 Sequence on screen (mobile-first, max content width 440 px, centred on desktop with the envelope colour outside)

1. **Envelope intro (full screen).** Cream paper envelope with fine paper grain (SVG `feTurbulence` data URI), four flaps drawn with CSS `clip-path`, soft brown fold lines forming an X that meet at 56% height. Centre: an ivory wax seal (SVG blob with wavy edge, radial gradient, inner ring) with the couple's initials "F&M" in Cormorant italic, gently breathing. Above: couple names in italic and "WEDDING INVITATION" in spaced caps. Below the seal: chevron + "TAP TO OPEN".
2. **On tap:** music starts (user gesture unlocks audio; set `navigator.audioSession.type = "playback"` when available so iPhone silent mode does not mute it), the seal scales up and fades, the top flap swings up (`scaleY` to negative), a warm white glow blooms from the opening, the envelope fades out. Total ≈ 1.4 s. Then the page is revealed.
3. **Hero:** cream background with paper grain; a hand-drawn SVG garland of sage leaves and small white five-petal flowers across the top, with a few hanging strands; leafy sprigs in both bottom corners; gold dust sparkles slowly drifting up. Text, centred: "Dear {invitee name}," (italic), "JOYFULLY INVITE YOU / TO THEIR WEDDING" (spaced caps), date "19 . 11 . 26", names "FAHIM & MARWA" (Cormorant, uppercase, letter-spaced, "&" in italic sage), "Scroll down" with a bouncing chevron.
4. **Invitation text:** basmala in Aref Ruqaa (sage), intro paragraph, "Join us as we celebrate our nikah" (italic), the bride-and-groom illustration (uploaded photo if present, otherwise the bundled artwork) inside a soft circular fade, then two blocks: "FAHIM / فهيم / S/O / Hamza and Rahiyanath", a gold diamond ornament, "MARWA / مروة / D/O / Rafeek and Souda", then "Thursday, 19 November 2026, 7:30 PM onwards", then the verse, then the optional note pill (e.g. "Ladies only").
5. **Countdown:** title "THE CELEBRATION BEGINS IN", days : hours : mins : secs in large Cormorant numerals, updated every second, stopping at zero.
6. **Schedule (optional):** only rendered when the schedule list in details is not empty; vertical timeline with diamond markers.
7. **Location:** title "LOCATION", the venue photo (uploaded, else the bundled flower-arch illustration) in an arch frame (`border-radius: 200px 200px 14px 14px` with a 1 px offset outline), venue name, date line, hall/address, then two buttons: "Open in Google Maps" (map link from details) and "Add to calendar" (downloads `/i/{token}/calendar.ics`, event 4 hours long, title "Wedding of Fahim & Marwa", location venue + hall).
8. **RSVP block:** sage section with an arched top (`border-radius: 50% 50% 0 0 / 190px 190px 0 0`), title "CONFIRM YOUR ATTENDANCE", lead "To help us prepare for this celebration, kindly reply by {rsvpBy}." (or "kindly confirm your attendance." when no date), a small cream envelope with a sage wax seal "RSVP" and "Tap to open". It pulses gently when the auto-scroll arrives.
9. **Farewell:** "Hope to see you there!" (italic) and "FAHIM & MARWA" in spaced caps on sage.
10. **Floating music button** (bottom-right, inside the 440 px column, respects the safe area): pause / play icon.

**Auto-scroll:** about 2 s after the envelope opens, the page scrolls smoothly (≈95 px/s with ease-in and ease-out) until the RSVP section is in view, then the RSVP envelope pulses 4 times. Any touch, wheel, mouse-down or key press cancels the auto-scroll immediately. Skip auto-scroll when `prefers-reduced-motion: reduce`.

**Reveal on scroll:** sections fade up (opacity 0 → 1, translateY 24 px → 0, 0.9 s) via IntersectionObserver.

### 6.2 RSVP dialog (native `<dialog>`)

- Title "Confirm your attendance", subtitle "Kindly reply by {rsvpBy}" or "We would love to know if you can join us".
- If the invitee already replied: a soft box "You told us you'll attend with 3 guests in total. You can change your reply below." (or "…you can't attend…"), and the form is prefilled.
- "Will you be attending?" radios: "Accepts with pleasure" / "Declines with regret".
- Accept → shows the guests stepper (− count +), hint "Including yourself · up to {seats} guests" (when seats = 1, hide the stepper and show "This invitation is for one guest."), and one name field per extra guest ("Guest 2 name", …). The invitee's own name is never asked; it is known from the link.
- Decline → nothing else is asked.
- "Send RSVP" posts JSON to `/i/{token}/rsvp`. Success replaces the form with: accept → "Thank you, {first name}! We can't wait to celebrate with you." / decline → "Thank you for letting us know. You'll be missed." Error → "Couldn't send. Please check your connection and try again." and the button re-enables.
- In admin preview (no token) the form shows "This is your preview. Guests reply from their personal links." and does not submit.

### 6.3 RSVP validation (server, `RsvpRequest`)

- `attending`: required boolean.
- `count`: required if attending, integer, 1..invitee.seats.
- `names`: array, size must equal `count - 1`, each required string max 80, trimmed. Store as `[invitee name, ...names]`.
- On decline: guests = 0, guest_names = null.
- Update the invitee, append an `rsvp_logs` row, return `{ ok: true }`.

### 6.4 Music

Original, royalty-free, synthesized live with the Web Audio API (no audio files): a gentle theme in maqam Nahawand on D (D E F G A B♭ C♯ D) at 64 BPM, 8-bar loop over Dm – Gm – A – Dm – B♭ – Gm – A – Dm. Voices: nay-like melody (sine + triangle with 5 Hz vibrato and breathy band-passed noise), qanun-like eighth-note arpeggios (bright plucks), oud-like bass plucks on beats 1 and 3, soft string pad (detuned sawtooth through a 950 Hz low-pass), a very soft frame-drum pattern from bar 5. Second time through, the melody switches to an oud-like pluck with tremolo on long notes. Convolution reverb from a generated 3.8 s noise impulse, dynamics compressor on the master. Fade in over ~0.8 s. Port it from `reference/index.php` if available. Admin can turn music off in details.

### 6.5 Private notice (`/` and invalid tokens)

Cream page, centred: "WEDDING INVITATION" (spaced caps), "FAHIM & MARWA", a thin line, and in italic: "This invitation is private. Please open the personal link you received from the family." No other details are shown.

---

## 7. Invitation details (editable in the dashboard)

Stored as JSON in `settings.details`. Fields, labels and validation:

| key | label | rules |
|---|---|---|
| groom | Groom name | required, max 60 |
| bride | Bride name | required, max 60 |
| groom_arabic | Groom name in Arabic | nullable, max 60, rendered `dir="rtl"` |
| bride_arabic | Bride name in Arabic | nullable, max 60 |
| groom_parents | Groom's parents | nullable, max 120 |
| bride_parents | Bride's parents | nullable, max 120 |
| date | Date | required, date |
| time | Time | required, `H:i` |
| venue | Venue | required, max 120 |
| venue_address | Hall or address | nullable, max 160 |
| map_link | Google Maps link | nullable, url, must start with `https://` |
| rsvp_by | Reply by | nullable, max 60 (free text, e.g. "10 November") |
| intro | Invitation text | nullable, max 400 |
| join_line | Join line | nullable, max 120 |
| note | Note on the invitation | nullable, max 120 |
| schedule | Schedule of events | nullable array of `{time, title}`, max 8 |
| music | Background music | boolean, default true |

Derived on save (server side, Carbon in Asia/Bahrain): `day_name` ("Thursday"), `date_long` ("19 November 2026"), `short_date` ("19 . 11 . 26"), `start_time` ("7:30 PM onwards"), `starts_at` (ISO 8601 with +03:00) used by the countdown and the calendar file. Initials for the seals come from the first letter of each name.

---

## 8. Dashboard (`/admin`)

Mobile-first, max width 720 px. Sections in order:

1. **Header:** eyebrow "WEDDING RSVPS", couple names (Cormorant), buttons "PDF" and "Refresh".
2. **Shortcuts:** "Preview invitation" (opens `/admin/preview` in a new tab), "Guest list" (jumps to the list).
3. **Summary card (sage):** "GUESTS COMING" big number (sum of `guests` where status = yes), "{answered} of {total} invitations answered", and a ring showing acceptance rate (yes ÷ answered). Subtle drifting highlight in the background.
4. **Stats:** Accepted, Declined, No reply yet.
5. **Seats filled:** "{guests coming} / {total seats}", progress bar, "{pct}% of the {seats} invited seats are confirmed."
6. **Invitation photos:** two slots, "Bride & groom" and "Venue", each with a thumbnail, "Upload from device" and "Remove". The client resizes to max 1600 px before upload; the server validates again (section 10).
7. **Invitation details:** the form from section 7 (two columns on wide screens, one on phones), "Save details", note "Changes show on the invitation as soon as you save." Schedule editor: rows of time + title with add/remove.
8. **Add guests:** Name, Seats (default 1), WhatsApp number (optional), "Add guest". A collapsible "Add many at once" textarea: one guest per line, `name, seats, phone` (seats and phone optional; a 1–2 digit value is seats, longer digits are the phone). Also accept CSV upload with the same columns. Report "Added N guests" and list skipped lines with reasons.
9. **Guest list:** filter tabs All / Coming / Declined / Waiting, a search box (name, guest names, phone), and one card per invitee: name, status pill ("Coming · 3", "Declined", "Waiting"), meta line ("3 seats · Ahmed, Sara, Yusuf · Replied 18 Oct · Opened 2×"), and actions:
   - **Send on WhatsApp** → `https://wa.me/{phone}?text=…` (no phone: `https://wa.me/?text=…`) with: "Dear {name},\nWith joy, we invite you to the wedding of Fahim & Marwa.\nPlease open your personal invitation and confirm your attendance:\n{link}"
   - **Copy link**, **Open**
   - **Edit** (inline: name, seats, phone, private note)
   - **Clear reply** (only when answered; confirm first)
   - **New link** (regenerates the token so an old forwarded link stops working; confirm first)
   - **Remove** (confirm: "Remove {name}? Their link will stop working.")
10. **Footer:** "Updated {time}" and "Sign out".

Live data: the dashboard polls `/admin/stats` and `/admin/invitees` every 60 s (and on Refresh). Numbers animate (count up with ease-out over 0.9 s; a small "bump" when a value changes), the ring and bar animate their width, newly arrived or changed rows slide in; existing rows do not re-animate on every refresh.

Empty states: "Your guests will appear here" / "No one matches".

---

## 9. Export

- **PDF** (`/admin/export/pdf`, dompdf, A4 portrait, 16 mm/14 mm margins): title "{couple} · Guest list", generated time, summary boxes (guests coming, accepted, declined, no reply yet, % of seats), then three tables: **Coming** (#, invitation, guests / seats, guest names, replied), **Declined** (#, invitation, seats, phone), **No reply yet** (#, invitation, seats, phone). Sorted by name. Filename `Fahim-Marwa-guest-list-YYYY-MM-DD.pdf`. Arabic text must render (embed a font with Arabic glyphs).
- **CSV** with all invitee columns plus link, for Excel.

---

## 10. Security and privacy (must-haves)

- **Single admin.** `/admin/setup` exists only while there are no users; it asks for name, email and a password (min 10 characters, confirmed) and signs the user in. Afterwards it returns 404. Also provide `php artisan wedding:admin` to create or reset the admin password from the server.
- Passwords hashed with Laravel's default (bcrypt/argon). Session regeneration on login, `SESSION_SECURE_COOKIE=true` in production, `same_site=lax`, HTTPS enforced (`URL::forceScheme('https')` in production).
- Rate limits: login 5/min per IP+email, setup 5/min, RSVP 10/min per token, invitation page 60/min.
- Tokens: 12 hex characters from `random_bytes`; lookups by exact match; invalid tokens get the same 404 private page.
- Never expose other guests' data on the guest page: the page receives only that invitee's name, seats, status, guests and guest names.
- Uploads: only JPEG, PNG, WebP (check real MIME and decode with Intervention), max 8 MB, resized to ≤1600 px, re-encoded, saved with a fixed name per slot on the `public` disk plus `?v={timestamp}` for cache busting; old file deleted on replace/remove.
- Validation through Form Requests everywhere; all output escaped (Blade `{{ }}`, `@json`).
- Security headers middleware: `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `X-Frame-Options: DENY`, a Content-Security-Policy that allows self, Google Fonts (`fonts.googleapis.com`, `fonts.gstatic.com`), `data:` images and inline styles only where needed.
- `noindex, nofollow` on all pages; `robots.txt` disallowing everything.
- SQLite file stays in `database/` (outside `public/`). Back it up: `php artisan wedding:backup` copies it to `storage/app/backups/` with a date, keeping the last 14.

---

## 11. Design system

**Look:** cream paper, sage greenery, a touch of gold; calm, elegant, airy. Mobile-first.

| token | light | dark |
|---|---|---|
| paper | #F7F2E9 | #1E221B |
| paper-2 | #EEE6D8 | #171A14 |
| ink | #3D463A | #EDE8DC |
| ink-soft | #7B8070 | #B5B6A6 |
| accent (sage) | #6E7A50 | #A9B784 |
| olive (RSVP section, buttons) | #56613F | #56613F |
| line | #E2D9C8 | #343A2E |
| envelope | #EFE6D6 / #E2D6C1 / #F7F0E4 | same |
| gold detail | #C9A55A | same |

Dark mode via `prefers-color-scheme` on CSS variables (the envelope keeps its paper colours).

**Type:** Cormorant Garamond (400/500/600, italic) for display and the guest page body; Rubik (300/400/500) for the dashboard UI; Aref Ruqaa for Arabic. Titles: uppercase, letter-spacing .22em. Names: uppercase, .14em.

**Greenery:** generated SVG, not images: leaves are ellipses (rx 10, ry 4.2, scaled 0.8–1.4) placed alternately along a thin sage stem, rotated 40–65° off the stem; greens `#5F6B43 #7E8C5A #9AA77A #B7C19A #6E7A50`; flowers are five overlapping cream circles with a `#C9B77A` centre. Use a seeded random generator so the garland looks the same on every load.

**Motion:** ease `cubic-bezier(.2,.7,.3,1)`; durations 0.5–0.9 s; everything disabled under `prefers-reduced-motion: reduce`.

**Accessibility:** WCAG AA contrast, focus outlines (2 px accent), real buttons and labels, `aria-live` not used for the ticking countdown, `alt` text on images, dialog closes with Esc and backdrop click, tap targets ≥ 40 px.

**Safe areas:** `viewport-fit=cover`, `env(safe-area-inset-*)` padding on the root, fixed music button offset by the bottom inset.

---

## 12. Suggested file structure

```
app/
  Console/Commands/{CreateAdmin,BackupDatabase}.php
  Http/Controllers/{PrivateNotice,Invitation,Rsvp,Setup,Auth,Dashboard,Invitee,Details,Photo,Export}Controller.php
  Http/Middleware/{EnsureNoAdmin,SecurityHeaders}.php
  Http/Requests/{RsvpRequest,DetailsRequest,InviteeRequest,BulkInviteeRequest,PhotoRequest}.php
  Models/{Invitee,RsvpLog,Setting,User}.php
  Services/{Settings,InvitationDetails,GuestStats,IcsBuilder}.php
database/migrations/…  database/seeders/DemoSeeder.php (6 sample invitees, the details above)
resources/views/
  layouts/{guest,admin}.blade.php
  invite/{show,private}.blade.php  invite/partials/{envelope,hero,text,countdown,schedule,location,rsvp,farewell}.blade.php
  admin/{setup,login,dashboard}.blade.php  admin/partials/{summary,photos,details,add-guests,guest-list}.blade.php
  exports/guest-list.blade.php
resources/js/{invite.js,admin.js,music.js,greenery.js}
resources/css/{invite.css,admin.css}
resources/images/{couple-default.jpg} (the bundled bride-and-groom artwork)
tests/Feature/{PrivateAccess,Rsvp,AdminAuth,Invitees,Details,Photos,Export}Test.php
```

---

## 13. Acceptance criteria (write a test for each)

1. `/` and `/i/{unknown}` return the private notice (404) and contain no wedding details beyond the couple's names.
2. `/i/{valid}` returns 200, shows "Dear {name},", and sets `opened_at` once and increments `open_count` every visit.
3. RSVP accept with `count` ≤ seats and the right number of names saves status yes, guests, guest_names (invitee first) and writes an `rsvp_logs` row.
4. RSVP with `count` > seats, wrong number of names, or an empty name is rejected with 422.
5. RSVP decline saves status no, guests 0, guest_names null.
6. A second RSVP from the same link updates the invitee (no duplicate invitee) and adds a second log row.
7. `/admin` redirects to `/admin/setup` when no user exists; after setup, `/admin/setup` returns 404.
8. Admin routes require auth; guests get redirected or 401 JSON.
9. Login is throttled after 5 failures.
10. Adding one guest and bulk-adding lines creates invitees with unique 12-char tokens, correct seats and digits-only phones; bad lines are reported, not saved.
11. Regenerating a token makes the old link return the private notice.
12. Removing an invitee makes their link return the private notice and removes them from stats.
13. Saving details updates the guest page (names, date strings, venue, map link, note) and derives `starts_at` in +03:00.
14. Map links not starting with `https://` are rejected.
15. Uploading a non-image or an image over 8 MB is rejected; a valid upload replaces the bundled artwork on the guest page; removing restores it.
16. Stats JSON returns correct accepted, declined, waiting, guests coming, total seats and percentages for a seeded dataset.
17. PDF export downloads, has the three sections and renders Arabic names.
18. `calendar.ics` downloads a valid VEVENT with the right UTC start and 4-hour duration.
19. All responses carry the security headers and `noindex`.

---

## 14. Deployment notes

**Shared hosting (cPanel):** upload the project above `public_html`, point the domain's document root to `/public` (or move `public/` contents into `public_html` and fix the paths in `index.php`), create `database/database.sqlite`, set `.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain`, `DB_CONNECTION=sqlite`, `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`), run `php artisan key:generate`, `php artisan migrate --force`, `php artisan storage:link`, `php artisan config:cache route:cache view:cache`. Make `storage/`, `bootstrap/cache/` and `database/` writable. Enable HTTPS (AutoSSL).

**VPS:** Nginx + PHP-FPM 8.2+, same artisan steps, a daily cron for `php artisan wedding:backup`.

**First run:** open `https://yourdomain/admin`, create the admin account immediately, save the details, upload photos, add guests, send yourself a link and test a reply, then clear it before sending to guests.
