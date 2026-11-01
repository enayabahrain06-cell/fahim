// The couple's dashboard: tab screens, live stats, the guest list and its actions, photos and details.

const $ = id => document.getElementById(id);
const CFG = JSON.parse($('config').textContent);
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const TZ = CFG.timezone;
const POLL_MS = 60000;

let details = CFG.details;
let rows = [];
let filter = 'all';
const seen = new Set();

/* ---------- small helpers ---------- */
const store = {
  get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
};
const couple = () => `${details.groom} & ${details.bride}`;
const el = (tag, props = {}, ...kids) => { const n = Object.assign(document.createElement(tag), props); n.append(...kids.filter(k => k != null)); return n; };
const fmtDate = t => { const d = t ? new Date(t) : null; return d && !isNaN(d) ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', timeZone: TZ }) : ''; };
const fmtTime = d => d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', timeZone: TZ });

let toastTimer;
function toast(msg, error = false) {
  const t = $('toast');
  t.textContent = msg; t.classList.toggle('error', error); t.hidden = false;
  t.style.animation = 'none'; void t.offsetWidth; t.style.animation = '';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.hidden = true; }, error ? 5000 : 2600);
}

class ApiError extends Error {
  constructor(msg, data) { super(msg); this.data = data; }
}
async function api(method, url, { json, form } = {}) {
  const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' };
  let body;
  if (json !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(json); }
  if (form) body = form;
  let res;
  try {
    res = await fetch(url, { method, headers, body, credentials: 'same-origin' });
  } catch (e) {
    throw new ApiError('You seem to be offline. Please check your connection.');
  }
  const data = await res.json().catch(() => null);
  if (res.status === 401) { location.href = '/admin/login'; throw new ApiError('Please sign in again.'); }
  if (!res.ok || !data || data.ok === false) throw new ApiError(data?.error || `Server error (${res.status}).`, data);
  return data;
}

function countUp(node, to, suffix = '') {
  const from = Number(node.dataset.v || 0);
  node.dataset.v = to;
  if (from === to || calm) { node.textContent = to + suffix; return; }
  if (node.dataset.started) { node.classList.remove('bump'); void node.offsetWidth; node.classList.add('bump'); }
  node.dataset.started = '1';
  const t0 = performance.now(), dur = 900;
  const step = now => {
    const p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
    node.textContent = Math.round(from + (to - from) * e) + suffix;
    if (p < 1 && Number(node.dataset.v) === to) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/* ---------- screens (hash routing, so the back button works) ---------- */
const SCREENS = ['overview', 'guests', 'invitation'];
function showScreen() {
  const name = SCREENS.includes(location.hash.slice(1)) ? location.hash.slice(1) : 'overview';
  SCREENS.forEach(s => { $('screen-' + s).hidden = s !== name; });
  document.querySelectorAll('.tabbar a').forEach(a => {
    if (a.dataset.tab === name) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
  });
  if (name === 'guests') markRepliesSeen();
  window.scrollTo(0, 0);
}
window.addEventListener('hashchange', showScreen);
const appbar = document.querySelector('.appbar');
window.addEventListener('scroll', () => appbar.classList.toggle('scrolled', window.scrollY > 4), { passive: true });
// The guest-list filters stick right under the app bar, whatever its height.
new ResizeObserver(() => document.documentElement.style.setProperty('--appbar-h', appbar.offsetHeight + 'px')).observe(appbar);
document.querySelectorAll('.stat[data-filter]').forEach(a => a.addEventListener('click', () => setFilter(a.dataset.filter)));

/* ---------- latest replies on the overview ---------- */
let recentShown = 5;
function renderRecent() {
  const all = rows.filter(r => r.replied_at).sort((a, b) => b.replied_at.localeCompare(a.replied_at));
  const latest = all.slice(0, recentShown);
  const items = latest.map(r => {
    const name = r.guest_names?.[0] || r.name;
    const pill = el('span', { className: 'pill ' + (r.status === 'yes' ? 'yes' : 'no'), textContent: r.status === 'yes' ? `Respondent · ${r.guests}` : 'Declined' });
    const meta = [name !== r.name ? `Invited as ${r.name}` : '', r.phone ? '+' + r.phone : '', `Replied on ${fmtDate(r.replied_at)} at ${fmtTime(new Date(r.replied_at))}`].filter(Boolean).join(' · ');
    const li = el('li', { className: 'row tap' }, el('span', { className: 'nm', textContent: name }), pill, el('span', { className: 'meta', textContent: meta }));
    li.tabIndex = 0;
    li.setAttribute('role', 'button');
    li.setAttribute('aria-label', `Details for ${name}`);
    li.addEventListener('click', () => detailsSheet(r));
    li.addEventListener('keydown', e => { if ((e.key === 'Enter' || e.key === ' ') && e.target === li) { e.preventDefault(); detailsSheet(r); } });
    return li;
  });
  if (!items.length) items.push(el('li', { className: 'note', textContent: 'No replies yet.' }));
  $('recent').replaceChildren(...items);
  const more = $('recentMore');
  more.hidden = all.length <= recentShown;
  more.textContent = `View more (${all.length - recentShown} left)`;
}

$('recentMore').addEventListener('click', () => { recentShown += 10; renderRecent(); });

/* ---------- stats ---------- */
function renderStats(s) {
  countUp($('guestsComing'), s.guests_coming);
  countUp($('yesCount'), s.accepted);
  countUp($('noCount'), s.declined);
  countUp($('kComing'), s.guests_coming);
  countUp($('kDeclined'), s.declined);
  countUp($('ringPct'), s.acceptance_pct, '%');
  $('ringArc').setAttribute('stroke-dasharray', (s.acceptance_pct / 100 * 314.16).toFixed(1) + ' 314.16');
  $('ringArc').style.opacity = s.acceptance_pct ? 1 : 0;
  $('heroNote').textContent = s.answered ? `${s.accepted} yes · ${s.declined} no` : 'No replies yet';
}

/* ---------- new-reply badge on the Guests tab ---------- */
function markRepliesSeen() {
  store.set('lastSeenReply', new Date().toISOString());
  $('badge').hidden = true;
}
function updateBadge() {
  const last = store.get('lastSeenReply');
  if (!last) { markRepliesSeen(); return; }
  const fresh = rows.filter(r => r.replied_at && r.replied_at > last).length;
  const onGuests = !$('screen-guests').hidden;
  if (onGuests) { markRepliesSeen(); return; }
  $('badge').textContent = fresh > 9 ? '9+' : fresh;
  $('badge').hidden = !fresh;
  $('badge').setAttribute('aria-label', `${fresh} new ${fresh === 1 ? 'reply' : 'replies'}`);
}

/* ---------- guest list ---------- */

function setFilter(f) {
  filter = f;
  page = 1;
  document.querySelectorAll('.tabs button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.f === f)));
  renderList();
}
document.querySelectorAll('.tabs button').forEach(b => b.addEventListener('click', () => setFilter(b.dataset.f)));
$('search').addEventListener('input', () => { page = 1; renderList(); });

// With 100+ guests the list is split into pages of 20.
const PAGE_AT = 100, PER_PAGE = 20;
let page = 1;

function renderPager(total, pages) {
  const old = $('pager');
  if (old) old.remove();
  if (pages < 2) return;
  const nav = el('nav', { id: 'pager', className: 'pager' });
  nav.setAttribute('aria-label', 'Guest list pages');
  const go = (label, n, text, current) => {
    const b = el('button', { type: 'button', textContent: text });
    b.setAttribute('aria-label', label);
    if (current) b.setAttribute('aria-current', 'page');
    if (n < 1 || n > pages || current) b.disabled = true;
    b.addEventListener('click', () => { page = n; renderList(); $('list').scrollIntoView({ block: 'start' }); });
    return b;
  };
  nav.append(go('Previous page', page - 1, '‹'));
  const nums = [...new Set([1, page - 1, page, page + 1, pages].filter(n => n >= 1 && n <= pages))].sort((a, b) => a - b);
  let last = 0;
  nums.forEach(n => {
    if (n - last > 1) nav.append(el('span', { className: 'gap', textContent: '…' }));
    nav.append(go(`Page ${n}`, n, String(n), n === page));
    last = n;
  });
  nav.append(go('Next page', page + 1, '›'));
  $('list').after(nav);
}

/* Select mode: tick several guests, then delete them in one go. */
let selecting = false;
const selected = new Set();
let shownMatches = [];

function updateSelBar() {
  const n = selected.size;
  $('selbar').hidden = !selecting;
  $('selCount').textContent = `${n} selected`;
  $('selDelete').disabled = !n;
  $('selDelete').textContent = n ? `Delete (${n})` : 'Delete';
  $('selAll').textContent = shownMatches.length && shownMatches.every(r => selected.has(r.id)) ? 'Select none' : 'Select all';
  $('selectMode').textContent = selecting ? 'Cancel' : 'Select';
  $('selectMode').setAttribute('aria-pressed', String(selecting));
  document.body.classList.toggle('selecting', selecting);
}
function setSelecting(on) {
  selecting = on;
  if (!on) selected.clear();
  renderList();
}
$('selectMode').addEventListener('click', () => setSelecting(!selecting));
$('selAll').addEventListener('click', () => {
  const all = shownMatches.length && shownMatches.every(r => selected.has(r.id));
  shownMatches.forEach(r => all ? selected.delete(r.id) : selected.add(r.id));
  renderList();
});
$('selDelete').addEventListener('click', async () => {
  const ids = [...selected];
  if (!ids.length) return;
  const label = `${ids.length} ${ids.length === 1 ? 'guest' : 'guests'}`;
  if (!await confirmSheet(`Remove ${label}?`, `This removes ${label} and their replies from the list. It can’t be undone.`, `Remove ${label}`, true)) return;
  try {
    const res = await api('DELETE', '/admin/invitees', { json: { ids } });
    toast(`${res.removed} ${res.removed === 1 ? 'guest' : 'guests'} removed`);
    setSelecting(false);
    await load();
  } catch (e) { toast(e.message, true); }
});

function renderList() {
  const q = $('search').value.trim().toLowerCase();
  const matches = rows
    .filter(r => filter === 'all' || r.status === filter)
    .filter(r => !q || [r.name, ...(r.guest_names || []), r.phone || ''].join(' ').toLowerCase().includes(q))
    .sort((a, b) => (b.replied_at || '').localeCompare(a.replied_at || '') || b.id - a.id);
  shownMatches = matches;
  for (const id of [...selected]) if (!rows.some(r => r.id === id)) selected.delete(id);
  const paged = rows.length >= PAGE_AT;
  const pages = paged ? Math.max(1, Math.ceil(matches.length / PER_PAGE)) : 1;
  if (page > pages) page = pages;
  const shown = paged ? matches.slice((page - 1) * PER_PAGE, page * PER_PAGE) : matches;
  renderPager(matches.length, pages);

  const list = $('list');
  list.replaceChildren();
  if (!shown.length) {
    list.append(el('li', { className: 'empty', textContent: rows.length ? 'No one matches' : 'Your guests will appear here' }));
    updateSelBar();
    return;
  }
  let fresh = 0;
  shown.forEach(r => {
    const key = [r.id, r.status, r.replied_at, r.name, r.seats, r.notes, r.phone].join('|');
    const li = el('li', { className: 'row' });
    if (!seen.has(key)) { seen.add(key); li.classList.add('new'); li.style.animationDelay = Math.min(fresh++, 12) * 45 + 'ms'; }

    const pill = r.status === 'pending' ? null : el('span', {
      className: 'pill ' + (r.status === 'yes' ? 'yes' : 'no'),
      textContent: r.status === 'yes' ? `Respondent · ${r.guests}` : 'Declined',
    });
    const meta = [
      r.status !== 'pending' && r.guest_names?.[0] && r.guest_names[0] !== r.name ? `Invited as ${r.name}` : '',
      r.replied_at ? `Replied on ${fmtDate(r.replied_at)} at ${fmtTime(new Date(r.replied_at))}` : `${r.seats} ${r.seats === 1 ? 'seat' : 'seats'} · No reply yet`,
    ].filter(Boolean).join(' · ');

    // The whole row opens the guest's details; in Select mode it ticks the guest instead.
    const who = (r.status !== 'pending' && r.guest_names?.[0]) || r.name;
    const act = selecting
      ? () => { selected.has(r.id) ? selected.delete(r.id) : selected.add(r.id); li.classList.toggle('selected', selected.has(r.id)); li.setAttribute('aria-checked', String(selected.has(r.id))); updateSelBar(); }
      : () => detailsSheet(r);
    li.classList.add('tap');
    li.tabIndex = 0;
    li.setAttribute('role', selecting ? 'checkbox' : 'button');
    li.setAttribute('aria-label', selecting ? `Select ${who}` : `Details for ${who}`);
    if (selecting) {
      li.classList.add('selectable');
      li.classList.toggle('selected', selected.has(r.id));
      li.setAttribute('aria-checked', String(selected.has(r.id)));
      li.append(el('span', { className: 'chk', ariaHidden: 'true' }));
    }
    li.addEventListener('click', act);
    li.addEventListener('keydown', e => { if ((e.key === 'Enter' || e.key === ' ') && e.target === li) { e.preventDefault(); act(); } });

    li.append(el('span', { className: 'nm', textContent: (r.status !== 'pending' && r.guest_names?.[0]) || r.name }), ...(pill ? [pill] : []), el('span', { className: 'meta', textContent: meta }));
    if (r.notes) li.append(el('span', { className: 'private-note', textContent: r.notes }));
    list.append(li);
  });
  updateSelBar();
}

/* ---------- bottom sheets ---------- */
const sheet = $('sheet');
sheet.addEventListener('click', e => { if (e.target === sheet) sheet.close(); });
function openSheet(title, ...content) {
  $('sheetTitle').textContent = title;
  $('sheetBody').replaceChildren(...content);
  if (!sheet.open) sheet.showModal();
}
function confirmSheet(title, message, okLabel, danger = false) {
  return new Promise(resolve => {
    const ok = el('button', { type: 'button', className: 'primary center', textContent: okLabel });
    if (danger) ok.style.background = 'var(--rose)';
    const cancel = el('button', { type: 'button', className: 'secondary center', textContent: 'Cancel' });
    let answer = false;
    ok.addEventListener('click', () => { answer = true; sheet.close(); });
    cancel.addEventListener('click', () => sheet.close());
    sheet.addEventListener('close', () => resolve(answer), { once: true });
    // Destructive actions: the button stays locked until "remove" is typed, and focus starts on Cancel.
    let guard = null;
    if (danger) {
      ok.disabled = true;
      guard = el('input', { type: 'text', placeholder: 'Type remove to confirm', autocomplete: 'off', autocapitalize: 'off', spellcheck: false, ariaLabel: 'Type remove to confirm' });
      guard.setAttribute('aria-label', 'Type remove to confirm');
      guard.addEventListener('input', () => { ok.disabled = guard.value.trim().toLowerCase() !== 'remove'; });
    }
    openSheet(title, el('p', { className: 'sheet-sub', textContent: message }), ...(guard ? [guard] : []), el('div', { className: 'sheet-actions' }, ok, cancel));
    (danger ? cancel : ok).focus();
  });
}

function detailsSheet(r) {
  const name = (r.status !== 'pending' && r.guest_names?.[0]) || r.name;
  const status = r.status === 'yes' ? `Respondent · ${r.guests} ${r.guests === 1 ? 'person' : 'people'}` : r.status === 'no' ? 'Declined' : 'No reply yet';
  const line = (label, value) => value ? el('div', { className: 'detail-row' }, el('span', { textContent: label }), el('b', { textContent: value })) : null;
  const names = (r.guest_names || []).join(', ');
  const phone = r.phone ? el('a', { href: `tel:+${r.phone}`, textContent: '+' + r.phone }) : null;
  const phoneRow = phone ? el('div', { className: 'detail-row' }, el('span', { textContent: 'WhatsApp' }), el('b', {}, phone)) : null;
  const close = el('button', { type: 'button', className: 'secondary center', textContent: 'Close' });
  close.addEventListener('click', () => sheet.close());
  const remove = el('button', { type: 'button', className: 'secondary danger center', textContent: 'Remove guest' });
  remove.addEventListener('click', () => removeGuest(r));
  openSheet(name,
    el('div', { className: 'details' },
      ...[
        line('Reply', status),
        line('Guest names', names && names !== name ? names : ''),
        line('Invited as', r.name !== name ? r.name : ''),
        line('Seats', r.seats ? String(r.seats) : ''),
        line('Replied', r.replied_at ? `${fmtDate(r.replied_at)} ${fmtTime(new Date(r.replied_at))}` : ''),
        phoneRow,
        line('Note', r.notes || ''),
      ].filter(Boolean)),
    el('div', { className: 'sheet-actions' }, close, remove));
}

async function removeGuest(r) {
  if (!await confirmSheet('Remove guest?', `Remove ${r.name} from the list?`, 'Remove', true)) return;
  try {
    await api('DELETE', `/admin/invitees/${r.id}`);
    toast(`${r.name} removed`);
    await load();
  } catch (e) { toast(e.message, true); }
}

/* ---------- loading ---------- */
let loading = false;
async function load() {
  if (loading) return;
  loading = true;
  $('refresh').classList.add('spin');
  try {
    const [s, list] = await Promise.all([api('GET', '/admin/stats'), api('GET', '/admin/invitees')]);
    rows = list.invitees;
    renderStats(s.stats);
    renderList();
    renderRecent();
    updateBadge();
    $('updated').textContent = 'Updated ' + fmtTime(new Date());
  } catch (e) {
    $('updated').textContent = 'Couldn’t refresh: ' + e.message;
  } finally {
    loading = false;
    setTimeout(() => $('refresh').classList.remove('spin'), 400);
  }
}
$('refresh').addEventListener('click', load);
setInterval(() => { if (document.visibilityState === 'visible') load(); }, POLL_MS);
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') load(); });

/* ---------- one link for everyone ---------- */
$('sharedWa')?.addEventListener('click', () => {
  const text = `${details.share_message_text}\n${$('sharedUrl').value}`;
  window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
});
$('sharedCopy')?.addEventListener('click', async e => {
  const btn = e.currentTarget, url = $('sharedUrl').value;
  try {
    await navigator.clipboard.writeText(url);
    btn.textContent = 'Copied'; setTimeout(() => { btn.textContent = 'Copy link'; }, 1800);
    toast('Link copied');
  } catch (err) {
    window.prompt('Copy this link:', url);
  }
});
/* ---------- photos ---------- */
/** Asks for the account password; resolves with it, or null if cancelled. The server checks it. */
function passwordSheet(message) {
  return new Promise(resolve => {
    const input = el('input', { type: 'password', placeholder: 'Password', autocomplete: 'current-password' });
    input.setAttribute('aria-label', 'Password');
    const ok = el('button', { type: 'button', className: 'primary center', textContent: 'Continue' });
    const cancel = el('button', { type: 'button', className: 'secondary center', textContent: 'Cancel' });
    let answer = null;
    const submit = () => { if (!input.value) { input.focus(); return; } answer = input.value; sheet.close(); };
    ok.addEventListener('click', submit);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
    cancel.addEventListener('click', () => sheet.close());
    sheet.addEventListener('close', () => resolve(answer), { once: true });
    openSheet('Enter your password', el('p', { className: 'sheet-sub', textContent: message }), input, el('div', { className: 'sheet-actions' }, ok, cancel));
    input.focus();
  });
}

/** Resizes on the phone first so uploads are quick; the server checks and re-encodes again. */
async function shrink(file, max = 1600) {
  try {
    const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
    const canvas = el('canvas', { width: Math.round(bmp.width * scale), height: Math.round(bmp.height * scale) });
    canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', .88));
    return blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : file;
  } catch (e) { return file; }
}
const pending = {}; // slot -> { file, url } chosen but not saved yet
let hiddenSlots = {};
function refreshSlot(slot, saved) {
  const name = slot.dataset.slot, p = pending[name], url = p ? p.url : (saved || slot.dataset.default);
  slot.querySelector('.thumb').replaceChildren(el('img', { src: url, alt: slot.querySelector('.slot-name').textContent }));
  slot.querySelector('.dl').href = saved || slot.dataset.default;
  slot.querySelector('.rm').disabled = !p && !saved;
  slot.dataset.saved = saved || '';
  const off = !!hiddenSlots[name] && !p;
  slot.classList.toggle('is-hidden', off);
  slot.querySelector('.hd').textContent = off ? 'Show on invitation' : 'Hide from invitation';
  slot.querySelector('.hd').disabled = !!p;
  $('photoSave').disabled = !Object.keys(pending).length;
}
function showPhotos(photos, hidden) {
  if (hidden) hiddenSlots = hidden;
  document.querySelectorAll('.slot').forEach(slot => refreshSlot(slot, photos[slot.dataset.slot]));
}
function dropPending(name) {
  if (pending[name]) { URL.revokeObjectURL(pending[name].url); delete pending[name]; }
}
document.querySelectorAll('.slot').forEach(slot => {
  const name = slot.dataset.slot, input = slot.querySelector('input[type=file]');
  hiddenSlots[name] = slot.classList.contains('is-hidden');
  refreshSlot(slot, slot.querySelector('.rm').disabled ? '' : slot.querySelector('.dl').getAttribute('href'));
  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) return;
    input.value = '';
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { toast('Please choose a JPG, PNG or WebP image.', true); return; }
    dropPending(name);
    pending[name] = { file, url: URL.createObjectURL(file) };
    refreshSlot(slot, slot.dataset.saved);
    $('photoNote').textContent = 'Tap Save photos to put your choice on the invitation.';
  });
  slot.querySelector('.hd').addEventListener('click', async () => {
    const hide = !hiddenSlots[name];
    const password = await passwordSheet('Changing the invitation photos needs your password.');
    if (!password) return;
    try {
      const r = await api('PUT', `/admin/photos/${name}/hidden`, { json: { hidden: hide, password } });
      showPhotos(r.photos, r.hidden);
      toast(hide ? 'Photo hidden from the invitation' : 'Photo shown on the invitation');
      $('photoNote').textContent = hide ? 'Hidden. The invitation shows no picture there.' : 'Shown again on the invitation.';
    } catch (e) { toast(e.message, true); }
  });
  slot.querySelector('.rm').addEventListener('click', async () => {
    if (pending[name]) { dropPending(name); refreshSlot(slot, slot.dataset.saved); $('photoNote').textContent = 'Choice discarded.'; return; }
    if (!await confirmSheet('Remove photo?', 'The invitation will show its built-in artwork again.', 'Remove', true)) return;
    const password = await passwordSheet('Changing the invitation photos needs your password.');
    if (!password) return;
    try {
      const r = await api('DELETE', `/admin/photos/${name}`, { json: { password } });
      showPhotos(r.photos, r.hidden); toast('Photo removed');
      $('photoNote').textContent = 'Photo removed. The invitation shows its built-in artwork.';
    } catch (e) { toast(e.message, true); }
  });
});
$('photoSave').addEventListener('click', async () => {
  const btn = $('photoSave'), names = Object.keys(pending);
  if (!names.length) return;
  const password = await passwordSheet('Changing the invitation photos needs your password.');
  if (!password) return;
  btn.disabled = true; btn.textContent = 'Saving…'; $('photoNote').textContent = 'Uploading…';
  try {
    let photos, hiddenNow;
    for (const name of names) {
      const form = new FormData(); form.append('photo', await shrink(pending[name].file)); form.append('password', password);
      const r = await api('POST', `/admin/photos/${name}`, { form });
      photos = r.photos; hiddenNow = r.hidden;
      dropPending(name);
    }
    showPhotos(photos, hiddenNow);
    $('photoNote').textContent = 'Saved. The invitation now shows your photos.';
    toast('Photos saved');
  } catch (e) { $('photoNote').textContent = 'Couldn’t save: ' + e.message; toast(e.message, true); }
  btn.textContent = 'Save photos'; btn.disabled = !Object.keys(pending).length;
});

/* ---------- music ---------- */
function showMusic(m) {
  $('musicName').textContent = m ? m.name : 'No music yet';
  const a = $('musicPreview');
  if (m) { a.src = m.url; a.hidden = false; } else { a.removeAttribute('src'); a.hidden = true; }
  $('musicRm').hidden = !m?.uploaded;
  $('cropBox').hidden = !m;
  $('cropStart').value = m?.start ? fmtClock(m.start) : '';
  $('cropEnd').value = m?.end ? fmtClock(m.end) : '';
}
const fmtClock = s => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
/** "1:05" or "65" to seconds; empty is null, anything else NaN. */
function parseClock(v) {
  v = v.trim();
  if (!v) return null;
  const m = v.match(/^(?:(\d+):)?(\d+(?:\.\d+)?)$/);
  return m ? (Number(m[1] || 0) * 60 + Number(m[2])) : NaN;
}
$('cropSetStart')?.addEventListener('click', () => { $('cropStart').value = fmtClock($('musicPreview').currentTime); });
$('cropSetEnd')?.addEventListener('click', () => { $('cropEnd').value = fmtClock($('musicPreview').currentTime); });
async function saveCrop(start, end, okMsg) {
  try {
    const r = await api('PUT', '/admin/music/crop', { json: { start, end } });
    showMusic(r.music);
    $('musicNote').textContent = okMsg; toast(okMsg);
  } catch (err) { $('musicNote').textContent = err.message; toast(err.message, true); }
}
$('cropSave')?.addEventListener('click', () => {
  const start = parseClock($('cropStart').value), end = parseClock($('cropEnd').value);
  if (Number.isNaN(start) || Number.isNaN(end)) { toast('Write the time like 1:05 or 65.', true); return; }
  saveCrop(start ?? 0, end, 'Saved. Guests hear only that part.');
});
$('cropReset')?.addEventListener('click', () => saveCrop(0, null, 'The whole song plays again.'));
$('musicFile')?.addEventListener('change', async e => {
  const input = e.target, file = input.files[0];
  if (!file) return;
  $('musicUpl').classList.add('busy'); $('musicNote').textContent = 'Uploading…';
  try {
    const form = new FormData(); form.append('music', file);
    const r = await api('POST', '/admin/music', { form });
    showMusic(r.music);
    $('musicNote').textContent = 'Saved. The invitation now plays your song.';
    toast('Music saved');
  } catch (err) { $('musicNote').textContent = 'Couldn’t upload: ' + err.message; toast(err.message, true); }
  $('musicUpl').classList.remove('busy'); input.value = '';
});
$('musicRm')?.addEventListener('click', async () => {
  if (!await confirmSheet('Remove your song?', 'The invitation goes back to the built-in music, if there is one.', 'Remove', true)) return;
  try { const r = await api('DELETE', '/admin/music'); showMusic(r.music); toast('Music removed'); } catch (err) { toast(err.message, true); }
});

/* ---------- invitation details ---------- */
const MAX_MOMENTS = 8;
function addMoment(m = { time: '', title: '' }) {
  const row = el('div', { className: 'sched-row' },
    el('input', { placeholder: '7:30 PM', value: m.time, maxLength: 20, ariaLabel: 'Time' }),
    el('input', { placeholder: 'e.g. Nikah ceremony', value: m.title, maxLength: 80, ariaLabel: 'What happens' }));
  row.querySelectorAll('input')[0].setAttribute('aria-label', 'Time');
  row.querySelectorAll('input')[1].setAttribute('aria-label', 'What happens');
  const rm = el('button', { type: 'button', textContent: '×' });
  rm.setAttribute('aria-label', 'Remove this moment');
  rm.addEventListener('click', () => { row.remove(); syncMomentButton(); });
  row.append(rm);
  $('scheduleRows').append(row);
  syncMomentButton();
}
function syncMomentButton() { $('addMoment').hidden = $('scheduleRows').children.length >= MAX_MOMENTS; }
(details.schedule || []).forEach(addMoment);
syncMomentButton();
$('addMoment').addEventListener('click', () => { addMoment(); $('scheduleRows').lastElementChild.querySelector('input').focus(); });

/* Verse picker: choose a ready verse (or write your own, or hide it). */
const versePick = $('versePick'), verseText = $('verseText');
function syncVersePick() {
  const v = verseText.value.trim();
  const match = [...versePick.options].find(o => o.value && o.value !== '__none' && o.value === v);
  versePick.value = match ? match.value : (v === '' ? '__none' : '');
}
versePick?.addEventListener('change', () => {
  if (versePick.value === '__none') verseText.value = '';
  else if (versePick.value) verseText.value = versePick.value;
  else verseText.focus();
});
verseText?.addEventListener('input', syncVersePick);
if (versePick) syncVersePick();

/** Saves the whole details form (plus any overrides) and refreshes everything that shows the details. */
async function saveDetails(overrides = {}, note = $('detailsNote')) {
  const form = $('detailsForm'), btn = $('saveDetails');
  const data = {};
  for (const f of form.elements) {
    if (!f.name) continue;
    data[f.name] = f.type === 'checkbox' ? f.checked : f.value.trim();
    f.classList.remove('invalid');
  }
  data.schedule = [...$('scheduleRows').children].map(r => {
    const [time, title] = r.querySelectorAll('input');
    return { time: time.value.trim(), title: title.value.trim() };
  }).filter(m => m.time || m.title);
  Object.assign(data, overrides);
  btn.disabled = true; note.textContent = 'Saving…';
  try {
    const r = await api('PUT', '/admin/details', { json: data });
    details = r.details;
    if ('share_message' in overrides) form.elements.share_message.value = overrides.share_message;
    $('coupleTitle').textContent = couple();
    document.title = couple() + ' · Dashboard';
    note.textContent = 'Saved. The invitation is updated.';
    toast('Details saved');
    renderList(); // WhatsApp messages use the couple's names
    renderWaMessage();
    btn.disabled = false;
    return true;
  } catch (err) {
    note.textContent = 'Couldn’t save: ' + err.message;
    Object.keys(err.data?.errors || {}).forEach(k => form.elements[k.split('.')[0]]?.classList?.add('invalid'));
    toast(err.message, true);
    btn.disabled = false;
    return false;
  }
}
$('detailsForm').addEventListener('submit', e => { e.preventDefault(); saveDetails(); });

/* ---------- edit the WhatsApp message in place ---------- */
function renderWaMessage() {
  const p = $('waMsg'); if (!p) return;
  const u = el('u', { textContent: $('sharedUrl').value });
  p.replaceChildren();
  details.share_message_text.split('\n').forEach((line, i) => { if (i) p.append(el('br')); p.append(line); });
  p.append(el('br'), u);
}
$('waEdit')?.addEventListener('click', () => {
  $('waEditor').hidden = false; $('waEdit').hidden = true;
  $('waText').value = details.share_message || details.share_message_text; $('waText').focus();
});
$('waCancel')?.addEventListener('click', () => { $('waEditor').hidden = true; $('waEdit').hidden = false; });
$('waSave')?.addEventListener('click', async () => {
  const text = $('waText').value.trim();
  if (await saveDetails({ share_message: text }, $('waNote'))) { $('waEditor').hidden = true; $('waEdit').hidden = false; }
});

/* ---------- start ---------- */
showScreen();
load();
