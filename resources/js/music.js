// An original, royalty-free theme in maqam Nahawand on D (D E F G A B♭ C♯ D), synthesized live with the
// Web Audio API: nay-like melody, qanun-like arpeggios, oud-like bass, a soft string pad and a frame drum.

const SEMI = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };
const hz = n => {
  const m = n.match(/^([A-G])(#|b)?(\d)$/);
  return 440 * Math.pow(2, ((+m[3] + 1) * 12 + SEMI[m[1]] + (m[2] === '#' ? 1 : m[2] === 'b' ? -1 : 0) - 69) / 12);
};
const BEAT = 60 / 64, BAR = BEAT * 4;
// Dm – Gm – A – Dm – B♭ – Gm – A – Dm
const CHORDS = [['D3', 'A3', 'D4', 'F4'], ['G2', 'D3', 'G3', 'Bb3'], ['A2', 'E3', 'A3', 'C#4'], ['D3', 'A3', 'D4', 'F4'],
  ['Bb2', 'F3', 'Bb3', 'D4'], ['G2', 'D3', 'G3', 'Bb3'], ['A2', 'E3', 'A3', 'C#4'], ['D3', 'A3', 'D4', 'F4']];
// melody per bar: [note, beats]
const MELODY = [
  [['A4', 2], ['D5', 1], ['C#5', .5], ['D5', .5]],
  [['E5', 1], ['D5', 1], ['Bb4', 2]],
  [['C#5', 1], ['Bb4', .5], ['A4', .5], ['G4', 1], ['A4', 1]],
  [['F4', 1], ['E4', 1], ['D4', 2]],
  [['F4', 1], ['G4', .5], ['A4', .5], ['Bb4', 1], ['D5', 1]],
  [['Bb4', 1], ['A4', .5], ['G4', .5], ['D5', 2]],
  [['C#5', 1], ['E5', 1], ['D5', .5], ['C#5', .5], ['A4', 1]],
  [['D5', 3], [null, 1]],
];

let ctx, master, noise, timer, nextBar = 0, bar = 0, playing = false;

function build() {
  ctx = new (window.AudioContext || window.webkitAudioContext)();
  master = ctx.createGain();
  master.gain.value = 0;
  const conv = ctx.createConvolver(), len = Math.floor(ctx.sampleRate * 3.8), ir = ctx.createBuffer(2, len, ctx.sampleRate);
  for (let c = 0; c < 2; c++) {
    const ch = ir.getChannelData(c);
    for (let i = 0; i < len; i++) ch[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 2.2);
  }
  conv.buffer = ir;
  const wet = ctx.createGain();
  wet.gain.value = .6;
  const comp = ctx.createDynamicsCompressor();
  master.connect(comp); comp.connect(ctx.destination);
  master.connect(conv); conv.connect(wet); wet.connect(comp);
  noise = ctx.createBuffer(1, ctx.sampleRate * 2, ctx.sampleRate);
  const nd = noise.getChannelData(0);
  for (let i = 0; i < nd.length; i++) nd[i] = Math.random() * 2 - 1;
}

function pluck(f, t, vol, dur, bright) {
  const o1 = ctx.createOscillator(), o2 = ctx.createOscillator(), lp = ctx.createBiquadFilter(), g = ctx.createGain(), g2 = ctx.createGain();
  o1.type = 'triangle'; o2.type = bright ? 'sawtooth' : 'sine';
  o1.frequency.value = f; o2.frequency.value = f * 2.001;
  g2.gain.value = bright ? .18 : .3;
  lp.type = 'lowpass';
  lp.frequency.setValueAtTime(bright ? 5200 : 2600, t);
  lp.frequency.exponentialRampToValueAtTime(600, t + dur);
  g.gain.setValueAtTime(.0001, t);
  g.gain.exponentialRampToValueAtTime(vol, t + .005);
  g.gain.exponentialRampToValueAtTime(.0001, t + dur);
  o1.connect(lp); o2.connect(g2); g2.connect(lp); lp.connect(g); g.connect(master);
  o1.start(t); o2.start(t); o1.stop(t + dur + .05); o2.stop(t + dur + .05);
}

function nay(f, t, dur, vol) {
  const o = ctx.createOscillator(), o2 = ctx.createOscillator(), g = ctx.createGain(), vib = ctx.createOscillator(), vg = ctx.createGain(), mix = ctx.createGain();
  o.type = 'sine'; o2.type = 'triangle'; o.frequency.value = f; o2.frequency.value = f;
  vib.frequency.value = 5;
  vg.gain.setValueAtTime(0, t);
  vg.gain.linearRampToValueAtTime(f * .006, t + Math.min(.6, dur * .6));
  vib.connect(vg); vg.connect(o.frequency); vg.connect(o2.frequency);
  mix.gain.value = .35; o2.connect(mix);
  g.gain.setValueAtTime(.0001, t);
  g.gain.linearRampToValueAtTime(vol, t + .12);
  g.gain.setValueAtTime(vol, t + dur * .75);
  g.gain.linearRampToValueAtTime(.0001, t + dur + .25);
  o.connect(g); mix.connect(g); g.connect(master);
  // breath
  const n = ctx.createBufferSource(), bp = ctx.createBiquadFilter(), ng = ctx.createGain();
  n.buffer = noise; bp.type = 'bandpass'; bp.frequency.value = f * 2; bp.Q.value = 3;
  ng.gain.setValueAtTime(.0001, t);
  ng.gain.linearRampToValueAtTime(vol * .35, t + .06);
  ng.gain.linearRampToValueAtTime(vol * .08, t + .3);
  ng.gain.linearRampToValueAtTime(.0001, t + dur + .2);
  n.connect(bp); bp.connect(ng); ng.connect(master);
  [o, o2, vib].forEach(x => { x.start(t); x.stop(t + dur + .3); });
  n.start(t, Math.random()); n.stop(t + dur + .3);
}

function strings(notes, t, dur, vol) {
  notes.forEach(nm => [-7, 7].forEach(det => {
    const o = ctx.createOscillator(), lp = ctx.createBiquadFilter(), g = ctx.createGain();
    o.type = 'sawtooth'; o.frequency.value = hz(nm); o.detune.value = det;
    lp.type = 'lowpass'; lp.frequency.value = 950;
    g.gain.setValueAtTime(.0001, t);
    g.gain.linearRampToValueAtTime(vol, t + .9);
    g.gain.setValueAtTime(vol, t + dur - .3);
    g.gain.linearRampToValueAtTime(.0001, t + dur + .8);
    o.connect(lp); lp.connect(g); g.connect(master);
    o.start(t); o.stop(t + dur + .9);
  }));
}

function drum(t, low) {
  if (low) {
    const o = ctx.createOscillator(), g = ctx.createGain();
    o.frequency.setValueAtTime(110, t);
    o.frequency.exponentialRampToValueAtTime(60, t + .2);
    g.gain.setValueAtTime(.22, t);
    g.gain.exponentialRampToValueAtTime(.0001, t + .4);
    o.connect(g); g.connect(master); o.start(t); o.stop(t + .45);
  } else {
    const n = ctx.createBufferSource(), bp = ctx.createBiquadFilter(), g = ctx.createGain();
    n.buffer = noise; bp.type = 'bandpass'; bp.frequency.value = 3800; bp.Q.value = 1.2;
    g.gain.setValueAtTime(.06, t);
    g.gain.exponentialRampToValueAtTime(.0001, t + .07);
    n.connect(bp); bp.connect(g); g.connect(master); n.start(t, Math.random()); n.stop(t + .1);
  }
}

function scheduleBar(t, b) {
  const i = b % 8, loop = Math.floor(b / 8), c = CHORDS[i];
  strings(c.slice(1), t, BAR, loop === 0 && i < 2 ? .008 : .013);
  // oud-like bass on beats 1 and 3
  pluck(hz(c[0]), t, .2, 2.4, false);
  pluck(hz(c[0]), t + BEAT * 2, .12, 2, false);
  // qanun-like arpeggio in eighths
  [c[1], c[2], c[3], c[2], c[1], c[2], c[3], c[2]].map(n => hz(n) * 2)
    .forEach((f, k) => pluck(f, t + k * BEAT / 2, k % 2 ? .035 : .05, 1.1, true));
  // frame drum from bar 5
  if (loop >= 1 || i >= 4) { drum(t, true); drum(t + BEAT * 1.5, false); drum(t + BEAT * 2.5, true); drum(t + BEAT * 3, false); drum(t + BEAT * 3.5, false); }
  let x = 0;
  MELODY[i].forEach(([n, d]) => {
    if (n) {
      const f = hz(n), tt = t + x * BEAT, dd = d * BEAT;
      if (loop % 2 === 0) nay(f, tt, dd * .95, .1);
      else {
        // second time through: oud-like pluck with tremolo on long notes
        pluck(f, tt, .17, Math.max(1.2, dd * 1.6), true);
        if (d >= 2) [.25, .5, .75].forEach(q => pluck(f, tt + q * BEAT, .07, .5, true));
      }
    }
    x += d;
  });
}

function schedule() {
  while (nextBar < ctx.currentTime + .6) { scheduleBar(nextBar, bar); nextBar += BAR; bar++; }
}

export const Music = {
  start() {
    // Lets the music play on iPhone even when the silent switch is on.
    try { if (navigator.audioSession) navigator.audioSession.type = 'playback'; } catch (e) { /* not supported */ }
    if (!ctx) build();
    ctx.resume();
    if (playing) return;
    playing = true;
    nextBar = ctx.currentTime + .15;
    master.gain.cancelScheduledValues(ctx.currentTime);
    master.gain.setTargetAtTime(.8, ctx.currentTime, .8);
    timer = setInterval(schedule, 150);
    schedule();
  },
  stop() {
    if (!ctx || !playing) return;
    playing = false;
    clearInterval(timer);
    master.gain.setTargetAtTime(0, ctx.currentTime, .3);
  },
  get playing() { return playing; },
};
