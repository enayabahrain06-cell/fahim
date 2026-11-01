import { grainURI, garland, sprig, usePalette } from './greenery.js';
import { Music } from './music.js';

import.meta.glob(['../images/**'], { eager: true }); // lets Blade use Vite::asset() for the bundled artwork

const $ = id => document.getElementById(id);
const CFG = JSON.parse($('config')?.textContent || '{}');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- Artwork ---------- */
document.querySelectorAll('.mini-env .grain').forEach(p => { p.style.backgroundImage = grainURI(.22); });
document.querySelectorAll('.hero .grain').forEach(p => { p.style.backgroundImage = grainURI(.06); });
const palette = usePalette();
$('garland').innerHTML = garland();
$('sprigL').innerHTML = sprig(3);
$('sprigR').innerHTML = sprig(11);

/* ---------- Gold dust ---------- */
if (!reduceMotion) {
  // One fixed layer over the page column, so the dust and petals drift past every page of the invitation.
  const hero = document.createElement('div');
  hero.className = 'fx';
  hero.setAttribute('aria-hidden', 'true');
  document.body.appendChild(hero);
  // Over the burgundy reply page the petals and dust fade right down, so they don't crowd the text and the envelope.
  const calmSecs = document.querySelectorAll('#rsvpSec, .olive-tail');
  if (calmSecs.length && 'IntersectionObserver' in window) {
    const inView = new Set();
    const io = new IntersectionObserver(es => {
      es.forEach(e => (e.isIntersecting ? inView.add(e.target) : inView.delete(e.target)));
      hero.classList.toggle('calm', inView.size > 0);
    }, { rootMargin: '-35% 0px -35% 0px' });
    calmSecs.forEach(s => io.observe(s));
  }
  for (let i = 0; i < 16; i++) {
    const s = document.createElement('span');
    s.className = 'spark';
    const z = 2 + Math.random() * 3;
    Object.assign(s.style, {
      left: (5 + Math.random() * 90) + '%', width: z + 'px', height: z + 'px',
      animationDuration: (9 + Math.random() * 8) + 's', animationDelay: (Math.random() * 10) + 's',
    });
    hero.appendChild(s);
  }

  /* Rose petals drifting down, in the softer leaf colours. */
  const petalColours = palette.leaves.slice(2);
  for (let i = 0; i < 9; i++) {
    const p = document.createElement('span');
    p.className = 'petal';
    const z = 7 + Math.random() * 6;
    Object.assign(p.style, {
      left: (Math.random() * 95) + '%', width: z + 'px', height: (z * .8) + 'px',
      background: petalColours[i % petalColours.length],
      animationDuration: (11 + Math.random() * 7) + 's', animationDelay: (1.5 + Math.random() * 12) + 's',
    });
    p.style.setProperty('--dx', ((Math.random() - .3) * 120).toFixed(0) + 'px');
    p.style.setProperty('--spin', (240 + Math.random() * 360).toFixed(0) + 'deg');
    hero.appendChild(p);
  }
}

/* ---------- Countdown ---------- */
const target = new Date(CFG.startsAt).getTime();
let cdTimer = null;
/** Updates a countdown number and replays its small tick animation when the value changes. */
function setDigit(id, value) {
  const el = $(id);
  if (el.textContent === value) return;
  el.textContent = value;
  el.classList.remove('bump');
  void el.offsetWidth; // restart the animation
  el.classList.add('bump');
}
function tick() {
  let diff = Math.max(0, target - Date.now());
  const d = Math.floor(diff / 864e5); diff -= d * 864e5;
  const h = Math.floor(diff / 36e5); diff -= h * 36e5;
  const m = Math.floor(diff / 6e4); diff -= m * 6e4;
  setDigit('cd-d', String(d));
  setDigit('cd-h', String(h).padStart(2, '0'));
  setDigit('cd-m', String(m).padStart(2, '0'));
  setDigit('cd-s', String(Math.floor(diff / 1e3)).padStart(2, '0'));
  if (target <= Date.now() && cdTimer) clearInterval(cdTimer);
}
if (isNaN(target)) $('cdSec').remove();
else { tick(); cdTimer = setInterval(tick, 1000); }

document.querySelectorAll('.hero-in > *').forEach((child, i) => child.style.setProperty('--i', i)); // staggers the opening lines

/* ---------- Reveal on scroll ---------- */
const io = 'IntersectionObserver' in window ? new IntersectionObserver(entries => entries.forEach(e => {
  if (e.isIntersecting) { e.target.classList.add('on'); io.unobserve(e.target); }
}), { threshold: .15 }) : null;
document.querySelectorAll('.reveal').forEach(el => {
  [...el.children].forEach((child, i) => child.style.setProperty('--i', i)); // staggers the lines in CSS
  io ? io.observe(el) : el.classList.add('on');
});

/* ---------- Music ---------- */
const ICON_PAUSE = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>';
const ICON_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>';
const musicBtn = $('music');
function setMusic(on) {
  if (!musicBtn) return;
  try { on ? Music.start(CFG.musicUrl, CFG.musicStart, CFG.musicEnd) : Music.stop(); } catch (e) { on = false; }
  musicBtn.setAttribute('aria-pressed', String(on));
  musicBtn.innerHTML = on ? ICON_PAUSE : ICON_PLAY;
}
if (musicBtn) { setMusic(false); musicBtn.addEventListener('click', () => setMusic(!Music.playing)); }
// Leaving the page (another app, a locked phone, a closed tab) must not leave the music playing; the button is a tap away on return.
document.addEventListener('visibilitychange', () => { if (document.hidden && Music.playing) setMusic(false); });
addEventListener('pagehide', () => { if (Music.playing) setMusic(false); });

/* ---------- Gentle auto-scroll down to the RSVP ---------- */
function autoScroll() {
  const target = $('rsvpSec');
  if (!target) return;
  const root = document.documentElement, prevBehavior = root.style.scrollBehavior;
  root.style.scrollBehavior = 'auto';
  const events = ['touchstart', 'wheel', 'mousedown', 'keydown'];
  let stop = false, last = null, v = 0, y = window.scrollY;
  const off = () => { events.forEach(e => window.removeEventListener(e, cancel)); root.style.scrollBehavior = prevBehavior; };
  function cancel() { stop = true; off(); }
  events.forEach(e => window.addEventListener(e, cancel, { passive: true }));
  const step = ts => {
    if (stop) return;
    if (last === null) last = ts;
    const dt = Math.min(0.05, (ts - last) / 1000); last = ts;
    const end = Math.min(target.getBoundingClientRect().top + window.scrollY - 24, root.scrollHeight - window.innerHeight);
    v = Math.min(35, v + 25 * dt);                               // ease in up to ~35 px/s
    const remain = end - y;
    const speed = remain < 320 ? Math.max(10, v * remain / 320) : v; // ease out near the end
    y = Math.min(end, y + speed * dt);
    window.scrollTo(0, y);
    if (y >= end - 0.5) { off(); $('openRsvp').classList.add('nudge'); return; }
    requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/* ---------- Open the envelope ---------- */
const intro = $('intro');
function openInvite() {
  if (intro.classList.contains('open')) return;
  if (CFG.music) setMusic(true);
  intro.classList.add('open');
  setTimeout(() => document.body.classList.remove('sealed'), reduceMotion ? 0 : 800);
  if (!reduceMotion) setTimeout(autoScroll, 2800); // ≈2 s after the page is revealed
}
intro.addEventListener('click', openInvite);

/* ---------- RSVP ---------- */
const INVITEE = CFG.invitee;
const MAX = Number(CFG.maxGuests) || 99; // guests may bring any number; this only stops a runaway tap
const dlg = $('rsvp'), form = $('rsvpForm'), sendBtn = $('send'), err = $('err');
let attending = null, count = 1;

$('closeRsvp').addEventListener('click', () => dlg.close());
dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });

/** Shows the number coming; only the number is asked, not the guests' names. */
function renderCount() {
  $('count').textContent = count;
  $('minus').disabled = count <= 1;
  $('plus').disabled = count >= MAX;
}

function setAttending(v) {
  attending = v;
  form.querySelectorAll('input[name="att"]').forEach(r => { r.checked = (r.value === 'yes') === v; });
  $('attendBlock').hidden = !v;
}
/** Pre-selects a returning guest's earlier answer so they can change it. */
function showPrevious() {
  if (!INVITEE || INVITEE.status === 'pending') return;
  const yes = INVITEE.status === 'yes', g = Number(INVITEE.guests) || 1;
  setAttending(yes);
  if (yes) { count = Math.min(MAX, g); renderCount(); }
}
// The guest's own name, filled in with the name they gave last time or the invited name; they can change it.
const nameInput = $('yourName');
nameInput.value = (INVITEE?.guest_names || [])[0] || INVITEE?.name || '';
nameInput.addEventListener('input', () => { err.textContent = ''; });
form.querySelectorAll('input[name="att"]').forEach(r => r.addEventListener('change', () => {
  setAttending(r.value === 'yes'); err.textContent = '';
}));
$('plus').addEventListener('click', () => { if (count < MAX) { count++; renderCount(); } });
$('minus').addEventListener('click', () => { if (count > 1) { count--; renderCount(); } });
renderCount();
showPrevious();

const env = $('openRsvp');
const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
if (!calm) {
  env.addEventListener('pointermove', e => {
    const r = env.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
    env.classList.add('tilting');
    env.style.setProperty('--ry', (x * 14) + 'deg'); env.style.setProperty('--rx', (-y * 14) + 'deg');
    env.style.setProperty('--mx', (x + .5) * 100 + '%'); env.style.setProperty('--my', (y + .5) * 100 + '%');
    env.style.setProperty('--px', (x * 10) + 'px'); env.style.setProperty('--py', (y * 8) + 'px');
  });
  env.addEventListener('pointerleave', () => {
    env.classList.remove('tilting'); ['--rx', '--ry', '--px', '--py'].forEach(v => env.style.removeProperty(v));
  });
}

function openCard() {
  $('done').hidden = true; form.hidden = false;
  sendBtn.disabled = false; sendBtn.textContent = 'Send RSVP'; err.textContent = '';
  showPrevious();
  dlg.showModal();
}
env.addEventListener('click', openCard);

form.addEventListener('submit', async e => {
  e.preventDefault();
  if (attending === null) { err.textContent = 'Choose whether you’ll attend.'; return; }
  const name = nameInput.value.trim();
  if (attending && !name) { err.textContent = 'Please enter your name.'; nameInput.focus(); return; }
  err.textContent = '';
  sendBtn.disabled = true; sendBtn.textContent = 'Sending…';
  try {
    const res = await fetch(CFG.rsvpUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
      body: JSON.stringify(attending ? { attending: true, count, name } : { attending: false }),
    });
    const out = await res.json().catch(() => ({}));
    if (!res.ok || !out.ok) throw new Error(out.error || 'failed');
    if (INVITEE) Object.assign(INVITEE, { status: attending ? 'yes' : 'no', guests: attending ? count : 0, guest_names: attending ? [name] : [] });
    form.hidden = true;
    const first = (name || INVITEE?.name || '').split(' ')[0];
    $('done').textContent = attending ? `Thank you, ${first}! We can’t wait to celebrate with you.` : 'Thank you for letting us know. You’ll be missed.';
    $('done').hidden = false;
  } catch (e2) {
    err.textContent = 'Couldn’t send. Please check your connection and try again.';
    sendBtn.disabled = false; sendBtn.textContent = 'Send RSVP';
  }
});
