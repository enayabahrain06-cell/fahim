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
const SCREENS = ['overview', 'guests', 'add', 'invitation'];
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

/* ---------- stats ---------- */
function renderStats(s) {
  countUp($('guestsComing'), s.guests_coming);
  countUp($('yesCount'), s.accepted);
  countUp($('noCount'), s.declined);
  countUp($('pendCount'), s.waiting);
  countUp($('ringPct'), s.acceptance_pct, '%');
  $('ringArc').setAttribute('stroke-dasharray', (s.acceptance_pct / 100 * 314.16).toFixed(1) + ' 314.16');
  $('ringArc').style.opacity = s.acceptance_pct ? 1 : 0;
  $('heroNote').textContent = s.invitations ? `${s.answered} of ${s.invitations} invitations answered` : 'No guests added yet';
  $('capVal').textContent = `${s.guests_coming} / ${s.total_seats}`;
  $('capBar').style.width = s.seats_pct + '%';
  $('capNote').textContent = s.total_seats
    ? `${s.seats_pct}% of the ${s.total_seats} invited seats are confirmed.`
    : 'Add your guests to track who is coming.';
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
const inviteText = r => `Dear ${r.name},\nWith joy, we invite you to the wedding of ${couple()}.\nPlease open your personal invitation and confirm your attendance:\n${r.link}`;
const WA_ICON = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z"/></svg>';

function setFilter(f) {
  filter = f;
  document.querySelectorAll('.tabs button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.f === f)));
  renderList();
}
document.querySelectorAll('.tabs button').forEach(b => b.addEventListener('click', () => setFilter(b.dataset.f)));
$('search').addEventListener('input', () => renderList());

function renderList() {
  const q = $('search').value.trim().toLowerCase();
  const shown = rows
    .filter(r => filter === 'all' || r.status === filter)
    .filter(r => !q || [r.name, ...(r.guest_names || []), r.phone || ''].join(' ').toLowerCase().includes(q))
    .sort((a, b) => (b.replied_at || '').localeCompare(a.replied_at || '') || b.id - a.id);

  const list = $('list');
  list.replaceChildren();
  if (!shown.length) {
    list.append(el('li', { className: 'empty', textContent: rows.length ? 'No one matches' : 'Your guests will appear here' }));
    return;
  }
  let fresh = 0;
  shown.forEach(r => {
    const key = [r.id, r.status, r.replied_at, r.name, r.seats, r.token, r.notes, r.phone, r.open_count].join('|');
    const li = el('li', { className: 'row' });
    if (!seen.has(key)) { seen.add(key); li.classList.add('new'); li.style.animationDelay = Math.min(fresh++, 12) * 45 + 'ms'; }

    const pill = el('span', {
      className: 'pill ' + (r.status === 'yes' ? 'yes' : r.status === 'no' ? 'no' : 'wait'),
      textContent: r.status === 'yes' ? `Coming · ${r.guests}` : r.status === 'no' ? 'Declined' : 'Waiting',
    });
    const meta = [
      `${r.seats} ${r.seats === 1 ? 'seat' : 'seats'}`,
      r.status === 'yes' && r.guest_names?.length ? r.guest_names.join(', ') : '',
      r.replied_at ? 'Replied ' + fmtDate(r.replied_at) : '',
      r.open_count ? `Opened ${r.open_count}×` : 'Not opened yet',
    ].filter(Boolean).join(' · ');

    const wa = el('a', { className: 'wa', target: '_blank', rel: 'noopener', href: `https://wa.me/${r.phone || ''}?text=${encodeURIComponent(inviteText(r))}` });
    wa.innerHTML = WA_ICON;
    wa.append('Send on WhatsApp');
    const copy = el('button', { type: 'button', textContent: 'Copy link' });
    copy.addEventListener('click', () => copyLink(r, copy));
    const more = el('button', { type: 'button', className: 'more', textContent: '•••', ariaLabel: `More for ${r.name}` });
    more.setAttribute('aria-label', `More actions for ${r.name}`);
    more.addEventListener('click', () => actionsSheet(r));

    li.append(el('span', { className: 'nm', textContent: r.name }), pill, el('span', { className: 'meta', textContent: meta }));
    if (r.notes) li.append(el('span', { className: 'private-note', textContent: r.notes }));
    li.append(el('div', { className: 'acts' }, wa, copy, more));
    list.append(li);
  });
}

async function copyLink(r, btn) {
  try {
    await navigator.clipboard.writeText(r.link);
    if (btn) { btn.textContent = 'Copied'; setTimeout(() => { btn.textContent = 'Copy link'; }, 1800); }
    toast('Link copied');
  } catch (e) {
    window.prompt('Copy this guest’s link:', r.link);
  }
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
    openSheet(title, el('p', { className: 'sheet-sub', textContent: message }), el('div', { className: 'sheet-actions' }, ok, cancel));
    ok.focus();
  });
}

function actionsSheet(r) {
  const btn = (label, fn, cls = 'secondary') => { const b = el('button', { type: 'button', className: cls, textContent: label }); b.addEventListener('click', fn); return b; };
  const open = el('a', { className: 'secondary', href: r.link, target: '_blank', rel: 'noopener', textContent: 'Open invitation' });
  const actions = [
    open,
    btn('Copy link', () => { sheet.close(); copyLink(r); }),
    btn('Edit', () => editSheet(r)),
    r.status !== 'pending' ? btn('Clear reply', () => act(r, 'reset', 'Clear reply?', `Clear the reply from ${r.name}? They can answer again from their link.`, 'Clear reply')) : null,
    btn('New link', () => act(r, 'regenerate', 'Make a new link?', `The link you already sent to ${r.name} will stop working. Send them the new one.`, 'Make new link')),
    btn('Remove', () => act(r, 'destroy', 'Remove guest?', `Remove ${r.name}? Their link will stop working.`, 'Remove', true), 'secondary danger'),
  ];
  const sub = r.status === 'yes' ? `Coming with ${r.guests} of ${r.seats}` : r.status === 'no' ? 'Declined' : `Waiting · ${r.seats} ${r.seats === 1 ? 'seat' : 'seats'}`;
  openSheet(r.name, el('p', { className: 'sheet-sub', textContent: sub }), el('div', { className: 'sheet-actions' }, ...actions));
}

async function act(r, action, title, message, okLabel, danger = false) {
  if (!await confirmSheet(title, message, okLabel, danger)) return;
  try {
    if (action === 'destroy') await api('DELETE', `/admin/invitees/${r.id}`);
    else await api('POST', `/admin/invitees/${r.id}/${action}`);
    toast(action === 'destroy' ? `${r.name} removed` : action === 'reset' ? 'Reply cleared' : 'New link ready. Send it to the guest.');
    await load();
  } catch (e) { toast(e.message, true); }
}

function editSheet(r) {
  const field = (label, props) => el('label', { className: props.wide ? 'wide' : '' }, label, el('input', props));
  const form = el('form', { className: 'form', noValidate: true },
    field('Name', { name: 'name', value: r.name, maxLength: 120, required: true, wide: true }),
    field('Seats', { name: 'seats', type: 'number', min: 1, max: CFG.maxSeats, value: r.seats, inputMode: 'numeric' }),
    field('WhatsApp number', { name: 'phone', type: 'tel', inputMode: 'tel', value: r.phone || '', maxLength: 24 }),
    el('label', { className: 'wide' }, 'Private note (only you see it)', el('textarea', { name: 'notes', rows: 2, maxLength: 255, value: r.notes || '' })),
  );
  const err = el('p', { className: 'sheet-err wide', role: 'alert' });
  const save = el('button', { type: 'submit', className: 'primary wide', textContent: 'Save' });
  form.append(err, save);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    save.disabled = true; err.textContent = '';
    const f = form.elements;
    try {
      await api('PATCH', `/admin/invitees/${r.id}`, { json: { name: f.name.value.trim(), seats: Number(f.seats.value) || 1, phone: f.phone.value, notes: f.notes.value.trim() || null } });
      sheet.close(); toast('Saved'); await load();
    } catch (e2) { err.textContent = e2.message; save.disabled = false; }
  });
  openSheet('Edit guest', form);
  form.elements.name.focus();
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

/* ---------- adding guests ---------- */
$('addForm').addEventListener('submit', async e => {
  e.preventDefault();
  const f = e.target, btn = f.querySelector('button');
  btn.disabled = true;
  try {
    const r = await api('POST', '/admin/invitees', { json: { name: f.name.value.trim(), seats: Number(f.seats.value) || 1, phone: f.phone.value } });
    $('addNote').textContent = `Added ${r.invitee.name}. Send them their link from the guest list.`;
    toast(`Added ${r.invitee.name}`);
    f.reset(); f.seats.value = 1; f.name.focus();
    await load();
  } catch (err) {
    $('addNote').textContent = 'Couldn’t add: ' + err.message;
    toast(err.message, true);
  }
  btn.disabled = false;
});

function bulkReport(r) {
  const box = $('bulkReport');
  box.replaceChildren(el('p', { textContent: `Added ${r.added} ${r.added === 1 ? 'guest' : 'guests'}.` }));
  if (r.skipped.length) {
    box.append(el('p', { textContent: `Skipped ${r.skipped.length} ${r.skipped.length === 1 ? 'line' : 'lines'}:` }),
      el('ul', {}, ...r.skipped.map(s => el('li', { textContent: `Line ${s.line} (“${s.text}”): ${s.reason}` }))));
  }
  box.hidden = false;
  toast(`Added ${r.added} ${r.added === 1 ? 'guest' : 'guests'}`, !r.added);
}
async function bulkSend(opts, btn) {
  btn?.classList.add('busy');
  try {
    const r = await api('POST', '/admin/invitees/bulk', opts);
    bulkReport(r);
    if (r.added && !r.skipped.length) $('bulkText').value = '';
    await load();
  } catch (e) { toast(e.message, true); }
  btn?.classList.remove('busy');
}
$('bulkAdd').addEventListener('click', e => {
  const text = $('bulkText').value.trim();
  if (!text) { toast('Write at least one guest, one per line.', true); return; }
  bulkSend({ json: { text } }, e.currentTarget);
});
$('bulkFile').addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;
  const form = new FormData(); form.append('file', file);
  bulkSend({ form }, e.target.closest('label'));
  e.target.value = '';
});

/* ---------- photos ---------- */
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
function showPhotos(photos) {
  document.querySelectorAll('.slot').forEach(slot => {
    const url = photos[slot.dataset.slot], thumb = slot.querySelector('.thumb');
    thumb.replaceChildren(url ? el('img', { src: url, alt: slot.querySelector('.slot-name').textContent }) : el('span', { textContent: 'Built-in artwork' }));
    slot.querySelector('.rm').hidden = !url;
  });
}
document.querySelectorAll('.slot').forEach(slot => {
  const name = slot.dataset.slot, input = slot.querySelector('input[type=file]'), label = slot.querySelector('.upl');
  input.addEventListener('change', async () => {
    const file = input.files[0];
    if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { toast('Please choose a JPG, PNG or WebP image.', true); input.value = ''; return; }
    label.classList.add('busy'); $('photoNote').textContent = 'Uploading…';
    try {
      const form = new FormData(); form.append('photo', await shrink(file));
      const r = await api('POST', `/admin/photos/${name}`, { form });
      showPhotos(r.photos);
      $('photoNote').textContent = 'Saved. The invitation now shows your photo.';
      toast('Photo saved');
    } catch (e) { $('photoNote').textContent = 'Couldn’t upload: ' + e.message; toast(e.message, true); }
    label.classList.remove('busy'); input.value = '';
  });
  slot.querySelector('.rm').addEventListener('click', async () => {
    if (!await confirmSheet('Remove photo?', 'The invitation will show its built-in artwork again.', 'Remove', true)) return;
    try {
      const r = await api('DELETE', `/admin/photos/${name}`);
      showPhotos(r.photos); toast('Photo removed');
    } catch (e) { toast(e.message, true); }
  });
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

$('detailsForm').addEventListener('submit', async e => {
  e.preventDefault();
  const form = e.target, btn = $('saveDetails');
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
  btn.disabled = true; $('detailsNote').textContent = 'Saving…';
  try {
    const r = await api('PUT', '/admin/details', { json: data });
    details = r.details;
    $('coupleTitle').textContent = couple();
    document.title = couple() + ' · Dashboard';
    $('detailsNote').textContent = 'Saved. The invitation is updated.';
    toast('Details saved');
    renderList(); // WhatsApp messages use the couple's names
  } catch (err) {
    $('detailsNote').textContent = 'Couldn’t save: ' + err.message;
    Object.keys(err.data?.errors || {}).forEach(k => form.elements[k.split('.')[0]]?.classList?.add('invalid'));
    toast(err.message, true);
  }
  btn.disabled = false;
});

/* ---------- start ---------- */
showScreen();
load();
