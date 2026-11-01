// Background music: a looping audio file (public/audio/music.mp3).
let audio = null;
let playing = false;

function build(url, start = 0, end = null) {
  audio = new Audio(url || '/audio/music.mp3');
  // Only the chosen part plays: from `start` to `end` seconds, then back to `start`.
  audio.loop = !(start > 0 || end);
  if (!audio.loop) {
    audio.addEventListener('loadedmetadata', () => { if (audio.currentTime < start) audio.currentTime = start; });
    audio.addEventListener('timeupdate', () => { if ((end && audio.currentTime >= end) || audio.ended) audio.currentTime = start; });
    audio.addEventListener('ended', () => { audio.currentTime = start; audio.play().catch(() => {}); });
  }
  audio.preload = 'metadata';
  audio.volume = .8;
}

export const Music = {
  start(url, from, to) {
    // Lets the music play on iPhone even when the silent switch is on.
    try { if (navigator.audioSession) navigator.audioSession.type = 'playback'; } catch (e) { /* not supported */ }
    if (!audio) build(url, Number(from) || 0, Number(to) || null);
    if (audio.loop === false && audio.currentTime < (Number(from) || 0)) audio.currentTime = Number(from) || 0;
    playing = true;
    audio.play().catch(() => { playing = false; });
  },
  stop() {
    if (!audio || !playing) return;
    playing = false;
    audio.pause();
  },
  get playing() { return playing; },
};
