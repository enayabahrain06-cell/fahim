<?php
/**
 * Wedding invitation system: the invitation, the RSVP database and the couple's dashboard in one place.
 *   /            → the invitation guests see
 *   /?admin      → the dashboard (password protected; the first visit creates the password)
 *   /?api=...    → the JSON API both pages use
 * Needs PHP 7.4+ with pdo_sqlite (standard on cPanel-style hosting).
 * Everything lives in this one file: upload it as index.php and open /?admin to start.
 * Guests can only open the invitation from the personal link the dashboard gives each of them.
 */
declare(strict_types=1);

const TZ_OFFSET = '+03:00';                 // Bahrain time
$root = __DIR__;
$dbFile = "$root/data/wedding.sqlite";
$uploadDir = "$root/uploads";
$slots = ['couple', 'venue'];
$detailKeys = [
    'groom' => 60, 'bride' => 60, 'groomArabic' => 60, 'brideArabic' => 60,
    'groomParents' => 120, 'brideParents' => 120, 'date' => 10, 'time' => 5,
    'dayName' => 20, 'dateLong' => 40, 'shortDate' => 20, 'startTime' => 40, 'startsAt' => 30,
    'venue' => 120, 'venueAddress' => 160, 'mapLink' => 600, 'rsvpBy' => 60, 'note' => 120, 'maxGuests' => 2,
];

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
session_name('wedding_admin');
session_start();

function cut(string $s, int $n): string { return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n); }
function out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function body(): array {
    $d = json_decode(file_get_contents('php://input', false, null, 0, 20000) ?: '{}', true);
    return is_array($d) ? $d : [];
}

foreach (['data' => "Require all denied\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n",
          'uploads' => "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\nOptions -Indexes\n"] as $dir => $rules) {
    if (!is_dir("$root/$dir")) @mkdir("$root/$dir", 0755, true);
    if (is_dir("$root/$dir") && !is_file("$root/$dir/.htaccess")) @file_put_contents("$root/$dir/.htaccess", $rules);
}

try {
    $db = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('CREATE TABLE IF NOT EXISTS rsvps (
        id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, name TEXT NOT NULL,
        attending INTEGER NOT NULL, guests INTEGER NOT NULL, guest_names TEXT NOT NULL DEFAULT "")');
    $db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS invitees (
        id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT "", seats INTEGER NOT NULL DEFAULT 1,
        status TEXT NOT NULL DEFAULT "pending", guests INTEGER NOT NULL DEFAULT 0,
        guest_names TEXT NOT NULL DEFAULT "", replied_at TEXT, created_at TEXT NOT NULL)');
} catch (Throwable $e) {
    if (isset($_GET['api'])) out(['ok' => false, 'error' => 'database unavailable'], 500);
    http_response_code(500);
    exit('The database could not be opened. Please enable PHP SQLite (pdo_sqlite) and make sure the data folder is writable.');
}

function setting(PDO $db, string $k): ?string {
    $st = $db->prepare('SELECT value FROM settings WHERE key = ?'); $st->execute([$k]);
    $v = $st->fetchColumn(); return $v === false ? null : (string)$v;
}
function setSetting(PDO $db, string $k, ?string $v): void {
    if ($v === null) { $db->prepare('DELETE FROM settings WHERE key = ?')->execute([$k]); return; }
    $db->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$k, $v]);
}
function details(PDO $db): array { $d = json_decode(setting($db, 'details') ?? '{}', true); return is_array($d) ? $d : []; }
function photos(PDO $db, array $slots): array { $r = []; foreach ($slots as $s) $r[$s] = setting($db, "photo_$s"); return $r; }
function invitee(PDO $db, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{8,32}$/', $token)) return null;
    $st = $db->prepare('SELECT * FROM invitees WHERE token = ?'); $st->execute([$token]);
    $r = $st->fetch(PDO::FETCH_ASSOC); return $r ?: null;
}
function loggedIn(): bool { return !empty($_SESSION['admin']); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function requireAdmin(bool $write = true): void {
    if (!loggedIn()) out(['ok' => false, 'error' => 'Please sign in.'], 401);
    if ($write && !hash_equals(csrf(), (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) out(['ok' => false, 'error' => 'Your session expired. Please reload the page.'], 403);
}
function removePhoto(PDO $db, string $dir, string $slot): void {
    foreach (['jpg', 'png', 'webp'] as $ext) { $f = "$dir/$slot.$ext"; if (is_file($f)) unlink($f); }
    setSetting($db, "photo_$slot", null);
}

/* ------------------------------ API ------------------------------ */
if (isset($_GET['api'])) {
    $api = (string)$_GET['api'];
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

    if ($api === 'rsvp' && $post) {
        $d = body();
        $inv = invitee($db, (string)($d['token'] ?? ''));
        if (!$inv) out(['ok' => false, 'error' => 'This invitation link is not valid.'], 403);
        $attending = ($d['attending'] ?? false) === true;
        $seats = max(1, (int)$inv['seats']);
        $guests = $attending ? max(1, min($seats, (int)($d['count'] ?? 1))) : 0;
        $names = '';
        if ($attending && is_array($d['names'] ?? null)) {
            $clean = array_filter(array_map(fn($x) => trim(cut((string)$x, 80)), $d['names']), fn($x) => $x !== '');
            $names = cut(implode(', ', array_slice($clean, 0, $guests)), 600);
        }
        $db->prepare('UPDATE invitees SET status = ?, guests = ?, guest_names = ?, replied_at = ? WHERE id = ?')
           ->execute([$attending ? 'yes' : 'no', $guests, $names, gmdate('c'), $inv['id']]);
        out(['ok' => true]);
    }

    if ($api === 'me') {
        out(['ok' => true, 'setup' => setting($db, 'admin_hash') === null, 'loggedIn' => loggedIn(),
             'csrf' => loggedIn() ? csrf() : '', 'details' => loggedIn() ? details($db) : null,
             'photos' => loggedIn() ? photos($db, $slots) : null]);
    }

    if (($api === 'setup' || $api === 'login') && $post) {
        $pw = (string)(body()['password'] ?? '');
        $hash = setting($db, 'admin_hash');
        if ($api === 'setup') {
            if ($hash !== null) out(['ok' => false, 'error' => 'A password already exists. Please sign in.'], 400);
            if (strlen($pw) < 8) out(['ok' => false, 'error' => 'Use at least 8 characters.'], 400);
            setSetting($db, 'admin_hash', password_hash($pw, PASSWORD_DEFAULT));
        } elseif ($hash === null || !password_verify($pw, $hash)) {
            sleep(1);
            out(['ok' => false, 'error' => 'Wrong password.'], 400);
        }
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        out(['ok' => true, 'csrf' => csrf()]);
    }

    if ($api === 'logout' && $post) { $_SESSION = []; session_destroy(); out(['ok' => true]); }

    if ($api === 'invitees') {
        requireAdmin(false);
        $rows = $db->query('SELECT id, token, name, phone, seats, status, guests, guest_names AS names, replied_at AS time, created_at FROM invitees ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['seats'] = (int)$r['seats']; $r['guests'] = (int)$r['guests']; }
        out(['ok' => true, 'rows' => $rows]);
    }

    if ($api === 'invitee_add' && $post) {
        requireAdmin();
        $items = body()['items'] ?? [];
        if (!is_array($items) || !$items) out(['ok' => false, 'error' => 'Add at least one name.'], 400);
        $st = $db->prepare('INSERT INTO invitees (token, name, phone, seats, created_at) VALUES (?, ?, ?, ?, ?)');
        $added = 0;
        foreach (array_slice($items, 0, 500) as $it) {
            $name = trim(cut((string)($it['name'] ?? ''), 120));
            if ($name === '') continue;
            $phone = preg_replace('/[^0-9]/', '', (string)($it['phone'] ?? ''));
            $seats = max(1, min(20, (int)($it['seats'] ?? 1)));
            $st->execute([bin2hex(random_bytes(6)), $name, cut($phone, 20), $seats, gmdate('c')]);
            $added++;
        }
        out(['ok' => true, 'added' => $added]);
    }

    if ($api === 'invitee_update' && $post) {
        requireAdmin();
        $d = body();
        $db->prepare('UPDATE invitees SET name = ?, phone = ?, seats = ? WHERE id = ?')->execute([
            trim(cut((string)($d['name'] ?? ''), 120)) ?: 'Guest',
            cut(preg_replace('/[^0-9]/', '', (string)($d['phone'] ?? '')), 20),
            max(1, min(20, (int)($d['seats'] ?? 1))), (int)($d['id'] ?? 0)]);
        out(['ok' => true]);
    }

    if (($api === 'invitee_delete' || $api === 'invitee_reset') && $post) {
        requireAdmin();
        $id = (int)(body()['id'] ?? 0);
        if ($api === 'invitee_delete') $db->prepare('DELETE FROM invitees WHERE id = ?')->execute([$id]);
        else $db->prepare('UPDATE invitees SET status = "pending", guests = 0, guest_names = "", replied_at = NULL WHERE id = ?')->execute([$id]);
        out(['ok' => true]);
    }

    if ($api === 'settings' && $post) {
        requireAdmin();
        $in = body(); $clean = details($db);
        foreach ($detailKeys as $k => $n) {
            if (!array_key_exists($k, $in)) continue;
            $v = trim(cut((string)$in[$k], $n));
            if ($k === 'mapLink' && $v !== '' && !preg_match('#^https://#i', $v)) out(['ok' => false, 'error' => 'The map link must start with https://'], 400);
            if ($k === 'maxGuests') $v = (string)max(1, min(20, (int)$v));
            $clean[$k] = $v;
        }
        setSetting($db, 'details', json_encode($clean, JSON_UNESCAPED_UNICODE));
        out(['ok' => true, 'details' => $clean]);
    }

    if (($api === 'upload' || $api === 'remove') && $post) {
        requireAdmin();
        $slot = (string)($_GET['slot'] ?? '');
        if (!in_array($slot, $slots, true)) out(['ok' => false, 'error' => 'unknown photo'], 400);
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if ($api === 'remove') { removePhoto($db, $uploadDir, $slot); out(['ok' => true, 'photos' => photos($db, $slots)]); }
        $f = $_FILES['photo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) out(['ok' => false, 'error' => 'No file was received.'], 400);
        if ($f['size'] > 8 * 1024 * 1024) out(['ok' => false, 'error' => 'The photo is too large (8 MB max).'], 400);
        $info = @getimagesize($f['tmp_name']);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) out(['ok' => false, 'error' => 'Please choose a JPG, PNG or WebP image.'], 400);
        removePhoto($db, $uploadDir, $slot);
        $name = "$slot." . $types[$info[2]];
        if (!move_uploaded_file($f['tmp_name'], "$uploadDir/$name")) out(['ok' => false, 'error' => 'The photo could not be saved.'], 500);
        setSetting($db, "photo_$slot", "uploads/$name?v=" . time());
        out(['ok' => true, 'photos' => photos($db, $slots)]);
    }

    out(['ok' => false, 'error' => 'not found'], 404);
}

/* ------------------------------ Pages ------------------------------ */
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (isset($_GET['admin'])) {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    echo admin_html();
    exit;
}

$d = details($db);
$inv = invitee($db, (string)($_GET['i'] ?? ''));
if (!$inv && !loggedIn()) {
    header('X-Robots-Tag: noindex, nofollow');
    http_response_code(403);
    echo private_html($d);
    exit;
}
header('X-Robots-Tag: noindex, nofollow');
$guest = $inv ? ['name' => $inv['name'], 'seats' => (int)$inv['seats'], 'status' => $inv['status'],
                 'guests' => (int)$inv['guests'], 'names' => $inv['guest_names']] : null;
$cfg = json_encode(['details' => $d, 'photos' => photos($db, $slots), 'invitee' => $guest, 'preview' => !$inv],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$html = invite_html();
$html = str_replace('/*__CONFIG__*/', 'window.WEDDING_CONFIG = ' . $cfg . ';', $html);
if (!empty($d['groom']) && !empty($d['bride'])) {
    $t = htmlspecialchars($d['groom'] . ' & ' . $d['bride'] . ' · Wedding Invitation', ENT_QUOTES, 'UTF-8');
    $html = preg_replace('#<title>.*?</title>#s', "<title>$t</title>", $html, 1);
}
echo $html;

function private_html(array $d): string {
    $names = htmlspecialchars(trim(($d['groom'] ?? 'Fahim') . ' & ' . ($d['bride'] ?? 'Marwa')), ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
      . '<meta name="robots" content="noindex"><title>Private invitation</title>'
      . '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,400&display=swap" rel="stylesheet">'
      . '<style>:root{--bg:#F7F2E9;--ink:#3D463A;--soft:#7B8070;--line:#E2D9C8}@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){color-scheme:dark;--bg:#1E221B;--ink:#EDE8DC;--soft:#B5B6A6;--line:#343A2E}}'
      . ':root[data-theme="dark"]{color-scheme:dark;--bg:#1E221B;--ink:#EDE8DC;--soft:#B5B6A6;--line:#343A2E}'
      . 'html,body{height:100%}body{margin:0;background:var(--bg);color:var(--ink);font-family:"Cormorant Garamond",Georgia,serif;display:grid;place-items:center;padding:24px;box-sizing:border-box;text-align:center}'
      . 'p{margin:0}.e{letter-spacing:.3em;text-transform:uppercase;font-size:.8rem;color:var(--soft)}h1{font-weight:500;letter-spacing:.12em;text-transform:uppercase;font-size:2rem;margin:10px 0 16px}'
      . '.t{font-style:italic;font-size:1.25rem;color:var(--soft);max-width:22rem;line-height:1.5}.l{width:120px;height:1px;background:var(--line);margin:18px auto}</style></head><body><main>'
      . '<p class="e">Wedding invitation</p><h1>' . $names . '</h1><div class="l"></div>'
      . '<p class="t">This invitation is private. Please open the personal link you received from the family.</p></main></body></html>';
}

/* ------------------------------ Page templates ------------------------------ */
function invite_html(): string {
    return <<<'__INVITE_HTML__'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Fahim & Marwa · Wedding Invitation</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Aref+Ruqaa&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Pinyon+Script&display=swap" rel="stylesheet">
<style>
  :root {
    --paper: #F7F2E9; --paper-2: #EEE6D8; --ink: #3D463A; --ink-soft: #7B8070;
    --gold: #6E7A50; --gold-soft: rgba(110,122,80,.35); --line: #E2D9C8; --wine: #3D463A;
    --velvet: #EFE6D6; --velvet-2: #E2D6C1; --velvet-3: #F7F0E4; --cream: #F7F2E9; --olive: #56613F;
    --err: #A33B2A;
    box-sizing: border-box;
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: env(safe-area-inset-bottom, 0px);
  }
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      color-scheme: dark;
      --paper: #1E221B; --paper-2: #171A14; --ink: #EDE8DC; --ink-soft: #B5B6A6;
      --gold: #A9B784; --gold-soft: rgba(169,183,132,.35); --line: #343A2E; --err: #EE8E7B; --wine: #EDE8DC;
    }
  }
  :root[data-theme="dark"] {
    color-scheme: dark;
    --paper: #1E221B; --paper-2: #171A14; --ink: #EDE8DC; --ink-soft: #B5B6A6;
    --gold: #A9B784; --gold-soft: rgba(169,183,132,.35); --line: #343A2E; --err: #EE8E7B; --wine: #EDE8DC;
  }
  html { scroll-padding-top: env(safe-area-inset-top, 0px); scroll-behavior: smooth; }
  *, *::before, *::after { box-sizing: border-box; }
  body {
    margin: 0; background: var(--paper); color: var(--ink);
    font-family: "Cormorant Garamond", "Times New Roman", Georgia, serif;
    font-size: 19px; line-height: 1.6; -webkit-font-smoothing: antialiased;
  }
  body.sealed { overflow: hidden; }
  body.sealed main { visibility: hidden; }
  main { max-width: 440px; margin: 0 auto; background: var(--paper); min-height: 100svh; overflow-x: hidden; }
  @media (min-width: 480px) {
    body { background: var(--velvet-2); }
    main { box-shadow: 0 0 60px rgba(0,0,0,.4); }
  }
  .script { font-family: "Cormorant Garamond", Georgia, serif; font-style: italic; font-weight: 400; }

  /* ================= ENVELOPE ================= */
  .intro {
    position: fixed; top: 0; bottom: 0; left: 50%; width: min(100%, 440px); transform: translateX(-50%);
    z-index: 50; overflow: hidden; perspective: 1400px;
    background: var(--velvet-2);
    padding: env(safe-area-inset-top, 0px) 0 env(safe-area-inset-bottom, 0px);
    transition: opacity .45s ease .85s, visibility 0s linear 1.3s;
  }
  .intro.open { opacity: 0; visibility: hidden; }
  .velvet { position: absolute; inset: 0; background-color: var(--velvet); }
  .velvet::after { content: ""; position: absolute; inset: 0; pointer-events: none; }
  .back { background-image: radial-gradient(ellipse at 50% 40%, var(--velvet-3), var(--velvet-2) 75%); }
  .flap { position: absolute; inset: 0; background-color: var(--velvet); }
  .flap-bottom { clip-path: polygon(0 100%, 100% 100%, 50% 56%); background-image: linear-gradient(to top, var(--velvet-2), var(--velvet-3)); }
  .flap-side { clip-path: polygon(0 0, 50% 56%, 0 100%); background-image: linear-gradient(to right, var(--velvet-2), var(--velvet)); }
  .flap-side.r { clip-path: polygon(100% 0, 50% 56%, 100% 100%); background-image: linear-gradient(to left, var(--velvet-2), var(--velvet)); }
  .flap-top {
    clip-path: polygon(0 0, 100% 0, 50% 56%); transform-origin: top center;
    background-image: linear-gradient(to bottom, var(--velvet-3), var(--velvet));
    filter: drop-shadow(0 6px 10px rgba(0,0,0,.35));
    transition: transform .65s cubic-bezier(.5,.05,.3,1) .1s, opacity .25s ease .55s;
  }
  .intro.open .flap-top { transform: rotateX(178deg); opacity: 0; }
  .pattern { position: absolute; inset: 0; opacity: .9; pointer-events: none; }
  .lines { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
  .lines path { fill: none; stroke: rgba(110,92,60,.3); stroke-width: 1.4; }
  .intro.open .lines { opacity: 0; transition: opacity .3s ease .1s; }

  .glow {
    position: absolute; inset: -20%; pointer-events: none; opacity: 0; transform: scale(.5);
    background: radial-gradient(circle at 50% 52%, #FFFFFF 0%, #FFFBF3 24%, rgba(255,248,236,.75) 42%, rgba(255,248,236,0) 64%);
    transition: opacity .45s ease .3s, transform .8s ease .3s;
  }
  .intro.open .glow { opacity: 1; transform: scale(2.4); }

  .seal-btn {
    position: absolute; left: 50%; top: 56%; transform: translate(-50%, -50%);
    width: min(34vw, 150px); aspect-ratio: 1; border: 0; padding: 0; background: none; cursor: pointer;
    filter: drop-shadow(0 8px 14px rgba(0,0,0,.4)); z-index: 3;
    transition: transform .6s cubic-bezier(.3,.7,.3,1), opacity .5s ease;
  }
  .seal-btn svg { width: 100%; height: 100%; display: block; }
  .seal-btn:focus-visible { outline: 2px solid #D8B86A; outline-offset: 10px; border-radius: 50%; }
  .intro.open .seal-btn { transform: translate(-50%, -50%) scale(1.25); opacity: 0; }
  .tap {
    position: absolute; left: 0; right: 0; top: calc(56% + min(17vw, 75px) + 18px); margin: 0;
    text-align: center; color: #7B7462; letter-spacing: .25em; font-size: .78rem; font-family: "Cormorant Garamond", serif;
    text-transform: uppercase; transition: opacity .3s;
  }
  .tap svg { display: block; margin: 0 auto 6px; width: 16px; height: 10px; }
  .intro.open .tap { opacity: 0; }
  .from { position: absolute; left: 0; right: 0; top: 16%; text-align: center; color: #5E5A4B; margin: 0; }
  .from .script { font-size: 2.4rem; line-height: 1.1; display: block; }
  .from small { letter-spacing: .3em; font-size: .72rem; text-transform: uppercase; opacity: .8; }
  .intro.open .from { opacity: 0; transition: opacity .4s; }

  /* ================= HERO ================= */
  .hero {
    position: relative; min-height: calc(100svh - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px));
    display: grid; place-items: center; overflow: hidden; background: var(--paper-2);
  }
  .hero .pattern { opacity: .55; }
  .arch { position: absolute; inset: 18px; width: calc(100% - 36px); height: calc(100% - 36px); }
  .arch .fill { fill: var(--paper); }
  .arch .ln { fill: none; stroke: var(--gold); stroke-width: 1.2; vector-effect: non-scaling-stroke; }
  .arch .ln2 { fill: none; stroke: var(--gold); stroke-width: .7; opacity: .6; vector-effect: non-scaling-stroke; }
  .hero-in { position: relative; text-align: center; padding: 18vh 34px 40px; z-index: 1; }
  .hero-in .wd { font-size: .85rem; font-style: normal; color: var(--ink-soft); margin: 0; line-height: 1.8; letter-spacing: .3em; text-transform: uppercase; }
  .hero-in .dt { letter-spacing: .32em; margin: 6px 0 0; font-size: 1.05rem; }
  .names { color: var(--ink); margin: 3vh 0 0; font-size: clamp(2.6rem, 12vw, 3.6rem); line-height: 1.1; font-weight: 500; font-style: normal; text-transform: uppercase; letter-spacing: .14em; }
  .names span { display: block; }
  .names .amp { font-size: .5em; color: var(--gold); margin: 6px 0; }
  .scroll { display: inline-block; margin-top: 7vh; color: var(--ink-soft); text-decoration: none; font-size: 1.15rem; }
  .scroll svg { display: block; margin: 2px auto 0; width: 22px; height: 12px; }
  .spark { position: absolute; bottom: -10px; width: 4px; height: 4px; border-radius: 50%; background: var(--gold); opacity: 0; pointer-events: none; }

  .arch { display: none; }
  .hero { background: var(--paper); }
  .garland { position: absolute; top: -6px; left: -6%; width: 112%; pointer-events: none; }
  .garland svg, .sprig svg { width: 100%; display: block; }
  .sprig { position: absolute; bottom: -4px; width: 48%; pointer-events: none; }
  .sprig.l { left: -4%; } .sprig.r { right: -4%; transform: scaleX(-1); }
  main .sec.olive-sec { background: var(--olive); color: var(--cream); border-radius: 50% 50% 0 0 / 190px 190px 0 0; margin-top: 30px; padding-top: 190px; max-width: none; }
  .olive-sec .title { max-width: 250px; margin: 0 auto; }
  .olive-sec .title, .olive-sec .lead, .olive-sec .open-hint { color: var(--cream); }
  .olive-sec .orn line, .olive-sec .orn path { stroke: var(--cream); } .olive-sec .orn .d { fill: var(--cream); }
  main .sec.olive-tail { background: var(--olive); color: var(--cream); max-width: none; }
  .olive-tail .sign { color: var(--cream); opacity: .8; }
  .btns { display: flex; flex-direction: column; align-items: center; gap: 10px; }

  /* ================= SECTIONS ================= */
  .sec { max-width: 540px; margin: 0 auto; padding: 80px 26px; text-align: center; }
  .sec + .sec { padding-top: 0; }
  .title { font-size: 1.45rem; color: var(--ink); margin: 0; line-height: 1.35; font-weight: 500; font-style: normal; text-transform: uppercase; letter-spacing: .22em; }
  .orn { display: block; margin: 8px auto 26px; width: 150px; height: 14px; }
  .orn path, .orn line { stroke: var(--gold); fill: none; stroke-width: 1; }
  .orn .d { fill: var(--gold); stroke: none; }
  .basmala { font-family: "Aref Ruqaa", serif; font-size: 1.5rem; color: var(--gold); margin: 0 0 18px; direction: rtl; }
  .lead { margin: 0; color: var(--ink-soft); }
  .couple { color: var(--ink); font-size: 2.1rem; font-style: normal; font-weight: 500; text-transform: uppercase; letter-spacing: .14em; margin: 14px 0 6px; line-height: 1.15; }
  .warm { font-style: italic; font-size: 1.35rem; margin: 0 0 16px; }
  .couple-art { width: min(360px, 96%); margin: 6px auto 0; }
  .couple-art img { width: 100%; height: auto; display: block;
    -webkit-mask-image: radial-gradient(farthest-side, #000 90%, transparent 100%); mask-image: radial-gradient(farthest-side, #000 90%, transparent 100%); }
  @media (prefers-color-scheme: light) { .couple-art img { mix-blend-mode: multiply; } }
  .couple-art img.uploaded { mix-blend-mode: normal; aspect-ratio: 1; object-fit: cover; border-radius: 50%; }
  .person { margin: 18px 0 0; }
  .dear { margin: 0 0 12px; font-size: 1.45rem; font-style: italic; color: var(--ink); }
  .prev-reply { margin: 0 0 16px; padding: 10px 12px; border-radius: 10px; background: var(--paper-2); color: var(--ink); font-size: .98rem; line-height: 1.45; }
  .person .couple { margin: 0; line-height: 1.1; }
  .arname { font-family: "Aref Ruqaa", serif; font-size: 2.1rem; color: var(--gold); margin: 0; line-height: 1.4; direction: rtl; }
  .parents { margin: 2px 0 0; color: var(--ink-soft); }
  .parents small { display: block; letter-spacing: .2em; font-size: .85rem; }
  .verse { font-family: "Aref Ruqaa", serif; font-size: 1.55rem; color: var(--gold); direction: rtl; margin: 30px 0 0; line-height: 1.7; }
  #whenLine { margin-top: 26px; }
  .note { display: inline-block; margin-top: 18px; padding: 6px 16px; border: 1px solid var(--gold-soft); border-radius: 30px; font-size: .95rem; color: var(--ink-soft); }

  .cd { display: flex; justify-content: center; align-items: flex-start; gap: 6px; font-variant-numeric: lining-nums tabular-nums; }
  .cd div { min-width: 64px; }
  .cd b { display: block; font-weight: 500; font-size: 2.9rem; line-height: 1; color: var(--gold); }
  .cd span { font-size: .78rem; letter-spacing: .18em; text-transform: uppercase; color: var(--ink-soft); }
  .cd i { font-style: normal; font-size: 2.4rem; line-height: 1; color: var(--gold-soft); }

  .timeline { list-style: none; margin: 0 auto; padding: 0; max-width: 360px; position: relative; }
  .timeline::before { content: ""; position: absolute; left: 50%; top: 14px; bottom: 14px; width: 1px; background: var(--gold-soft); }
  .timeline li { display: grid; grid-template-columns: 1fr 34px 1fr; align-items: center; padding: 14px 0; }
  .timeline .tm { text-align: right; font-size: 1.45rem; font-weight: 500; }
  .timeline .ev { text-align: left; color: var(--ink-soft); line-height: 1.25; }
  .timeline .dot { justify-self: center; width: 9px; height: 9px; background: var(--gold); transform: rotate(45deg); position: relative; z-index: 1; box-shadow: 0 0 0 5px var(--paper); }
  .timeline li.hl .dot { width: 18px; height: 18px; border-radius: 50%; transform: none; background: radial-gradient(circle at 35% 30%, #FFFFFF, #CFC6B8 80%); box-shadow: 0 0 0 5px var(--paper), 0 0 0 6px var(--gold-soft); }

  .venue-photo {
    width: 100%; max-width: 360px; margin: 0 auto 24px; aspect-ratio: 4 / 4.6; overflow: hidden;
    border-radius: 200px 200px 14px 14px; outline: 1px solid var(--gold); outline-offset: 6px;
  }
  .venue-photo svg, .venue-photo img { width: 100%; height: 100%; display: block; object-fit: cover; }
  .vname { font-size: 1.7rem; font-weight: 600; margin: 0; }
  .vwhen { margin: 4px 0 2px; }
  .vaddr { margin: 0 0 22px; color: var(--ink-soft); }

  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    font: inherit; font-size: 1.05rem; font-weight: 600; letter-spacing: .04em;
    padding: 13px 26px; border-radius: 40px; border: 1px solid var(--gold); color: var(--ink);
    background: transparent; text-decoration: none; cursor: pointer;
  }
  .btn svg { width: 18px; height: 18px; }
  .btn:focus-visible, button:focus-visible, input:focus-visible { outline: 2px solid var(--gold); outline-offset: 3px; }
  .btn.solid { background: var(--velvet); color: var(--cream); border-color: var(--velvet); width: 100%; }

  .mini-env {
    position: relative; width: min(300px, 86%); aspect-ratio: 3 / 2.1; margin: 26px auto 0;
    border: 0; padding: 0; cursor: pointer; border-radius: 6px; overflow: visible; background: var(--velvet);
    box-shadow: 0 14px 30px rgba(58,14,21,.3);
  }
  .mini-env .pattern { border-radius: 6px; overflow: hidden; }
  .mini-env .lines path { stroke-width: 1; }
  .mini-env .ms { position: absolute; left: 50%; top: 52%; width: 34%; aspect-ratio: 1; transform: translate(-50%, -50%); filter: drop-shadow(0 6px 10px rgba(0,0,0,.35)); transition: transform .3s ease; }
  .mini-env:hover .ms { transform: translate(-50%, -50%) scale(1.06); }
  .mini-env .ms svg { width: 100%; height: 100%; display: block; }
  .open-hint { margin: 16px 0 0; font-size: 1.2rem; color: var(--ink-soft); }
  .open-hint svg { display: block; margin: 0 auto 2px; width: 16px; height: 10px; }

  .farewell { font-size: 2.1rem; margin: 0; }
  .sign { letter-spacing: .3em; text-transform: uppercase; font-size: .9rem; margin: 6px 0 0; }

  /* ================= RSVP DIALOG ================= */
  dialog {
    width: min(460px, calc(100% - 24px)); max-height: calc(100% - 40px); border: 1px solid var(--gold-soft); border-radius: 18px;
    padding: 30px 24px 26px; background: var(--paper); color: var(--ink); overflow-y: auto;
  }
  dialog::backdrop { background: rgba(30,36,26,.55); backdrop-filter: blur(3px); }
  dialog[open] { animation: rise .45s cubic-bezier(.2,.7,.3,1); }
  @keyframes rise { from { transform: translateY(30px); opacity: 0; } }
  .x { position: absolute; top: 10px; right: 10px; width: 38px; height: 38px; border: 0; border-radius: 50%; background: transparent; color: var(--ink); font-size: 1.6rem; cursor: pointer; }
  dialog h3 { margin: 0; text-align: center; font-weight: 500; font-size: 1.7rem; }
  dialog .sub { text-align: center; color: var(--ink-soft); margin: 2px 0 22px; font-style: italic; }
  .fl { display: block; margin: 0 0 4px; font-weight: 600; }
  .in {
    width: 100%; font: inherit; color: var(--ink); background: transparent; border: 0;
    border-bottom: 1px solid var(--gold-soft); padding: 8px 2px; margin-bottom: 20px; border-radius: 0;
  }
  .radios { display: grid; gap: 8px; margin: 6px 0 20px; }
  .radio { display: flex; align-items: center; gap: 12px; cursor: pointer; }
  .radio input { appearance: none; -webkit-appearance: none; width: 20px; height: 20px; border: 1px solid var(--gold); border-radius: 50%; margin: 0; display: grid; place-items: center; flex: none; }
  .radio input::after { content: ""; width: 10px; height: 10px; border-radius: 50%; background: var(--gold); transform: scale(0); transition: transform .2s; }
  .radio input:checked::after { transform: scale(1); }
  .counter { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
  .stepper { display: flex; align-items: center; gap: 14px; }
  .stepper button { width: 38px; height: 38px; border-radius: 50%; font-size: 1.3rem; line-height: 1; cursor: pointer; border: 1px solid var(--gold); background: transparent; color: var(--ink); }
  .stepper button:disabled { opacity: .35; cursor: default; }
  .stepper output { min-width: 2ch; text-align: center; font-size: 1.5rem; font-weight: 600; }
  .hint { color: var(--ink-soft); font-size: .9rem; margin: 0 0 16px; font-style: italic; }
  .err { color: var(--err); min-height: 1.4em; margin: 10px 0 0; text-align: center; font-size: .98rem; }
  .done { text-align: center; color: var(--ink); margin: 18px 0 6px; font-size: 1.35rem; font-style: italic; line-height: 1.5; }
  .hidden { display: none !important; }

  /* ================= MUSIC ================= */
  .music-wrap { position: fixed; right: max(0px, calc(50% - 220px)); bottom: 0; z-index: 40; padding: 0 16px calc(16px + env(safe-area-inset-bottom, 0px)) 0; transition: opacity .5s ease 1.1s; }
  body.sealed .music-wrap { opacity: 0; pointer-events: none; }
  .music { width: 54px; height: 54px; border-radius: 50%; border: 1px solid var(--olive); background: var(--olive); color: var(--cream); cursor: pointer; display: grid; place-items: center; box-shadow: 0 6px 16px rgba(0,0,0,.25); }
  .music svg { width: 20px; height: 20px; }

  /* ================= MOTION ================= */
  .reveal { opacity: 0; transform: translateY(24px); transition: opacity .9s ease, transform .9s ease; }
  .reveal.on { opacity: 1; transform: none; }
  @media (prefers-reduced-motion: no-preference) {
    .seal-btn svg { animation: breathe 2.8s ease-in-out infinite; }
    .tap svg, .scroll svg, .open-hint svg { animation: nudge 1.8s ease-in-out infinite; }
    .spark { animation: drift linear infinite; }
    .mini-env.nudge { animation: tapme 1.4s ease-in-out 4; }
  }
  @media (prefers-reduced-motion: reduce) {
    .intro, .flap-top, .glow, .seal-btn, .music-wrap { transition-duration: .01s !important; transition-delay: 0s !important; }
    .reveal { opacity: 1; transform: none; }
    .spark { display: none; }
    html { scroll-behavior: auto; }
  }
  @keyframes breathe { 50% { transform: scale(1.04); } }
  @keyframes tapme { 50% { transform: scale(1.05); } }
  @keyframes nudge { 50% { transform: translateY(-4px); } }
  @keyframes drift { 0% { transform: translateY(0); opacity: 0; } 15% { opacity: .8; } 100% { transform: translateY(-80vh); opacity: 0; } }
</style>
</head>
<body class="sealed">

<!-- ========== ENVELOPE ========== -->
<div class="intro" id="intro">
  <div class="velvet back"></div>
  <div class="glow"></div>
  <div class="flap flap-side"><div class="pattern"></div></div>
  <div class="flap flap-side r"><div class="pattern"></div></div>
  <div class="flap flap-bottom"><div class="pattern"></div></div>
  <svg class="lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0 0 L50 56 L100 0" vector-effect="non-scaling-stroke"/>
    <path d="M0 100 L50 56 L100 100" vector-effect="non-scaling-stroke"/>
  </svg>
  <div class="flap flap-top"><div class="pattern"></div></div>
  <p class="from"><span class="script" id="fromNames"></span><small>Wedding invitation</small></p>
  <button class="seal-btn" id="seal" aria-label="Open the invitation"></button>
  <p class="tap"><svg viewBox="0 0 16 10" aria-hidden="true"><path d="M1 9 L8 2 L15 9" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>Tap to open</p>
</div>

<main>
  <!-- ========== HERO ========== -->
  <section class="hero" id="top">
    <div class="pattern" id="heroPattern"></div>
    <div class="garland" id="garland"></div>
    <div class="sprig l" id="sprigL"></div><div class="sprig r" id="sprigR"></div>
    <svg class="arch" viewBox="0 0 200 400" preserveAspectRatio="none" aria-hidden="true">
      <path class="fill" d="M14 400 L14 140 C14 82 58 44 100 16 C142 44 186 82 186 140 L186 400 Z"/>
      <path class="ln" d="M14 400 L14 140 C14 82 58 44 100 16 C142 44 186 82 186 140 L186 400"/>
      <path class="ln2" d="M24 400 L24 144 C24 90 64 56 100 30 C136 56 176 90 176 144 L176 400"/>
    </svg>
    <div class="hero-in">
      <p class="dear" id="dear" hidden></p>
      <p class="script wd">Joyfully invite you<br>to their wedding</p>
      <p class="dt" id="shortDate"></p>
      <h1 class="names script"><span id="n1"></span><span class="amp">&amp;</span><span id="n2"></span></h1>
      <a class="scroll script" href="#invite">Scroll down
        <svg viewBox="0 0 22 12" aria-hidden="true"><path d="M1 1 L11 11 L21 1" fill="none" stroke="currentColor" stroke-width="1.2"/></svg></a>
    </div>
  </section>

  <!-- ========== INVITATION ========== -->
  <section class="sec reveal" id="invite">
    <p class="basmala">بسم الله الرحمن الرحيم</p>
    <p class="lead" id="host"></p>
    <p class="warm" id="joinLine"></p>
    <div class="couple-art" id="coupleArt"></div>
    <div class="person">
      <p class="couple script" id="gName"></p>
      <p class="arname" id="gAr"></p>
      <p class="parents"><small>s/o</small><span id="gParents"></span></p>
    </div>
    <svg class="orn" viewBox="0 0 150 14" aria-hidden="true"><line x1="0" y1="7" x2="62" y2="7"/><path class="d" d="M75 1 L81 7 L75 13 L69 7 Z"/><line x1="88" y1="7" x2="150" y2="7"/></svg>
    <div class="person" style="margin-top:0">
      <p class="couple script" id="bName"></p>
      <p class="arname" id="bAr"></p>
      <p class="parents"><small>d/o</small><span id="bParents"></span></p>
    </div>
    <p class="lead" id="whenLine"></p>
    <p class="verse">وَمِن كُلِّ شَيْءٍ خَلَقْنَا زَوْجَيْنِ لَعَلَّكُمْ تَذَكَّرُونَ</p>
    <span class="note" id="note"></span>
  </section>

  <!-- ========== COUNTDOWN ========== -->
  <section class="sec reveal" id="cdSec">
    <h2 class="title script">The celebration begins in</h2>
    <svg class="orn" viewBox="0 0 150 14" aria-hidden="true"><line x1="0" y1="7" x2="62" y2="7"/><path class="d" d="M75 1 L81 7 L75 13 L69 7 Z"/><line x1="88" y1="7" x2="150" y2="7"/></svg>
    <div class="cd" aria-live="off">
      <div><b id="cd-d">0</b><span>Days</span></div><i>:</i>
      <div><b id="cd-h">0</b><span>Hours</span></div><i>:</i>
      <div><b id="cd-m">0</b><span>Mins</span></div><i>:</i>
      <div><b id="cd-s">0</b><span>Secs</span></div>
    </div>
  </section>

  <!-- ========== SCHEDULE ========== -->
  <section class="sec reveal">
    <h2 class="title script">Schedule of events</h2>
    <svg class="orn" viewBox="0 0 150 14" aria-hidden="true"><line x1="0" y1="7" x2="62" y2="7"/><path class="d" d="M75 1 L81 7 L75 13 L69 7 Z"/><line x1="88" y1="7" x2="150" y2="7"/></svg>
    <ol class="timeline" id="timeline"></ol>
  </section>

  <!-- ========== LOCATION ========== -->
  <section class="sec reveal">
    <h2 class="title script">Location</h2>
    <svg class="orn" viewBox="0 0 150 14" aria-hidden="true"><line x1="0" y1="7" x2="62" y2="7"/><path class="d" d="M75 1 L81 7 L75 13 L69 7 Z"/><line x1="88" y1="7" x2="150" y2="7"/></svg>
    <div class="venue-photo" id="venuePhoto"></div>
    <p class="vname" id="vname"></p>
    <p class="vwhen" id="vwhen"></p>
    <p class="vaddr" id="vaddr"></p>
    <a class="btn" id="mapBtn" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
      Open in Google Maps
    </a>
    <button class="btn" id="calBtn" type="button" style="margin-top:10px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>
      Add to calendar
    </button>
  </section>

  <!-- ========== RSVP ========== -->
  <section class="sec reveal olive-sec">
    <h2 class="title script">Confirm your attendance</h2>
    <svg class="orn" viewBox="0 0 150 14" aria-hidden="true"><line x1="0" y1="7" x2="62" y2="7"/><path class="d" d="M75 1 L81 7 L75 13 L69 7 Z"/><line x1="88" y1="7" x2="150" y2="7"/></svg>
    <p class="lead" id="rsvpLead"></p>
    <button class="mini-env" id="openRsvp" aria-label="Open the RSVP card">
      <div class="pattern"></div>
      <svg class="lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 0 L50 52 L100 0" vector-effect="non-scaling-stroke"/>
        <path d="M0 100 L50 52 L100 100" vector-effect="non-scaling-stroke"/>
      </svg>
      <span class="ms" id="rsvpSeal"></span>
    </button>
    <p class="open-hint script"><svg viewBox="0 0 16 10" aria-hidden="true"><path d="M1 9 L8 2 L15 9" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>Tap to open</p>
  </section>

  <section class="sec reveal olive-tail">
    <p class="farewell script">Hope to see you there!</p>
    <p class="sign" id="sign"></p>
  </section>
</main>

<!-- ========== RSVP FORM ========== -->
<dialog id="rsvp" aria-labelledby="rsvpTitle">
  <button class="x" id="closeRsvp" aria-label="Close">×</button>
  <h3 id="rsvpTitle">Confirm your attendance</h3>
  <p class="sub" id="rsvpBy"></p>

  <div id="rsvpForm">
  <p class="prev-reply hidden" id="prevReply"></p>
  <span class="fl">Will you be attending?</span>
  <div class="radios" role="radiogroup">
    <label class="radio"><input type="radio" name="att" value="yes"> Accepts with pleasure</label>
    <label class="radio"><input type="radio" name="att" value="no"> Declines with regret</label>
  </div>

  <div id="attendBlock" class="hidden">
    <div class="counter">
      <span class="fl" style="margin:0">Number of guests</span>
      <div class="stepper">
        <button type="button" id="minus" aria-label="Fewer guests">−</button>
        <output id="count">1</output>
        <button type="button" id="plus" aria-label="More guests">+</button>
      </div>
    </div>
    <p class="hint" id="seatHint">Including yourself</p>
    <div id="guests"></div>
  </div>

  <button class="btn solid" id="send" type="button">Send RSVP</button>
  <p class="err" id="err" role="alert"></p>
  </div>
  <p class="done hidden" id="done"></p>
</dialog>

<div class="music-wrap">
  <button class="music" id="music" aria-pressed="false" aria-label="Play or pause music"></button>
</div>

<script>/*__CONFIG__*/</script>
<script>
/* ======================= Edit your details here ======================= */
const WEDDING = {
  host: "In a moment filled with joy and gratitude, we invite you to share in our happiness as we begin our new journey together.",
  join: "Join us as we celebrate our nikah",
  groom: "Fahim",
  groomFull: "Fahim Hamza",
  groomArabic: "فهيم",
  groomParents: "Hamza and Rahiyanath",
  bride: "Marwa",
  brideFull: "Marwa Rafeek",
  brideArabic: "مروة",
  brideParents: "Rafeek and Souda",
  dayName: "Thursday",
  dateLong: "19 November 2026",
  shortDate: "19 . 11 . 26",
  startTime: "7:30 PM onwards",
  startsAt: "2026-11-19T19:30:00+03:00",          // countdown target (Bahrain time)
  schedule: [],                                     // add moments here to show the schedule section
  venue: "Adhari New Seasons",
  venueAddress: "Hall No. 1",
  mapLink: "https://www.google.com/maps/search/?api=1&query=Adhari%20New%20Seasons%20Bahrain",
  venuePhoto: "",                                   // photo of the venue; empty shows the illustration
  note: "",
  rsvpBy: "",
  apiUrl: "?api=rsvp",                              // the RSVP endpoint of this system
  maxGuests: 6
};
/* Details saved from the dashboard override the defaults above */
const SAVED = (window.WEDDING_CONFIG && window.WEDDING_CONFIG.details) || {};
Object.keys(SAVED).forEach(k => { if (SAVED[k] !== "" && SAVED[k] != null) WEDDING[k] = k === "maxGuests" ? Number(SAVED[k]) : SAVED[k]; });
if ("note" in SAVED) WEDDING.note = SAVED.note;
if ("rsvpBy" in SAVED) WEDDING.rsvpBy = SAVED.rsvpBy;
const PHOTOS = (window.WEDDING_CONFIG && window.WEDDING_CONFIG.photos) || {};
/* ===================================================================== */

const $ = id => document.getElementById(id);
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const initials = `${WEDDING.groom[0]}&${WEDDING.bride[0]}`;

/* ---------- Fill text ---------- */
$("fromNames").textContent = `${WEDDING.groom} & ${WEDDING.bride}`;
$("shortDate").textContent = WEDDING.shortDate;
$("n1").textContent = WEDDING.groom;
$("n2").textContent = WEDDING.bride;
$("host").textContent = WEDDING.host;
$("joinLine").textContent = WEDDING.join;
$("gName").textContent = WEDDING.groom;
$("gAr").textContent = WEDDING.groomArabic;
$("gParents").textContent = WEDDING.groomParents;
$("bName").textContent = WEDDING.bride;
$("bAr").textContent = WEDDING.brideArabic;
$("bParents").textContent = WEDDING.brideParents;
$("whenLine").textContent = `${WEDDING.dayName}, ${WEDDING.dateLong}, ${WEDDING.startTime}`;
if (WEDDING.note) $("note").textContent = WEDDING.note; else $("note").remove();
$("vname").textContent = WEDDING.venue;
$("vwhen").textContent = `${WEDDING.dayName}, ${WEDDING.dateLong} at ${WEDDING.startTime}`;
$("vaddr").textContent = WEDDING.venueAddress;
$("mapBtn").href = WEDDING.mapLink;
$("rsvpLead").textContent = WEDDING.rsvpBy ? `To help us prepare for this celebration, kindly reply by ${WEDDING.rsvpBy}.` : "To help us prepare for this celebration, kindly confirm your attendance.";
$("rsvpBy").textContent = WEDDING.rsvpBy ? `Kindly reply by ${WEDDING.rsvpBy}` : "We would love to know if you can join us";
$("sign").textContent = `${WEDDING.groom} & ${WEDDING.bride}`;

/* ---------- Geometric pattern (8-point stars) ---------- */
function patternURI(color, op) {
  const s = `<svg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'>
    <g fill='none' stroke='${color}' stroke-opacity='${op}' stroke-width='1'>
      <rect x='18' y='18' width='20' height='20'/>
      <rect x='18' y='18' width='20' height='20' transform='rotate(45 28 28)'/>
      <circle cx='28' cy='28' r='4'/>
      <path d='M0 0 L8 8 M56 0 L48 8 M0 56 L8 48 M56 56 L48 48'/>
    </g></svg>`;
  return `url("data:image/svg+xml,${encodeURIComponent(s)}")`;
}
function grainURI(op) {
  const s = `<svg xmlns='http://www.w3.org/2000/svg' width='240' height='240'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 .42 0 0 0 0 .36 0 0 0 0 .26 0 0 0 1 0'/></filter><rect width='100%' height='100%' filter='url(#n)' opacity='${op}'/></svg>`;
  return `url("data:image/svg+xml,${encodeURIComponent(s)}")`;
}
document.querySelectorAll(".intro .pattern, .mini-env .pattern").forEach(p => p.style.backgroundImage = grainURI(.22));
const heroPat = $("heroPattern");
function setHeroPattern() {
  const c = getComputedStyle(document.documentElement).getPropertyValue("--gold").trim() || "#9E7B34";
  heroPat.style.backgroundImage = grainURI(.12);
}
setHeroPattern();
window.matchMedia("(prefers-color-scheme: dark)").addEventListener?.("change", setHeroPattern);

/* ---------- Wax seal ---------- */
let sealId = 0;
function sealSVG(text, c) {
  const id = "w" + (sealId++);
  let d = "";
  for (let i = 0; i <= 90; i++) {
    const t = i / 90 * Math.PI * 2;
    const r = 45 + 2.6 * Math.sin(t * 9 + .4) + 1.3 * Math.sin(t * 4 + 1.7) + .8 * Math.sin(t * 17);
    d += (i ? "L" : "M") + (Math.cos(t) * r).toFixed(2) + " " + (Math.sin(t) * r).toFixed(2);
  }
  return `<svg viewBox="-50 -50 100 100" aria-hidden="true">
    <defs>
      <radialGradient id="${id}a" cx="35%" cy="30%" r="80%"><stop offset="0" stop-color="${c.hi}"/><stop offset=".6" stop-color="${c.mid}"/><stop offset="1" stop-color="${c.lo}"/></radialGradient>
      <radialGradient id="${id}b" cx="60%" cy="65%" r="70%"><stop offset="0" stop-color="${c.mid}"/><stop offset="1" stop-color="${c.hi}"/></radialGradient>
    </defs>
    <path d="${d}Z" fill="url(#${id}a)"/>
    <circle r="32" fill="url(#${id}b)"/>
    <circle r="32" fill="none" stroke="${c.ink}" stroke-opacity=".45" stroke-width="1"/>
    <circle r="28.5" fill="none" stroke="${c.ink}" stroke-opacity=".25" stroke-width=".6"/>
    <text x="0" y="${c.dy}" text-anchor="middle" font-family="${c.font}" font-size="${c.size}" fill="${c.ink}" fill-opacity=".85">${text}</text>
  </svg>`;
}
$("seal").innerHTML = sealSVG(initials, { hi: "#FFF9EE", mid: "#EADBC2", lo: "#C9B08A", ink: "#5E6845", font: "'Pinyon Script', cursive", size: 25, dy: 8 });
$("rsvpSeal").innerHTML = sealSVG("RSVP", { hi: "#9AA578", mid: "#5E6A42", lo: "#3B4428", ink: "#F1EBDA", font: "'Cormorant Garamond', serif", size: 15, dy: 5 });

/* ---------- Greenery ---------- */
function leafy(seed, build) {
  let x = seed; const r = () => (x = (x * 16807) % 2147483647) / 2147483647;
  const greens = ["#5F6B43", "#7E8C5A", "#9AA77A", "#B7C19A", "#6E7A50"];
  const leaf = (lx, ly, ang, sz) => `<ellipse cx="${lx.toFixed(1)}" cy="${ly.toFixed(1)}" rx="${(10 * sz).toFixed(1)}" ry="${(4.2 * sz).toFixed(1)}" transform="rotate(${ang.toFixed(1)} ${lx.toFixed(1)} ${ly.toFixed(1)})" fill="${greens[Math.floor(r() * greens.length)]}" opacity="${(0.75 + r() * 0.25).toFixed(2)}"/>`;
  const flower = (fx, fy, sz) => { let o = ""; for (let k = 0; k < 5; k++) { const a = k / 5 * Math.PI * 2; o += `<circle cx="${(fx + Math.cos(a) * 3.2 * sz).toFixed(1)}" cy="${(fy + Math.sin(a) * 3.2 * sz).toFixed(1)}" r="${(2.6 * sz).toFixed(1)}" fill="#FFFDF6"/>`; } return o + `<circle cx="${fx.toFixed(1)}" cy="${fy.toFixed(1)}" r="${(1.3 * sz).toFixed(1)}" fill="#C9B77A"/>`; };
  return build({ r, leaf, flower });
}
function vineAlong(k, pts, density) {
  let stem = "", leaves = "";
  stem += "M" + pts.map(p => p.join(" ")).join(" L");
  for (let i = 1; i < pts.length; i++) {
    const [x0, y0] = pts[i - 1], [x1, y1] = pts[i];
    const ang = Math.atan2(y1 - y0, x1 - x0) * 180 / Math.PI;
    for (let j = 0; j < density; j++) {
      const u = (j + k.r()) / density, lx = x0 + (x1 - x0) * u, ly = y0 + (y1 - y0) * u;
      const side = (i + j) % 2 ? 1 : -1;
      leaves += k.leaf(lx + side * 4, ly + side * 3, ang + side * (40 + k.r() * 25), 0.8 + k.r() * 0.6);
    }
  }
  return `<path d="${stem}" fill="none" stroke="#6E7A50" stroke-width="1.2" opacity=".7"/>` + leaves;
}
$("garland").innerHTML = leafy(7, k => {
  const pts = []; for (let i = 0; i <= 24; i++) { const u = i / 24; pts.push([u * 420 - 10, 14 + Math.sin(u * Math.PI) * 46 + Math.sin(u * 9) * 4]); }
  let o = vineAlong(k, pts, 3);
  [60, 120, 200, 280, 345].forEach((hx, n) => {
    const top = 14 + Math.sin((hx + 10) / 420 * Math.PI) * 46, len = 30 + (n % 3) * 26; const d = [];
    for (let i = 0; i <= 5; i++) d.push([hx + Math.sin(i * .9 + n) * 6, top + i / 5 * len]);
    o += vineAlong(k, d, 2);
  });
  for (let i = 0; i < 9; i++) { const u = 0.06 + i * 0.11 + k.r() * 0.03; o += k.flower(u * 420 - 10, 14 + Math.sin(u * Math.PI) * 46 + (k.r() - .5) * 10, 0.9 + k.r() * 0.5); }
  return `<svg viewBox="0 0 400 150" aria-hidden="true">${o}</svg>`;
});
const sprig = seed => leafy(seed, k => {
  let o = "";
  [[[10, 210], [40, 160], [62, 110], [70, 60], [66, 20]], [[0, 200], [50, 178], [100, 150], [140, 130]], [[20, 210], [80, 196], [130, 186]]].forEach(path => { o += vineAlong(k, path, 4); });
  for (let i = 0; i < 4; i++) o += k.flower(40 + k.r() * 90, 110 + k.r() * 80, 0.9 + k.r() * 0.5);
  return `<svg viewBox="0 0 200 210" aria-hidden="true">${o}</svg>`;
});
$("sprigL").innerHTML = sprig(3); $("sprigR").innerHTML = sprig(11);
$("coupleArt").innerHTML = `<img src="data:image/jpeg;base64,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" alt="Illustration of the bride and groom inside a wreath of leaves and white flowers" width="760" height="716">`;
/* Photos uploaded from the dashboard replace the built-in artwork */
{
  const use = (box, url, alt) => { const img = new Image(); img.onload = () => { box.innerHTML = ""; img.alt = alt; img.className = "uploaded"; box.appendChild(img); }; img.src = url; };
  if (PHOTOS.couple) use($("coupleArt"), PHOTOS.couple, "The bride and groom");
  if (PHOTOS.venue) use($("venuePhoto"), PHOTOS.venue, WEDDING.venue);
}

/* ---------- Add to calendar ---------- */
$("calBtn").onclick = () => {
  const s = new Date(WEDDING.startsAt), e = new Date(s.getTime() + 4 * 3600e3);
  const f = d => d.toISOString().replace(/[-:]/g, "").replace(/\.\d{3}/, "");
  const ics = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Wedding Invitation//EN", "BEGIN:VEVENT",
    "UID:" + f(s) + "@wedding-invitation", "DTSTAMP:" + f(new Date()), "DTSTART:" + f(s), "DTEND:" + f(e),
    "SUMMARY:Wedding of " + WEDDING.groom + " & " + WEDDING.bride,
    "LOCATION:" + WEDDING.venue + (WEDDING.venueAddress ? ", " + WEDDING.venueAddress : ""),
    "END:VEVENT", "END:VCALENDAR"].join("\r\n");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([ics], { type: "text/calendar" }));
  a.download = "wedding.ics"; document.body.appendChild(a); a.click(); a.remove();
};

/* ---------- Sparkles ---------- */
if (!reduceMotion) {
  for (let i = 0; i < 16; i++) {
    const s = document.createElement("span"); s.className = "spark";
    s.style.left = (5 + Math.random() * 90) + "%";
    s.style.animationDuration = (9 + Math.random() * 8) + "s";
    s.style.animationDelay = (Math.random() * 10) + "s";
    const z = 2 + Math.random() * 3; s.style.width = s.style.height = z + "px";
    document.querySelector(".hero").appendChild(s);
  }
}

/* ---------- Countdown ---------- */
const target = new Date(WEDDING.startsAt).getTime();
function tick() {
  let diff = Math.max(0, target - Date.now());
  const d = Math.floor(diff / 864e5); diff -= d * 864e5;
  const h = Math.floor(diff / 36e5); diff -= h * 36e5;
  const m = Math.floor(diff / 6e4); diff -= m * 6e4;
  $("cd-d").textContent = d;
  $("cd-h").textContent = String(h).padStart(2, "0");
  $("cd-m").textContent = String(m).padStart(2, "0");
  $("cd-s").textContent = String(Math.floor(diff / 1e3)).padStart(2, "0");
}
if (isNaN(target)) $("cdSec").remove(); else { tick(); setInterval(tick, 1000); }

/* ---------- Schedule ---------- */
WEDDING.schedule.forEach(e => {
  const li = document.createElement("li"); if (e.highlight) li.className = "hl";
  const tm = document.createElement("span"); tm.className = "tm"; tm.textContent = e.time;
  const dot = document.createElement("span"); dot.className = "dot";
  const ev = document.createElement("span"); ev.className = "ev"; ev.textContent = e.title;
  li.append(tm, dot, ev); $("timeline").appendChild(li);
});
if (!WEDDING.schedule.length) $("timeline").closest("section").remove();

/* ---------- Venue photo / illustration ---------- */
if (WEDDING.venuePhoto) {
  const img = document.createElement("img"); img.src = WEDDING.venuePhoto; img.alt = WEDDING.venue;
  $("venuePhoto").appendChild(img);
} else {
  $("venuePhoto").innerHTML = leafy(21, k => {
    let o = `<defs>
      <linearGradient id="vbg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ECE8DA"/><stop offset="1" stop-color="#D5DAC1"/></linearGradient>
      <radialGradient id="vlit" cx="50%" cy="75%" r="75%"><stop offset="0" stop-color="#FFFCF4"/><stop offset=".65" stop-color="#F7EDD7"/><stop offset="1" stop-color="#EBDDBD"/></radialGradient>
      <radialGradient id="vglo" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#FFF3CF" stop-opacity=".9"/><stop offset="1" stop-color="#FFF3CF" stop-opacity="0"/></radialGradient>
    </defs>`;
    o += `<rect width="400" height="460" fill="url(#vbg)"/>`;
    o += `<path d="M0 384 L400 384 L400 460 L0 460 Z" fill="#E6E0CE"/>`;
    o += `<path d="M110 384 L110 214 A90 90 0 0 1 290 214 L290 384 Z" fill="url(#vlit)"/>`;
    o += `<path d="M176 384 L224 384 L262 460 L138 460 Z" fill="#F5F0E3"/>`;
    // fairy lights inside the archway
    [0, 1, 2, 3].forEach(j => {
      const y0 = 176 + j * 40, half = j === 0 ? 62 : 84;
      let d = "", dots = "";
      for (let i = 0; i <= 14; i++) {
        const u = i / 14, x = 200 - half + u * half * 2, y = y0 + Math.sin(u * Math.PI) * 16;
        d += (i ? " L" : "M") + x.toFixed(1) + " " + y.toFixed(1);
        dots += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="6" fill="url(#vglo)"/><circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="1.8" fill="#FFF6DA"/>`;
      }
      o += `<path d="${d}" fill="none" stroke="#C8BB94" stroke-width=".6"/>` + dots;
    });
    // the arch frame
    const frame = "M96 386 L96 214 A104 104 0 0 1 304 214 L304 386";
    o += `<path d="${frame}" fill="none" stroke="#C3B898" stroke-width="12"/><path d="${frame}" fill="none" stroke="#F8F4EB" stroke-width="7"/>`;
    // greenery climbing the frame
    const arc = [];
    for (let i = 0; i <= 26; i++) { const a = Math.PI - i / 26 * Math.PI; arc.push([200 + Math.cos(a) * 104, 214 - Math.sin(a) * 104]); }
    o += vineAlong(k, [[98, 392], [93, 340], [97, 290], [95, 250]].concat(arc.slice(0, 17)), 4);
    o += vineAlong(k, [[302, 392], [307, 350], [303, 310], [305, 270]].concat(arc.slice(22).reverse().map(p => p)).slice(0, 6), 4);
    o += vineAlong(k, [[60, 396], [80, 372], [100, 360]], 4) + vineAlong(k, [[340, 396], [320, 372], [300, 362]], 4);
    for (let i = 0; i < 9; i++) { const q = arc[Math.min(16, Math.floor(k.r() * 17))]; o += k.flower(q[0] + (k.r() - .5) * 14, q[1] + (k.r() - .5) * 14, 1.1 + k.r() * .7); }
    for (let i = 0; i < 5; i++) o += k.flower(84 + k.r() * 26, 300 + k.r() * 90, 1 + k.r() * .6);
    for (let i = 0; i < 4; i++) o += k.flower(294 + k.r() * 24, 320 + k.r() * 70, 1 + k.r() * .6);
    // petals on the aisle
    for (let i = 0; i < 16; i++) {
      const y = 392 + k.r() * 64, w = 26 + (y - 384) * 0.9, x = 200 + (k.r() - .5) * w * 2;
      o += `<ellipse cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" rx="3" ry="1.6" transform="rotate(${(k.r() * 180).toFixed(0)} ${x.toFixed(1)} ${y.toFixed(1)})" fill="#FFFFFF" opacity=".9"/>`;
    }
    return `<svg viewBox="0 0 400 460" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Illustration of a flower-framed archway">${o}</svg>`;
  });
}

/* ---------- Reveal on scroll ---------- */
const io = "IntersectionObserver" in window ? new IntersectionObserver(es => es.forEach(e => {
  if (e.isIntersecting) { e.target.classList.add("on"); io.unobserve(e.target); }
}), { threshold: .15 }) : null;
document.querySelectorAll(".reveal").forEach(el => io ? io.observe(el) : el.classList.add("on"));

/* ---------- Music: an original, gentle Arabic theme in maqam Nahawand (nay, qanun, oud, strings), synthesized live ---------- */
const Music = (() => {
  let ctx, master, noise, timer, nextBar = 0, bar = 0, playing = false;
  const SEMI = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };
  const hz = n => { const m = n.match(/^([A-G])(#|b)?(\d)$/); return 440 * Math.pow(2, ((+m[3] + 1) * 12 + SEMI[m[1]] + (m[2] === "#" ? 1 : m[2] === "b" ? -1 : 0) - 69) / 12); };
  const BEAT = 60 / 64, BAR = BEAT * 4;
  const CH = [["D3","A3","D4","F4"], ["G2","D3","G3","Bb3"], ["A2","E3","A3","C#4"], ["D3","A3","D4","F4"], ["Bb2","F3","Bb3","D4"], ["G2","D3","G3","Bb3"], ["A2","E3","A3","C#4"], ["D3","A3","D4","F4"]];
  // melody: [note, beats]
  const MEL = [
    [["A4",2],["D5",1],["C#5",.5],["D5",.5]],
    [["E5",1],["D5",1],["Bb4",2]],
    [["C#5",1],["Bb4",.5],["A4",.5],["G4",1],["A4",1]],
    [["F4",1],["E4",1],["D4",2]],
    [["F4",1],["G4",.5],["A4",.5],["Bb4",1],["D5",1]],
    [["Bb4",1],["A4",.5],["G4",.5],["D5",2]],
    [["C#5",1],["E5",1],["D5",.5],["C#5",.5],["A4",1]],
    [["D5",3],[null,1]]
  ];
  function build() {
    ctx = new (window.AudioContext || window.webkitAudioContext)();
    master = ctx.createGain(); master.gain.value = 0;
    const conv = ctx.createConvolver(), len = ctx.sampleRate * 3.8, ir = ctx.createBuffer(2, len, ctx.sampleRate);
    for (let c = 0; c < 2; c++) { const ch = ir.getChannelData(c); for (let i = 0; i < len; i++) ch[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 2.2); }
    conv.buffer = ir;
    const wet = ctx.createGain(); wet.gain.value = .6;
    const comp = ctx.createDynamicsCompressor();
    master.connect(comp); comp.connect(ctx.destination); master.connect(conv); conv.connect(wet); wet.connect(comp);
    noise = ctx.createBuffer(1, ctx.sampleRate * 2, ctx.sampleRate);
    const nd = noise.getChannelData(0); for (let i = 0; i < nd.length; i++) nd[i] = Math.random() * 2 - 1;
  }
  function pluck(f, t, vol, dur, bright) {
    const o1 = ctx.createOscillator(), o2 = ctx.createOscillator(), lp = ctx.createBiquadFilter(), g = ctx.createGain();
    o1.type = "triangle"; o2.type = bright ? "sawtooth" : "sine"; o1.frequency.value = f; o2.frequency.value = f * 2.001;
    const g2 = ctx.createGain(); g2.gain.value = bright ? .18 : .3;
    lp.type = "lowpass"; lp.frequency.setValueAtTime(bright ? 5200 : 2600, t); lp.frequency.exponentialRampToValueAtTime(600, t + dur);
    g.gain.setValueAtTime(.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + .005); g.gain.exponentialRampToValueAtTime(.0001, t + dur);
    o1.connect(lp); o2.connect(g2); g2.connect(lp); lp.connect(g); g.connect(master);
    o1.start(t); o2.start(t); o1.stop(t + dur + .05); o2.stop(t + dur + .05);
  }
  function nay(f, t, dur, vol) {
    const o = ctx.createOscillator(), o2 = ctx.createOscillator(), g = ctx.createGain(), vib = ctx.createOscillator(), vg = ctx.createGain();
    o.type = "sine"; o2.type = "triangle"; o.frequency.value = f; o2.frequency.value = f;
    vib.frequency.value = 5; vg.gain.setValueAtTime(0, t); vg.gain.linearRampToValueAtTime(f * .006, t + Math.min(.6, dur * .6));
    vib.connect(vg); vg.connect(o.frequency); vg.connect(o2.frequency);
    const mix = ctx.createGain(); mix.gain.value = .35; o2.connect(mix);
    g.gain.setValueAtTime(.0001, t); g.gain.linearRampToValueAtTime(vol, t + .12); g.gain.setValueAtTime(vol, t + dur * .75); g.gain.linearRampToValueAtTime(.0001, t + dur + .25);
    o.connect(g); mix.connect(g); g.connect(master);
    const n = ctx.createBufferSource(), bp = ctx.createBiquadFilter(), ng = ctx.createGain();
    n.buffer = noise; bp.type = "bandpass"; bp.frequency.value = f * 2; bp.Q.value = 3;
    ng.gain.setValueAtTime(.0001, t); ng.gain.linearRampToValueAtTime(vol * .35, t + .06); ng.gain.linearRampToValueAtTime(vol * .08, t + .3); ng.gain.linearRampToValueAtTime(.0001, t + dur + .2);
    n.connect(bp); bp.connect(ng); ng.connect(master);
    [o, o2, vib].forEach(x => { x.start(t); x.stop(t + dur + .3); }); n.start(t, Math.random()); n.stop(t + dur + .3);
  }
  function strings(notes, t, dur, vol) {
    notes.forEach(nm => [-7, 7].forEach(det => {
      const o = ctx.createOscillator(), lp = ctx.createBiquadFilter(), g = ctx.createGain();
      o.type = "sawtooth"; o.frequency.value = hz(nm); o.detune.value = det;
      lp.type = "lowpass"; lp.frequency.value = 950;
      g.gain.setValueAtTime(.0001, t); g.gain.linearRampToValueAtTime(vol, t + .9); g.gain.setValueAtTime(vol, t + dur - .3); g.gain.linearRampToValueAtTime(.0001, t + dur + .8);
      o.connect(lp); lp.connect(g); g.connect(master); o.start(t); o.stop(t + dur + .9);
    }));
  }
  function frame(t, low) {
    if (low) {
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.frequency.setValueAtTime(110, t); o.frequency.exponentialRampToValueAtTime(60, t + .2);
      g.gain.setValueAtTime(.22, t); g.gain.exponentialRampToValueAtTime(.0001, t + .4);
      o.connect(g); g.connect(master); o.start(t); o.stop(t + .45);
    } else {
      const n = ctx.createBufferSource(), bp = ctx.createBiquadFilter(), g = ctx.createGain();
      n.buffer = noise; bp.type = "bandpass"; bp.frequency.value = 3800; bp.Q.value = 1.2;
      g.gain.setValueAtTime(.06, t); g.gain.exponentialRampToValueAtTime(.0001, t + .07);
      n.connect(bp); bp.connect(g); g.connect(master); n.start(t, Math.random()); n.stop(t + .1);
    }
  }
  function scheduleBar(t, b) {
    const i = b % 8, loop = Math.floor(b / 8), c = CH[i];
    strings(c.slice(1), t, BAR, loop === 0 && i < 2 ? .008 : .013);
    pluck(hz(c[0]), t, .2, 2.4, false);
    pluck(hz(c[0]), t + BEAT * 2, .12, 2, false);
    // qanun-like arpeggio in eighths
    const arp = [c[1], c[2], c[3], c[2], c[1], c[2], c[3], c[2]].map(n => hz(n) * 2);
    arp.forEach((f, k) => pluck(f, t + k * BEAT / 2, k % 2 ? .035 : .05, 1.1, true));
    if (loop >= 1 || i >= 4) { frame(t, true); frame(t + BEAT * 1.5, false); frame(t + BEAT * 2.5, true); frame(t + BEAT * 3, false); frame(t + BEAT * 3.5, false); }
    let x = 0;
    MEL[i].forEach(([n, d]) => {
      if (n) {
        const f = hz(n), tt = t + x * BEAT, dd = d * BEAT;
        if (loop % 2 === 0) nay(f, tt, dd * .95, .1);
        else { pluck(f, tt, .17, Math.max(1.2, dd * 1.6), true); if (d >= 2) [.25, .5, .75].forEach(q => pluck(f, tt + q * BEAT, .07, .5, true)); }
      }
      x += d;
    });
  }
  function schedule() {
    while (nextBar < ctx.currentTime + .6) { scheduleBar(nextBar, bar); nextBar += BAR; bar++; }
  }
  return {
    start() {
      try { if (navigator.audioSession) navigator.audioSession.type = "playback"; } catch (e) {}
      if (!ctx) build(); ctx.resume(); if (playing) return;
      playing = true; nextBar = ctx.currentTime + .15;
      master.gain.cancelScheduledValues(ctx.currentTime); master.gain.setTargetAtTime(.8, ctx.currentTime, .8);
      timer = setInterval(schedule, 150); schedule();
    },
    stop() { if (!ctx || !playing) return; playing = false; clearInterval(timer); master.gain.setTargetAtTime(0, ctx.currentTime, .3); },
    get playing() { return playing; }
  };
})();
const ICON_PAUSE = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>`;
const ICON_PLAY = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>`;
const musicBtn = $("music");
function setMusic(on) {
  try { on ? Music.start() : Music.stop(); } catch (e) { on = false; }
  musicBtn.setAttribute("aria-pressed", on);
  musicBtn.innerHTML = on ? ICON_PAUSE : ICON_PLAY;
}
setMusic(false);
musicBtn.onclick = () => setMusic(!Music.playing);

/* ---------- Gentle auto-scroll down to the RSVP ---------- */
function autoScroll() {
  const target = document.querySelector(".olive-sec");
  if (!target) return;
  const root = document.documentElement, prevBehavior = root.style.scrollBehavior;
  root.style.scrollBehavior = "auto";
  const evs = ["touchstart", "wheel", "mousedown", "keydown"];
  let stop = false, last = null, v = 0, y = window.scrollY;
  const off = () => { evs.forEach(e => window.removeEventListener(e, cancel)); root.style.scrollBehavior = prevBehavior; };
  function cancel() { stop = true; off(); }
  evs.forEach(e => window.addEventListener(e, cancel, { passive: true }));
  const step = ts => {
    if (stop) return;
    if (last === null) last = ts;
    const dt = Math.min(0.05, (ts - last) / 1000); last = ts;
    const end = target.getBoundingClientRect().top + window.scrollY - 24;
    v = Math.min(95, v + 60 * dt);
    const remain = end - y;
    const speed = remain < 320 ? Math.max(20, v * remain / 320) : v;
    y = Math.min(end, y + speed * dt);
    window.scrollTo(0, y);
    if (y >= end - 0.5) { off(); const env = document.querySelector(".mini-env"); if (env) env.classList.add("nudge"); return; }
    requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/* ---------- Open envelope ---------- */
const intro = $("intro");
function openInvite() {
  if (intro.classList.contains("open")) return;
  setMusic(true);
  intro.classList.add("open");
  setTimeout(() => document.body.classList.remove("sealed"), reduceMotion ? 0 : 800);
  if (!reduceMotion) setTimeout(autoScroll, 3000);
}
$("seal").addEventListener("click", openInvite);
intro.addEventListener("click", e => { if (e.target !== $("seal")) openInvite(); });

/* ---------- RSVP (personal invitations) ---------- */
const CFG = window.WEDDING_CONFIG || {};
const INVITEE = CFG.invitee || null;
const TOKEN = new URLSearchParams(location.search).get("i") || "";
const SEATS = INVITEE ? Math.max(1, Number(INVITEE.seats) || 1) : WEDDING.maxGuests;
if (INVITEE) { $("dear").textContent = `Dear ${INVITEE.name},`; $("dear").hidden = false; }

const dlg = $("rsvp");
$("closeRsvp").onclick = () => dlg.close();
dlg.addEventListener("click", e => { if (e.target === dlg) dlg.close(); });

let attending = null, count = 1;
const guestsBox = $("guests");
function renderGuests(prefill) {
  const old = prefill || [...guestsBox.querySelectorAll("input")].map(i => i.value);
  guestsBox.innerHTML = "";
  for (let i = 1; i < count; i++) {
    const l = document.createElement("label"); l.className = "fl"; l.htmlFor = "g" + i; l.textContent = `Guest ${i + 1} name`;
    const inp = document.createElement("input"); inp.className = "in"; inp.type = "text"; inp.id = "g" + i; inp.value = old[i - 1] || "";
    guestsBox.append(l, inp);
  }
  $("count").textContent = count;
  $("minus").disabled = count <= 1;
  $("plus").disabled = count >= SEATS;
}
if (SEATS <= 1) {
  document.querySelector("#attendBlock .counter").classList.add("hidden");
  $("seatHint").textContent = "This invitation is for one guest.";
} else $("seatHint").textContent = `Including yourself · up to ${SEATS} guests`;

function setAttending(v) {
  attending = v;
  document.querySelectorAll('input[name="att"]').forEach(r => { r.checked = (r.value === "yes") === v; });
  $("attendBlock").classList.toggle("hidden", !v);
}
function showPrevious() {
  if (!INVITEE || INVITEE.status === "pending") { $("prevReply").classList.add("hidden"); return; }
  const yes = INVITEE.status === "yes", g = Number(INVITEE.guests) || 1;
  $("prevReply").textContent = yes
    ? `You told us you’ll attend${g > 1 ? ` with ${g} guests in total` : ""}. You can change your reply below.`
    : "You told us you can’t attend. You can change your reply below.";
  $("prevReply").classList.remove("hidden");
  setAttending(yes);
  if (yes) { count = Math.min(SEATS, g); renderGuests(String(INVITEE.names || "").split(", ").slice(1)); }
}
document.querySelectorAll('input[name="att"]').forEach(r => r.addEventListener("change", () => {
  setAttending(r.value === "yes"); $("err").textContent = "";
}));
$("plus").onclick = () => { if (count < SEATS) { count++; renderGuests(); } };
$("minus").onclick = () => { if (count > 1) { count--; renderGuests(); } };
renderGuests();
showPrevious();

$("openRsvp").onclick = () => {
  $("done").classList.add("hidden"); $("rsvpForm").classList.remove("hidden");
  $("send").disabled = false; $("send").textContent = "Send RSVP"; $("err").textContent = "";
  showPrevious(); dlg.showModal();
};

$("send").onclick = async () => {
  if (!INVITEE || !TOKEN) {
    $("err").textContent = CFG.preview ? "This is your preview. Guests reply from their personal links." : "Please open your personal invitation link to reply.";
    return;
  }
  if (attending === null) { $("err").textContent = "Choose whether you'll attend"; return; }
  const others = attending ? [...guestsBox.querySelectorAll("input")].map(i => i.value.trim()) : [];
  if (others.some(n => !n)) { $("err").textContent = "Enter the name of every guest"; return; }
  $("err").textContent = "";
  const btn = $("send"); btn.disabled = true; btn.textContent = "Sending…";
  try {
    const names = attending ? [INVITEE.name, ...others] : [];
    const res = await fetch(WEDDING.apiUrl, { method: "POST", headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token: TOKEN, attending, count: attending ? count : 0, names }) });
    const out = await res.json().catch(() => ({}));
    if (!res.ok || !out.ok) throw new Error("save failed");
    Object.assign(INVITEE, { status: attending ? "yes" : "no", guests: attending ? count : 0, names: names.join(", ") });
    $("rsvpForm").classList.add("hidden");
    const first = INVITEE.name.split(" ")[0];
    $("done").textContent = attending ? `Thank you, ${first}! We can’t wait to celebrate with you.` : "Thank you for letting us know. You’ll be missed.";
    $("done").classList.remove("hidden");
  } catch (e) {
    $("err").textContent = "Couldn’t send. Please check your connection and try again.";
    btn.disabled = false; btn.textContent = "Send RSVP";
  }
};

</script>
</body>
</html>

__INVITE_HTML__;
}

function admin_html(): string {
    return <<<'__ADMIN_HTML__'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Wedding dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,400&family=Rubik:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #F2ECE0; --card: #FBF8F2; --ink: #343C31; --soft: #6F7566; --line: #E2D9C8;
    --sage: #6E7A50; --olive: #56613F; --sage-soft: #DCE1CB; --rose: #B5675F; --rose-soft: #F1DDD8;
    box-sizing: border-box;
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: env(safe-area-inset-bottom, 0px);
  }
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      color-scheme: dark;
      --bg: #141812; --card: #1D221A; --ink: #ECE7DB; --soft: #A9AE9E; --line: #30372B;
      --sage: #A9B784; --olive: #8D9A65; --sage-soft: #2C3424; --rose: #E3958B; --rose-soft: #3A2622;
    }
  }
  :root[data-theme="dark"] {
    color-scheme: dark;
    --bg: #141812; --card: #1D221A; --ink: #ECE7DB; --soft: #A9AE9E; --line: #30372B;
    --sage: #A9B784; --olive: #8D9A65; --sage-soft: #2C3424; --rose: #E3958B; --rose-soft: #3A2622;
  }
  html { scroll-padding-top: env(safe-area-inset-top, 0px); }
  *, *::before, *::after { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--ink); font-family: "Rubik", "Helvetica Neue", Arial, sans-serif; font-weight: 300; font-size: 16px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
  main { max-width: 720px; margin: 0 auto; padding: 28px 18px 60px; }
  header { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 22px; }
  .eyebrow { margin: 0; font-size: .72rem; letter-spacing: .28em; text-transform: uppercase; color: var(--soft); font-weight: 400; }
  h1 { margin: 2px 0 0; font-family: "Cormorant Garamond", Georgia, serif; font-weight: 600; font-size: 2.1rem; line-height: 1.1; letter-spacing: .04em; }
  .refresh { flex: none; display: inline-flex; align-items: center; gap: 8px; font: inherit; font-weight: 400; font-size: .9rem; padding: 9px 14px; border-radius: 30px; border: 1px solid var(--line); background: var(--card); color: var(--ink); cursor: pointer; }
  .refresh svg { width: 16px; height: 16px; }
  .refresh.spin svg { animation: spin 1s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .banner { background: var(--rose-soft); color: var(--ink); border-radius: 14px; padding: 12px 14px; font-size: .9rem; margin-bottom: 16px; }
  .banner b { font-weight: 500; }

  .invite-links { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
  .il { display: inline-flex; align-items: center; justify-content: center; gap: 7px; font: inherit; font-weight: 400; font-size: .88rem; padding: 11px 8px; border-radius: 14px; border: 1px solid var(--line); background: var(--card); color: var(--ink); text-decoration: none; cursor: pointer; white-space: nowrap; }
  .il svg { width: 17px; height: 17px; flex: none; }
  .il.primary { background: var(--olive); border-color: var(--olive); color: #F7F2E9; }
  .il:focus-visible { outline: 2px solid var(--sage); outline-offset: 2px; }
  .hero { background: var(--olive); color: #F7F2E9; border-radius: 22px; padding: 22px; display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center; }
  .hero .big { font-family: "Cormorant Garamond", Georgia, serif; font-weight: 600; font-size: 4.2rem; line-height: 1; margin: 6px 0 4px; font-variant-numeric: lining-nums tabular-nums; }
  .hero .eyebrow { color: #DCE1CB; }
  .hero p { margin: 0; color: #E8EADB; font-size: .92rem; }
  .ring { width: 116px; height: 116px; position: relative; }
  .ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
  .ring .lbl { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; }
  .ring .lbl b { display: block; font-family: "Cormorant Garamond", Georgia, serif; font-size: 1.9rem; font-weight: 600; line-height: 1; }
  .ring .lbl span { font-size: .66rem; letter-spacing: .14em; text-transform: uppercase; color: #DCE1CB; }

  .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 12px 0; }
  .stat { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 14px; }
  .stat b { display: block; font-family: "Cormorant Garamond", Georgia, serif; font-size: 2.1rem; font-weight: 600; line-height: 1.05; font-variant-numeric: lining-nums tabular-nums; }
  .stat span { font-size: .78rem; color: var(--soft); }
  .stat.yes b { color: var(--sage); } .stat.no b { color: var(--rose); }

  .card { background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 16px; margin-bottom: 12px; }
  .cap-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
  .cap-row label { font-size: .9rem; color: var(--soft); }
  .cap-row input { width: 92px; font: inherit; font-weight: 400; color: var(--ink); background: var(--bg); border: 1px solid var(--line); border-radius: 10px; padding: 7px 10px; text-align: center; }
  .bar { height: 12px; border-radius: 10px; background: var(--sage-soft); overflow: hidden; margin: 12px 0 6px; }
  .bar i { display: block; height: 100%; width: 0; background: var(--sage); border-radius: 10px; transition: width .6s ease; }
  .bar-note { font-size: .85rem; color: var(--soft); margin: 0; }

  .tools { display: flex; gap: 8px; flex-wrap: wrap; margin: 18px 0 10px; }
  .tabs { display: flex; background: var(--card); border: 1px solid var(--line); border-radius: 30px; padding: 3px; }
  .tabs button { font: inherit; font-weight: 400; font-size: .85rem; border: 0; background: transparent; color: var(--soft); padding: 7px 12px; border-radius: 30px; cursor: pointer; }
  .tabs button[aria-pressed="true"] { background: var(--olive); color: #F7F2E9; }
  .search { flex: 1; min-width: 150px; font: inherit; color: var(--ink); background: var(--card); border: 1px solid var(--line); border-radius: 30px; padding: 8px 14px; }
  button:focus-visible, input:focus-visible { outline: 2px solid var(--sage); outline-offset: 2px; }

  .list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
  .row { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; }
  .row .nm { font-weight: 500; overflow-wrap: anywhere; }
  .row .pill { align-self: start; font-size: .78rem; font-weight: 400; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
  .row .pill.yes { background: var(--sage-soft); color: var(--sage); } .row .pill.no { background: var(--rose-soft); color: var(--rose); }
  .row .meta { grid-column: 1 / -1; font-size: .84rem; color: var(--soft); overflow-wrap: anywhere; }
  .photos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px; }
  .slot { display: flex; flex-direction: column; align-items: center; gap: 8px; }
  .thumb { width: 100%; aspect-ratio: 1; border-radius: 14px; background: var(--bg); border: 1px dashed var(--line); display: grid; place-items: center; overflow: hidden; color: var(--soft); font-size: .8rem; }
  .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .slot-name { margin: 0; font-size: .85rem; color: var(--soft); }
  .upl { display: inline-flex; justify-content: center; width: 100%; font-weight: 400; font-size: .85rem; padding: 9px 10px; border-radius: 30px; background: var(--olive); color: #F7F2E9; cursor: pointer; text-align: center; }
  .upl:focus-within { outline: 2px solid var(--sage); outline-offset: 2px; }
  .upl.busy { opacity: .6; pointer-events: none; }
  .rm { font: inherit; font-size: .8rem; border: 0; background: none; color: var(--rose); cursor: pointer; padding: 2px 6px; }
  .empty { text-align: center; color: var(--soft); padding: 26px 0; }
  .form { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; }
  .form label { display: flex; flex-direction: column; gap: 4px; font-size: .8rem; color: var(--soft); min-width: 0; }
  .form .wide { grid-column: 1 / -1; }
  .form input { font: inherit; font-size: .95rem; color: var(--ink); background: var(--bg); border: 1px solid var(--line); border-radius: 10px; padding: 9px 10px; width: 100%; min-width: 0; }
  .form button { border: 0; margin-top: 4px; font-size: .95rem; padding: 11px; }
  .linkbtn { font: inherit; font-size: .8rem; border: 0; background: none; color: var(--soft); text-decoration: underline; cursor: pointer; }
  .row .del { grid-column: 2; justify-self: end; font: inherit; font-size: .75rem; border: 0; background: none; color: var(--soft); cursor: pointer; padding: 0; text-decoration: underline; }
  .gate { position: fixed; inset: 0; z-index: 50; display: grid; place-items: center; padding: 20px; background: var(--bg); }
  .gate[hidden] { display: none; }
  .gate-card { width: min(360px, 100%); background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 26px 22px; display: flex; flex-direction: column; gap: 12px; }
  .gate-card h2 { margin: 0; font-family: "Cormorant Garamond", Georgia, serif; font-weight: 600; font-size: 2rem; }
  .gate-card .gate-text { margin: 0; color: var(--soft); font-size: .92rem; }
  .gate-card input { font: inherit; color: var(--ink); background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 12px; }
  .gate-card button { border: 0; font-size: 1rem; padding: 12px; }
  .gate-err { margin: 0; min-height: 1.2em; color: var(--rose); font-size: .88rem; }
  .cap-label { font-size: .9rem; color: var(--soft); } .cap-val { font-family: "Cormorant Garamond", Georgia, serif; font-size: 1.4rem; font-weight: 600; }
  .bulk { margin-top: 12px; } .bulk summary { cursor: pointer; font-size: .9rem; color: var(--sage); }
  .bulk textarea { width: 100%; font: inherit; font-size: .9rem; color: var(--ink); background: var(--bg); border: 1px solid var(--line); border-radius: 10px; padding: 10px; margin: 6px 0 10px; resize: vertical; }
  .bulk .upl { border: 0; }
  .row .acts { grid-column: 1 / -1; display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
  .row .acts button, .row .acts a { font: inherit; font-size: .78rem; font-weight: 400; padding: 6px 10px; border-radius: 20px; border: 1px solid var(--line); background: var(--bg); color: var(--ink); cursor: pointer; text-decoration: none; }
  .row .acts .wa { background: var(--olive); border-color: var(--olive); color: #F7F2E9; }
  .row .acts .danger { color: var(--rose); }
  .row .pill.wait { background: var(--bg); color: var(--soft); border: 1px solid var(--line); }
  .tabs button { padding: 7px 10px; }
  .hbtns { display: flex; gap: 8px; flex: none; }
  #report { display: none; }
  @media print {
    @page { size: A4; margin: 16mm 14mm; }
    body { background: #fff; color: #222; }
    main, .banner { display: none !important; }
    #report { display: block; font-family: "Rubik", Arial, sans-serif; font-size: 11pt; color: #222; }
    #report h1 { font-family: "Cormorant Garamond", Georgia, serif; font-size: 24pt; margin: 0; color: #343C31; }
    #report .sub { color: #666; margin: 2px 0 14px; font-size: 9.5pt; }
    #report .sum { display: flex; gap: 10px; margin-bottom: 16px; }
    #report .sum div { flex: 1; border: 1px solid #d8d2c4; border-radius: 6px; padding: 8px 10px; }
    #report .sum b { display: block; font-size: 18pt; color: #56613F; font-family: "Cormorant Garamond", Georgia, serif; }
    #report .sum span { font-size: 8.5pt; color: #666; }
    #report h2 { font-size: 12pt; margin: 18px 0 6px; color: #56613F; }
    #report table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    #report th { text-align: left; border-bottom: 1.5px solid #56613F; padding: 5px 6px; font-weight: 500; }
    #report td { border-bottom: 1px solid #e4dfd3; padding: 5px 6px; vertical-align: top; }
    #report tr { break-inside: avoid; }
    #report .num { text-align: center; width: 52px; }
  }
  /* ---------- motion ---------- */
  main > .enter { opacity: 0; transform: translateY(16px); animation: rise .7s cubic-bezier(.2,.7,.3,1) forwards; }
  @keyframes rise { to { opacity: 1; transform: none; } }
  #ringArc { transition: stroke-dasharray 1.2s cubic-bezier(.2,.7,.3,1), opacity .3s; }
  .hero { position: relative; overflow: hidden; isolation: isolate; }
  .hero::before { content: ""; position: absolute; inset: -40%; z-index: -1; background: radial-gradient(closest-side, rgba(247,242,233,.14), transparent 70%); animation: drift 14s ease-in-out infinite alternate; }
  @keyframes drift { from { transform: translate(-18%, -10%); } to { transform: translate(22%, 14%); } }
  .row.new { opacity: 0; transform: translateY(10px); animation: rise .5s cubic-bezier(.2,.7,.3,1) forwards; }
  .bump { animation: bump .55s cubic-bezier(.3,1.6,.5,1); display: inline-block; }
  @keyframes bump { 40% { transform: scale(1.14); } }
  .stat, .il, .card, .row { transition: transform .25s ease, box-shadow .25s ease; }
  @media (hover: hover) { .stat:hover, .row:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(52,60,49,.08); } }
  .il:active, .refresh:active, .upl:active { transform: scale(.97); }
  @media (prefers-reduced-motion: reduce) {
    main > .enter, .row.new { animation: none; opacity: 1; transform: none; }
    #ringArc, .stat, .il, .card, .row { transition: none; }
    .hero::before, .bump { animation: none; }
  }
  footer { text-align: center; color: var(--soft); font-size: .8rem; margin-top: 22px; }
  @media (max-width: 420px) { h1 { font-size: 1.6rem; } .refresh { padding: 8px 11px; } .hero .big { font-size: 3.4rem; } .ring { width: 96px; height: 96px; } }
  @media (prefers-reduced-motion: reduce) { .bar i { transition: none; } .refresh.spin svg { animation: none; } }
</style>
</head>
<body>
<div class="gate" id="gate" hidden>
  <form class="gate-card" id="gateForm">
    <p class="eyebrow">Wedding dashboard</p>
    <h2 id="gateTitle">Sign in</h2>
    <p class="gate-text" id="gateText">Enter your dashboard password.</p>
    <input id="gatePw" type="password" autocomplete="current-password" placeholder="Password" required>
    <input id="gatePw2" type="password" autocomplete="new-password" placeholder="Repeat password" hidden>
    <button class="upl" type="submit" id="gateBtn">Sign in</button>
    <p class="gate-err" id="gateErr" role="alert"></p>
  </form>
</div>

<main>
  <header>
    <div>
      <p class="eyebrow">Wedding RSVPs</p>
      <h1 id="coupleTitle">Your wedding</h1>
    </div>
    <div class="hbtns">
    <button class="refresh" id="savePdf" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
      PDF
    </button>
    <button class="refresh" id="refresh" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/></svg>
      Refresh
    </button>
    </div>
  </header>

  <div class="banner" id="banner" hidden></div>

  <section class="invite-links" aria-label="Your invitation">
    <a class="il primary" id="openInvite" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
      Preview invitation
    </a>
    <a class="il" href="#guestList">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.8-3.2 3-5 5.5-5s4.7 1.8 5.5 5M17 8v6M14 11h6"/></svg>
      Guest list
    </a>
  </section>

  <section class="hero" aria-label="Summary">
    <div>
      <p class="eyebrow">Guests coming</p>
      <p class="big" id="guests">0</p>
      <p id="heroNote">No replies yet</p>
    </div>
    <div class="ring" aria-hidden="true">
      <svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="50" fill="none" stroke="rgba(247,242,233,.2)" stroke-width="12"/><circle id="ringArc" cx="60" cy="60" r="50" fill="none" stroke="#F7F2E9" stroke-width="12" stroke-linecap="round" stroke-dasharray="0 314.16"/></svg>
      <div class="lbl"><div><b id="ringPct">0%</b><span>Accepted</span></div></div>
    </div>
  </section>

  <section class="stats" aria-label="Replies">
    <div class="stat yes"><b id="yesCount">0</b><span>Accepted</span></div>
    <div class="stat no"><b id="noCount">0</b><span>Declined</span></div>
    <div class="stat"><b id="pendCount">0</b><span>No reply yet</span></div>
  </section>

  <section class="card" aria-label="Against the guest list">
    <div class="cap-row"><span class="cap-label">Seats filled</span><b class="cap-val" id="capVal">0 / 0</b></div>
    <div class="bar"><i id="capBar"></i></div>
    <p class="bar-note" id="capNote">Add your guests below to track who is coming.</p>
  </section>

  <section class="card" aria-labelledby="photosTitle">
    <p class="eyebrow" id="photosTitle" style="margin-bottom:12px">Invitation photos</p>
    <div class="photos">
      <div class="slot" data-slot="couple">
        <div class="thumb"><span>Bride &amp; groom</span></div>
        <p class="slot-name">Bride &amp; groom</p>
        <label class="upl">Upload from device<input type="file" accept="image/jpeg,image/png,image/webp" hidden></label>
        <button type="button" class="rm" hidden>Remove</button>
      </div>
      <div class="slot" data-slot="venue">
        <div class="thumb"><span>Venue</span></div>
        <p class="slot-name">Venue</p>
        <label class="upl">Upload from device<input type="file" accept="image/jpeg,image/png,image/webp" hidden></label>
        <button type="button" class="rm" hidden>Remove</button>
      </div>
    </div>
    <p class="bar-note" id="photoNote">Photos appear on the invitation right away. Without a photo, the invitation keeps its built-in artwork.</p>
  </section>

  <section class="card" aria-labelledby="detailsTitle">
    <p class="eyebrow" id="detailsTitle" style="margin-bottom:12px">Invitation details</p>
    <form id="detailsForm" class="form" autocomplete="off">
      <label>Groom name<input name="groom" required maxlength="60"></label>
      <label>Bride name<input name="bride" required maxlength="60"></label>
      <label>Groom name in Arabic<input name="groomArabic" dir="rtl" maxlength="60"></label>
      <label>Bride name in Arabic<input name="brideArabic" dir="rtl" maxlength="60"></label>
      <label>Groom’s parents<input name="groomParents" maxlength="120"></label>
      <label>Bride’s parents<input name="brideParents" maxlength="120"></label>
      <label>Date<input name="date" type="date" required></label>
      <label>Time<input name="time" type="time" required></label>
      <label class="wide">Venue<input name="venue" maxlength="120"></label>
      <label class="wide">Hall or address<input name="venueAddress" maxlength="160"></label>
      <label class="wide">Google Maps link<input name="mapLink" type="url" inputmode="url" placeholder="https://maps.app.goo.gl/…" maxlength="600"></label>
      <label class="wide">Reply by<input name="rsvpBy" placeholder="e.g. 10 November" maxlength="60"></label>
      <label class="wide">Note on the invitation (optional)<input name="note" placeholder="e.g. Ladies only" maxlength="120"></label>
      <button class="upl wide" type="submit" id="saveDetails">Save details</button>
    </form>
    <p class="bar-note" id="detailsNote">Changes show on the invitation as soon as you save.</p>
  </section>

  <section class="card" id="guestList" aria-labelledby="glTitle">
    <p class="eyebrow" id="glTitle" style="margin-bottom:12px">Add guests</p>
    <form id="addForm" class="form" autocomplete="off">
      <label class="wide">Name<input name="name" required maxlength="120" placeholder="e.g. Ahmed Ali &amp; family"></label>
      <label>Seats<input name="seats" type="number" min="1" max="20" value="1" inputmode="numeric"></label>
      <label>WhatsApp number (optional)<input name="phone" inputmode="tel" placeholder="9733XXXXXXX"></label>
      <button class="upl wide" type="submit">Add guest</button>
    </form>
    <details class="bulk">
      <summary>Add many at once</summary>
      <p class="bar-note">One guest per line: name, seats, WhatsApp number. Seats and number are optional.</p>
      <textarea id="bulkText" rows="5" placeholder="Ahmed Ali, 3, 97333334444&#10;Fatima Rashid, 2&#10;Rahul Nair"></textarea>
      <button class="upl" type="button" id="bulkAdd">Add all</button>
    </details>
    <p class="bar-note" id="addNote">Each guest gets a personal link. Only people with a link can open the invitation.</p>
  </section>

  <div class="tools">
    <div class="tabs" role="group" aria-label="Filter">
      <button type="button" data-f="all" aria-pressed="true">All</button>
      <button type="button" data-f="yes" aria-pressed="false">Coming</button>
      <button type="button" data-f="no" aria-pressed="false">Declined</button>
      <button type="button" data-f="pending" aria-pressed="false">Waiting</button>
    </div>
    <input class="search" id="search" type="search" placeholder="Search a name" aria-label="Search a name">
  </div>
  <ul class="list" id="list"></ul>

  <footer><span id="updated"></span> <button type="button" class="linkbtn" id="logout" hidden>Sign out</button></footer>
</main>

<section id="report" aria-hidden="true"></section>

<script>
const DEMO = [
  { id: 1, token: "demo01", name: "Ahmed Ali & family", phone: "97333334444", seats: 4, status: "yes", guests: 3, names: "Ahmed Ali & family, Sara Ahmed, Yusuf Ahmed", time: "2026-10-20T18:12:00Z" },
  { id: 2, token: "demo02", name: "Fatima Rashid", phone: "", seats: 2, status: "yes", guests: 2, names: "Fatima Rashid, Huda Rashid", time: "2026-10-20T19:40:00Z" },
  { id: 3, token: "demo03", name: "Mohammed Saleem", phone: "", seats: 1, status: "no", guests: 0, names: "", time: "2026-10-21T08:05:00Z" },
  { id: 4, token: "demo04", name: "Rahul Nair", phone: "", seats: 4, status: "yes", guests: 4, names: "Rahul Nair, Anjali Nair, Dev Nair, Meera Nair", time: "2026-10-21T10:30:00Z" },
  { id: 5, token: "demo05", name: "Aisha Kareem", phone: "", seats: 1, status: "pending", guests: 0, names: "", time: null },
  { id: 6, token: "demo06", name: "Shameer K", phone: "", seats: 5, status: "pending", guests: 0, names: "", time: null }
];
const DEMO_DETAILS = { groom: "Fahim", bride: "Marwa", groomArabic: "فهيم", brideArabic: "مروة", groomParents: "Hamza and Rahiyanath", brideParents: "Rafeek and Souda", date: "2026-11-19", time: "19:30", venue: "Adhari New Seasons", venueAddress: "Hall No. 1", mapLink: "", rsvpBy: "", maxGuests: "6", note: "" };

const $ = id => document.getElementById(id);
const onSite = /^https?:$/.test(location.protocol) && !/claude\.ai|claudeusercontent|anthropic/.test(location.hostname);
const calm = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
let rows = [], filter = "all", csrf = "", details = {}, timer = null;
const seen = new Set();
let base = "./";
try { if (/^https?:$/.test(location.protocol)) base = new URL("./", location.href).href; } catch (e) {}
const linkFor = r => base + "?i=" + r.token;

/* ---------- helpers ---------- */
function countUp(el, to, suffix = "") {
  const from = Number(el.dataset.v || 0);
  el.dataset.v = to;
  if (from === to || calm) { el.textContent = to + suffix; return; }
  if (el.dataset.started) { el.classList.remove("bump"); void el.offsetWidth; el.classList.add("bump"); }
  el.dataset.started = "1";
  const t0 = performance.now(), dur = 900;
  const step = now => {
    const p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(from + (to - from) * e) + suffix;
    if (p < 1 && Number(el.dataset.v) === to) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}
document.querySelectorAll("main > *").forEach((el, i) => { el.classList.add("enter"); el.style.animationDelay = Math.min(i, 10) * 70 + "ms"; });

async function api(name, { method = "GET", json, form, query = "" } = {}) {
  const headers = {};
  if (csrf) headers["X-CSRF"] = csrf;
  let body;
  if (json) { headers["Content-Type"] = "application/json"; body = JSON.stringify(json); }
  if (form) body = form;
  const res = await fetch(`?api=${name}${query}`, { method, headers, body, credentials: "same-origin" });
  const text = await res.text();
  let data = null; try { data = JSON.parse(text); } catch (e) {}
  if (!data) throw new Error(/<\?php/.test(text) ? "Your hosting isn’t running PHP. Use hosting with PHP, such as cPanel." : `Unexpected server reply (status ${res.status}).`);
  if (res.status === 401) { showGate(false); throw new Error(data.error || "Please sign in."); }
  if (!res.ok || data.ok === false) throw new Error(data.error === "database unavailable" ? "PHP couldn’t open the database. Ask your host to enable pdo_sqlite and make the data folder writable." : (data.error || `Server error (${res.status}).`));
  return data;
}
const couple = () => (details.groom && details.bride) ? `${details.groom} & ${details.bride}` : "Our wedding";
function inviteText(r) {
  return `Dear ${r.name},\nWith joy, we invite you to the wedding of ${couple()}.\nPlease open your personal invitation and confirm your attendance:\n${linkFor(r)}`;
}

/* ---------- sign in ---------- */
function showGate(setup) {
  $("gate").hidden = false;
  $("gateTitle").textContent = setup ? "Create your password" : "Sign in";
  $("gateText").textContent = setup ? "This is the first time the dashboard is opened. Choose a password (at least 8 characters) to keep your guest list private." : "Enter your dashboard password.";
  $("gatePw2").hidden = !setup; $("gatePw2").required = setup;
  $("gatePw").autocomplete = setup ? "new-password" : "current-password";
  $("gateBtn").textContent = setup ? "Create password" : "Sign in";
  $("gateForm").dataset.mode = setup ? "setup" : "login";
  setTimeout(() => $("gatePw").focus(), 50);
}
$("gateForm").addEventListener("submit", async e => {
  e.preventDefault();
  const setup = $("gateForm").dataset.mode === "setup";
  if (setup && $("gatePw").value !== $("gatePw2").value) { $("gateErr").textContent = "The two passwords don’t match."; return; }
  $("gateBtn").disabled = true; $("gateErr").textContent = "";
  try {
    const d = await api(setup ? "setup" : "login", { method: "POST", json: { password: $("gatePw").value } });
    csrf = d.csrf; $("gatePw").value = $("gatePw2").value = "";
    $("gate").hidden = true; boot();
  } catch (err) { $("gateErr").textContent = err.message; }
  $("gateBtn").disabled = false;
});
$("logout").addEventListener("click", async () => { try { await api("logout", { method: "POST" }); } catch (e) {} location.reload(); });

/* ---------- invitation details ---------- */
function applyDetails() { $("coupleTitle").textContent = couple(); document.title = couple() + " · Dashboard"; }
function fillForm() {
  for (const el of $("detailsForm").elements) if (el.name) el.value = details[el.name] ?? (el.name === "maxGuests" ? "6" : "");
}
function derive(d) {
  const at = new Date(`${d.date}T${d.time}:00+03:00`);
  if (isNaN(at)) return {};
  const tz = { timeZone: "Asia/Bahrain" };
  const dd = at.toLocaleDateString("en-GB", { ...tz, day: "2-digit" }), mm = at.toLocaleDateString("en-GB", { ...tz, month: "2-digit" }), yy = at.toLocaleDateString("en-GB", { ...tz, year: "2-digit" });
  return {
    dayName: at.toLocaleDateString("en-GB", { ...tz, weekday: "long" }),
    dateLong: at.toLocaleDateString("en-GB", { ...tz, day: "numeric", month: "long", year: "numeric" }),
    shortDate: `${dd} . ${mm} . ${yy}`,
    startTime: at.toLocaleTimeString("en-US", { ...tz, hour: "numeric", minute: "2-digit" }) + " onwards",
    startsAt: `${d.date}T${d.time}:00+03:00`
  };
}
$("detailsForm").addEventListener("submit", async e => {
  e.preventDefault();
  const d = {};
  for (const el of e.target.elements) if (el.name) d[el.name] = el.value.trim();
  Object.assign(d, derive(d));
  if (!onSite) { details = d; applyDetails(); $("detailsNote").textContent = "Preview only: details are saved once the system is on your site."; return; }
  $("saveDetails").disabled = true; $("detailsNote").textContent = "Saving…";
  try { const r = await api("settings", { method: "POST", json: d }); details = r.details; applyDetails(); $("detailsNote").textContent = "Saved. The invitation is updated."; }
  catch (err) { $("detailsNote").textContent = "Couldn’t save: " + err.message; }
  $("saveDetails").disabled = false;
});

/* ---------- guests ---------- */
function parseLine(line) {
  const parts = line.split(/[,\t;]/).map(x => x.trim());
  const name = parts[0];
  let seats = 1, phone = "";
  parts.slice(1).forEach(p => {
    const digits = p.replace(/[^0-9]/g, "");
    if (!digits) return;
    if (digits.length <= 2) seats = Math.max(1, Math.min(20, Number(digits))); else phone = digits;
  });
  return { name, seats, phone };
}
async function addGuests(items) {
  items = items.filter(x => x.name);
  if (!items.length) { $("addNote").textContent = "Write at least one name."; return false; }
  if (!onSite) {
    items.forEach(x => rows.push({ id: Date.now() + Math.random(), token: Math.random().toString(16).slice(2, 10), name: x.name, phone: x.phone || "", seats: Math.max(1, Number(x.seats) || 1), status: "pending", guests: 0, names: "", time: null }));
    render(); $("addNote").textContent = `Added ${items.length} (preview only).`; return true;
  }
  try { const r = await api("invitee_add", { method: "POST", json: { items } }); $("addNote").textContent = `Added ${r.added} ${r.added === 1 ? "guest" : "guests"}. Send each one their link from the list below.`; await load(); return true; }
  catch (err) { $("addNote").textContent = "Couldn’t add: " + err.message; return false; }
}
$("addForm").addEventListener("submit", async e => {
  e.preventDefault();
  const f = e.target;
  if (await addGuests([{ name: f.name.value.trim(), seats: Number(f.seats.value) || 1, phone: f.phone.value.replace(/[^0-9]/g, "") }])) { f.reset(); f.seats.value = 1; f.name.focus(); }
});
$("bulkAdd").addEventListener("click", async () => {
  const items = $("bulkText").value.split("\n").map(l => l.trim()).filter(Boolean).map(parseLine);
  if (await addGuests(items)) $("bulkText").value = "";
});

async function act(name, r, msg) {
  if (msg && !confirm(msg)) return;
  if (!onSite) {
    if (name === "invitee_delete") rows = rows.filter(x => x !== r);
    if (name === "invitee_reset") Object.assign(r, { status: "pending", guests: 0, names: "", time: null });
    render(); return;
  }
  try { await api(name, { method: "POST", json: { id: r.id } }); load(); } catch (err) { alert(err.message); }
}

function render() {
  const yes = rows.filter(r => r.status === "yes"), no = rows.filter(r => r.status === "no"), wait = rows.filter(r => r.status === "pending");
  const guests = yes.reduce((s, r) => s + (Number(r.guests) || 0), 0);
  const seats = rows.reduce((s, r) => s + (Number(r.seats) || 0), 0);
  const replied = yes.length + no.length;
  const pct = replied ? Math.round(yes.length / replied * 100) : 0;
  countUp($("guests"), guests); countUp($("yesCount"), yes.length); countUp($("noCount"), no.length); countUp($("pendCount"), wait.length); countUp($("ringPct"), pct, "%");
  $("ringArc").setAttribute("stroke-dasharray", (pct / 100 * 314.16).toFixed(1) + " 314.16");
  $("ringArc").style.opacity = pct ? 1 : 0;
  $("heroNote").textContent = rows.length ? `${replied} of ${rows.length} invitations answered` : "No guests added yet";
  const fill = seats ? Math.min(100, Math.round(guests / seats * 100)) : 0;
  $("capVal").textContent = `${guests} / ${seats}`;
  $("capBar").style.width = fill + "%";
  $("capNote").textContent = seats ? `${fill}% of the ${seats} invited seats are confirmed.` : "Add your guests below to track who is coming.";

  const q = $("search").value.trim().toLowerCase();
  const shown = rows.filter(r => (filter === "all" || r.status === filter) && (!q || (r.name + " " + r.names + " " + r.phone).toLowerCase().includes(q)))
    .sort((a, b) => (b.time || b.created_at || "").localeCompare(a.time || a.created_at || "") || b.id - a.id);
  const list = $("list"); list.innerHTML = "";
  if (!shown.length) {
    const li = document.createElement("li"); li.className = "empty";
    li.textContent = rows.length ? "No one matches" : "Your guests will appear here"; list.appendChild(li); return;
  }
  let fresh = 0;
  shown.forEach(r => {
    const li = document.createElement("li"); li.className = "row";
    const key = r.id + "|" + r.status + "|" + r.time;
    if (!seen.has(key)) { seen.add(key); li.classList.add("new"); li.style.animationDelay = Math.min(fresh++, 12) * 45 + "ms"; }
    const n = document.createElement("span"); n.className = "nm"; n.textContent = r.name;
    const pill = document.createElement("span");
    pill.className = "pill " + (r.status === "yes" ? "yes" : r.status === "no" ? "no" : "wait");
    pill.textContent = r.status === "yes" ? `Coming · ${r.guests}` : r.status === "no" ? "Declined" : "Waiting";
    const meta = document.createElement("span"); meta.className = "meta";
    const when = r.time ? new Date(r.time) : null;
    const date = when && !isNaN(when) ? "Replied " + when.toLocaleDateString(undefined, { day: "numeric", month: "short" }) : "";
    meta.textContent = [`${r.seats} ${r.seats === 1 ? "seat" : "seats"}`, r.status === "yes" && r.names ? r.names : "", date].filter(Boolean).join(" · ");
    const acts = document.createElement("div"); acts.className = "acts";
    const wa = document.createElement("a"); wa.className = "wa"; wa.target = "_blank"; wa.rel = "noopener"; wa.textContent = "Send on WhatsApp";
    wa.href = `https://wa.me/${r.phone || ""}?text=${encodeURIComponent(inviteText(r))}`;
    const cp = document.createElement("button"); cp.type = "button"; cp.textContent = "Copy link";
    cp.addEventListener("click", async () => {
      try { await navigator.clipboard.writeText(linkFor(r)); cp.textContent = "Copied"; } catch (e) { window.prompt("Copy this guest’s link:", linkFor(r)); }
      setTimeout(() => { cp.textContent = "Copy link"; }, 1800);
    });
    const op = document.createElement("a"); op.textContent = "Open"; op.target = "_blank"; op.rel = "noopener"; op.href = linkFor(r);
    acts.append(wa, cp, op);
    if (r.status !== "pending") { const rs = document.createElement("button"); rs.type = "button"; rs.textContent = "Clear reply"; rs.addEventListener("click", () => act("invitee_reset", r, `Clear the reply from ${r.name}? They can answer again from their link.`)); acts.append(rs); }
    const del = document.createElement("button"); del.type = "button"; del.className = "danger"; del.textContent = "Remove";
    del.addEventListener("click", () => act("invitee_delete", r, `Remove ${r.name}? Their link will stop working.`));
    acts.append(del);
    li.append(n, pill, meta, acts); list.appendChild(li);
  });
}
async function load() {
  const btn = $("refresh"), banner = $("banner"); btn.classList.add("spin");
  if (!onSite) {
    if (!rows.length) rows = DEMO.map(x => ({ ...x }));
    banner.hidden = false; banner.innerHTML = "<b>Preview with demo data.</b> Your real guest list shows once the system is on your site.";
  } else {
    try { const d = await api("invitees"); rows = d.rows || []; banner.hidden = true; }
    catch (e) { banner.hidden = false; banner.innerHTML = "<b>Couldn’t load your guest list.</b> "; banner.appendChild(document.createTextNode(e.message)); }
  }
  $("updated").textContent = "Updated " + new Date().toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" }) + " ·";
  btn.classList.remove("spin"); render();
}
$("search").addEventListener("input", render);
document.querySelectorAll(".tabs button").forEach(b => b.addEventListener("click", () => {
  filter = b.dataset.f; document.querySelectorAll(".tabs button").forEach(x => x.setAttribute("aria-pressed", x === b)); render();
}));
$("refresh").addEventListener("click", load);
$("openInvite").href = base;

/* ---------- photos ---------- */
function showPhotos(photos) {
  document.querySelectorAll(".slot").forEach(el => {
    const url = photos && photos[el.dataset.slot], th = el.querySelector(".thumb");
    th.innerHTML = "";
    if (url) { const img = document.createElement("img"); img.src = url; img.alt = el.querySelector(".slot-name").textContent; th.appendChild(img); }
    else { const sp = document.createElement("span"); sp.textContent = "No photo yet"; th.appendChild(sp); }
    el.querySelector(".rm").hidden = !url;
  });
}
function shrink(file, max = 1600) {
  return new Promise(resolve => {
    const img = new Image(), url = URL.createObjectURL(file);
    img.onload = () => {
      const k = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight)), c = document.createElement("canvas");
      c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
      c.getContext("2d").drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url);
      c.toBlob(b => resolve(b || file), "image/jpeg", 0.86);
    };
    img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
    img.src = url;
  });
}
document.querySelectorAll(".slot").forEach(el => {
  const slot = el.dataset.slot, input = el.querySelector("input"), label = el.querySelector(".upl");
  input.addEventListener("change", async () => {
    const file = input.files[0]; input.value = ""; if (!file) return;
    if (!onSite) { $("photoNote").textContent = "Upload works once the system is on your site."; return; }
    label.classList.add("busy"); $("photoNote").textContent = "Uploading…";
    try {
      const fd = new FormData(); fd.append("photo", await shrink(file), slot + ".jpg");
      const d = await api("upload", { method: "POST", form: fd, query: "&slot=" + slot });
      showPhotos(d.photos); $("photoNote").textContent = "Saved. The invitation now shows this photo.";
    } catch (e) { $("photoNote").textContent = "Couldn’t upload: " + e.message; }
    label.classList.remove("busy");
  });
  el.querySelector(".rm").addEventListener("click", async () => {
    try { const d = await api("remove", { method: "POST", query: "&slot=" + slot }); showPhotos(d.photos); $("photoNote").textContent = "Removed. The invitation is back to its built-in artwork."; }
    catch (e) { $("photoNote").textContent = "Couldn’t remove: " + e.message; }
  });
});

/* ---------- save as PDF ---------- */
function esc(t) { const d = document.createElement("div"); d.textContent = t == null ? "" : String(t); return d.innerHTML; }
function fmt(t) { const d = t ? new Date(t) : null; return d && !isNaN(d) ? d.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" }) : ""; }
$("savePdf").addEventListener("click", () => {
  const by = n => rows.filter(r => r.status === n).sort((a, b) => a.name.localeCompare(b.name));
  const yes = by("yes"), no = by("no"), wait = by("pending");
  const guests = yes.reduce((a, r) => a + (Number(r.guests) || 0), 0), seats = rows.reduce((a, r) => a + (Number(r.seats) || 0), 0);
  let h = `<h1>${esc(couple())} · Guest list</h1><p class="sub">Generated ${esc(new Date().toLocaleString())}</p>`;
  h += `<div class="sum"><div><b>${guests}</b><span>Guests coming</span></div><div><b>${yes.length}</b><span>Accepted</span></div><div><b>${no.length}</b><span>Declined</span></div><div><b>${wait.length}</b><span>No reply yet</span></div><div><b>${seats ? Math.round(guests / seats * 100) : 0}%</b><span>of ${seats} seats</span></div></div>`;
  h += `<h2>Coming (${yes.length} invitations · ${guests} guests)</h2>`;
  h += yes.length ? `<table><thead><tr><th>#</th><th>Invitation</th><th class="num">Guests</th><th>Guest names</th><th>Replied</th></tr></thead><tbody>` +
    yes.map((r, i) => `<tr><td>${i + 1}</td><td>${esc(r.name)}</td><td class="num">${esc(r.guests)} / ${esc(r.seats)}</td><td>${esc(r.names)}</td><td>${esc(fmt(r.time))}</td></tr>`).join("") + `</tbody></table>` : `<p>No one yet.</p>`;
  const simple = (title, list) => `<h2>${title} (${list.length})</h2>` + (list.length ? `<table><thead><tr><th>#</th><th>Invitation</th><th class="num">Seats</th><th>Phone</th></tr></thead><tbody>` +
    list.map((r, i) => `<tr><td>${i + 1}</td><td>${esc(r.name)}</td><td class="num">${esc(r.seats)}</td><td>${esc(r.phone)}</td></tr>`).join("") + `</tbody></table>` : `<p>No one.</p>`);
  h += simple("Declined", no) + simple("No reply yet", wait);
  $("report").innerHTML = h;
  const old = document.title;
  document.title = couple().replace(/\s*&\s*/g, "-").replace(/\s+/g, "-") + "-guest-list-" + new Date().toISOString().slice(0, 10);
  setTimeout(() => { window.print(); document.title = old; }, 50);
});

/* ---------- start ---------- */
async function boot() {
  if (!onSite) { details = { ...DEMO_DETAILS }; applyDetails(); fillForm(); showPhotos({}); load(); return; }
  try {
    const me = await api("me");
    if (!me.loggedIn) { showGate(me.setup); return; }
    csrf = me.csrf; details = (me.details && Object.keys(me.details).length) ? me.details : { ...DEMO_DETAILS };
    $("logout").hidden = false;
    applyDetails(); fillForm(); showPhotos(me.photos); load();
    if (!timer) timer = setInterval(load, 60000);
  } catch (e) {
    $("banner").hidden = false; $("banner").innerHTML = "<b>Couldn’t reach the system.</b> "; $("banner").appendChild(document.createTextNode(e.message));
  }
}
boot();
</script>
</body>
</html>

__ADMIN_HTML__;
}
