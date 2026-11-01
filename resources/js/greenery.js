// Generated SVG artwork: paper grain, the RSVP wax seal, and the botanical garland and corner sprigs.
// A seeded random generator keeps every drawing identical on every load.

// Colours come from the CSS tokens in invite.css (see usePalette). Deep burgundy leads, dusty and soft rose
// follow, and a few pale pink leaves give depth. Repeats in this list weight the random pick.
const LEAF_TOKENS = ['--leaf-1', '--leaf-1', '--leaf-2', '--leaf-3', '--leaf-3', '--leaf-4', '--leaf-5'];
const P = {};

// The same values as invite.css. iOS Safari can run this module before the stylesheet has applied, and
// getPropertyValue then returns '' (an empty fill paints solid black), so each token falls back to its colour.
const FALLBACK = {
  '--leaf-1': '#781D2B',
  '--leaf-2': '#93414C',
  '--leaf-3': '#B66C72',
  '--leaf-4': '#D99A9C',
  '--leaf-5': '#EBCFCC',
  '--stem': '#781D2B',
  '--flower': '#FFFDF8',
  '--flower-eye': '#C6A66B',
  '--rsvp-hi': '#B66C72',
  '--rsvp-mid': '#781D2B',
  '--rsvp-lo': '#4E111B',
  '--rsvp-ink': '#FFFDF8'
};

/** Reads the artwork colours from the page's CSS custom properties. Call once before drawing. */
export function usePalette(root = document.documentElement) {
  const css = getComputedStyle(root);
  const v = name => css.getPropertyValue(name).trim() || FALLBACK[name];
  P.leaves = LEAF_TOKENS.map(v);
  for (const k of ['stem', 'flower', 'flower-eye']) P[k] = v('--' + k);
  P.rsvp = { hi: v('--rsvp-hi'), mid: v('--rsvp-mid'), lo: v('--rsvp-lo'), ink: v('--rsvp-ink') };
  return P;
}

export function grainURI(op) {
  const s = `<svg xmlns='http://www.w3.org/2000/svg' width='240' height='240'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 .45 0 0 0 0 .39 0 0 0 0 .36 0 0 0 1 0'/></filter><rect width='100%' height='100%' filter='url(#n)' opacity='${op}'/></svg>`;
  return `url("data:image/svg+xml,${encodeURIComponent(s)}")`;
}

let sealId = 0;
/** An SVG wax seal: wavy-edged blob, radial gradient, inner rings and text. */
export function sealSVG(text, c) {
  const id = 'w' + (sealId++);
  let d = '';
  for (let i = 0; i <= 90; i++) {
    const t = i / 90 * Math.PI * 2;
    const r = 45 + 2.6 * Math.sin(t * 9 + .4) + 1.3 * Math.sin(t * 4 + 1.7) + .8 * Math.sin(t * 17);
    d += (i ? 'L' : 'M') + (Math.cos(t) * r).toFixed(2) + ' ' + (Math.sin(t) * r).toFixed(2);
  }
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '-50 -50 100 100');
  svg.setAttribute('aria-hidden', 'true');
  svg.innerHTML = `
    <defs>
      <radialGradient id="${id}a" cx="35%" cy="30%" r="80%"><stop offset="0" stop-color="${c.hi}"/><stop offset=".6" stop-color="${c.mid}"/><stop offset="1" stop-color="${c.lo}"/></radialGradient>
      <radialGradient id="${id}b" cx="60%" cy="65%" r="70%"><stop offset="0" stop-color="${c.mid}"/><stop offset="1" stop-color="${c.hi}"/></radialGradient>
    </defs>
    <path d="${d}Z" fill="url(#${id}a)"/>
    <circle r="32" fill="url(#${id}b)"/>
    <circle r="32" fill="none" stroke="${c.ink}" stroke-opacity=".45" stroke-width="1"/>
    <circle r="28.5" fill="none" stroke="${c.ink}" stroke-opacity=".25" stroke-width=".6"/>`;
  const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
  label.setAttribute('x', '0');
  label.setAttribute('y', String(c.dy));
  label.setAttribute('text-anchor', 'middle');
  label.setAttribute('font-family', "'Cormorant Garamond', Georgia, serif");
  label.setAttribute('font-style', c.italic ? 'italic' : 'normal');
  label.setAttribute('font-weight', '500');
  label.setAttribute('font-size', String(c.size));
  label.setAttribute('fill', c.ink);
  label.setAttribute('fill-opacity', '.85');
  label.textContent = text; // the initials come from data, so never through innerHTML
  svg.appendChild(label);
  return svg;
}

/* ---------- Botanical line work (matches the embossed branches on the envelope art) ---------- */

const rng = seed => { let x = seed; return () => (x = (x * 16807) % 2147483647) / 2147483647; };
const f = n => n.toFixed(1);
const mirror = (c, w) => c.map(([x, y]) => [w - x, y]);

/** Point and tangent angle (degrees) at t on a cubic Bézier given as four [x, y] points. */
function bez([p0, p1, p2, p3], t) {
  const u = 1 - t;
  const at = i => u * u * u * p0[i] + 3 * u * u * t * p1[i] + 3 * u * t * t * p2[i] + t * t * t * p3[i];
  const d = i => 3 * u * u * (p1[i] - p0[i]) + 6 * u * t * (p2[i] - p1[i]) + 3 * t * t * (p3[i] - p2[i]);
  return [at(0), at(1), Math.atan2(d(1), d(0)) * 180 / Math.PI];
}

/** A slender, slightly curved pointed leaf with a pale midrib; base at (x, y), pointing along ang. */
function leaf(r, x, y, ang, len) {
  const w = len * (0.22 + r() * 0.07), bend = len * (r() - 0.5) * 0.12;
  const fill = P.leaves[Math.floor(r() * P.leaves.length)];
  return `<g transform="translate(${f(x)} ${f(y)}) rotate(${f(ang)})">`
    + `<path d="M0 0 Q${f(len * 0.4)} ${f(-w + bend)} ${f(len)} ${f(bend)} Q${f(len * 0.45)} ${f(w + bend)} 0 0Z" fill="${fill}" opacity="${(0.8 + r() * 0.2).toFixed(2)}"/>`
    + `<path d="M${f(len * 0.06)} 0 Q${f(len * 0.5)} ${f(bend * 0.6)} ${f(len * 0.85)} ${f(bend * 0.9)}" fill="none" stroke="${P.flower}" stroke-opacity=".45" stroke-width=".35"/></g>`;
}

/** A fine champagne-gold stalk ending in a small cluster of berries. */
function berries(r, x, y, ang) {
  const a = ang * Math.PI / 180, len = 7 + r() * 6, ex = x + Math.cos(a) * len, ey = y + Math.sin(a) * len;
  const line = (x1, y1, x2, y2, w) => `<path d="M${f(x1)} ${f(y1)} L${f(x2)} ${f(y2)}" stroke="${P['flower-eye']}" stroke-opacity=".8" stroke-width="${w}"/>`;
  let o = line(x, y, ex, ey, 0.5);
  const n = 3 + Math.floor(r() * 3);
  for (let i = 0; i < n; i++) {
    const b = a + (i - (n - 1) / 2) * 0.55, l = 2.5 + r() * 3, bx = ex + Math.cos(b) * l, by = ey + Math.sin(b) * l;
    o += line(ex, ey, bx, by, 0.4) + `<circle cx="${f(bx)}" cy="${f(by)}" r="${(0.9 + r() * 0.7).toFixed(2)}" fill="${P['flower-eye']}"/>`;
  }
  return o;
}

/** A thin stem along a Bézier with alternating leaves that taper toward a terminal leaf, plus optional berry stalks. */
function branch(r, c, { leaves = 8, len = 14, from = 0.1, berry = 0, width = 1 } = {}) {
  let o = `<path d="M${f(c[0][0])} ${f(c[0][1])} C${c.slice(1).map(p => `${f(p[0])} ${f(p[1])}`).join(' ')}" fill="none" stroke="${P.stem}" stroke-opacity=".6" stroke-width="${width}" stroke-linecap="round"/>`;
  for (let i = 0; i < berry; i++) {
    const [x, y, a] = bez(c, from + r() * (0.8 - from));
    o += berries(r, x, y, a + (r() < 0.5 ? -1 : 1) * (45 + r() * 20));
  }
  for (let i = 0; i < leaves; i++) {
    const t = from + (1 - from) * (i / leaves) * 0.96, [x, y, a] = bez(c, t), side = i % 2 ? 1 : -1;
    o += leaf(r, x, y, a + side * (28 + r() * 18), len * (1 - t * 0.4) * (0.85 + r() * 0.3));
  }
  const [tx, ty, ta] = bez(c, 1);
  o += leaf(r, tx, ty, ta, len * 0.6);
  return o;
}

/** Top of the hero: an airy swag with a spray hanging from each corner. */
export function garland() {
  const r = rng(7), W = 400;
  const corner = [
    [[[-6, 2], [34, 16], [62, 52], [78, 112]], { leaves: 14, len: 19, berry: 2 }],
    [[[-6, 4], [44, 2], [92, 14], [136, 40]], { leaves: 13, len: 17, berry: 2 }],
    [[[10, 4], [20, 36], [18, 66], [6, 96]], { leaves: 10, len: 15, berry: 1 }],
  ];
  let o = branch(r, [[-10, 16], [110, 96], [290, 96], [410, 16]], { leaves: 30, len: 13, from: 0.03, berry: 4, width: 0.8 });
  for (const [c, opt] of corner) o += branch(r, c, opt) + branch(r, mirror(c, W), opt);
  return `<svg viewBox="0 0 400 150" aria-hidden="true">${o}</svg>`;
}

/** Bottom corner: a few branches rising from the corner (the right one is mirrored in CSS). */
export function sprig(seed) {
  const r = rng(seed);
  let o = '';
  o += branch(r, [[0, 202], [56, 192], [108, 162], [160, 124]], { leaves: 15, len: 21, berry: 2 });
  o += branch(r, [[16, 212], [52, 176], [76, 138], [118, 96]], { leaves: 12, len: 18, berry: 2 });
  o += branch(r, [[6, 212], [20, 150], [44, 96], [84, 30]], { leaves: 17, len: 22, berry: 3 });
  return `<svg viewBox="0 0 200 210" aria-hidden="true">${o}</svg>`;
}
