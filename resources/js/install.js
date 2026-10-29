// "Get the app" panel on the sign-in pages: a one-tap Install where the browser offers it (Chrome, Edge,
// Samsung Internet), Share → Add to Home Screen steps on iPhone and iPad, and nothing once installed.

const $ = id => document.getElementById(id);
const panel = $('install');
const installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
// iPadOS reports itself as a Mac, so a touch screen tells them apart.
const ios = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

if (panel && !installed) {
  if (ios) {
    $('installIos').hidden = false;
    panel.hidden = false;
  }

  let prompt = null;
  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault(); // show our own button instead of the browser's mini bar
    prompt = e;
    $('installBtn').hidden = false;
    panel.hidden = false;
  });

  $('installBtn').addEventListener('click', async () => {
    if (!prompt) return;
    prompt.prompt();
    await prompt.userChoice;
    prompt = null;
    $('installBtn').hidden = true;
  });

  window.addEventListener('appinstalled', () => { panel.hidden = true; });
}
