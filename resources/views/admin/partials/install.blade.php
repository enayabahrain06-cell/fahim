{{-- Install the dashboard as a phone app. resources/js/install.js shows the right version for the device and hides it once installed. --}}
<section class="install" id="install" hidden aria-labelledby="installTitle">
  <img src="/icons/icon-192.png" alt="" width="44" height="44">
  <div class="install-text">
    <p class="install-title" id="installTitle">Get the app</p>
    <p class="install-sub" id="installSub">Add the dashboard to your home screen and open it like an app.</p>
  </div>
  <button class="primary install-btn" id="installBtn" type="button" hidden>Install</button>
  <ol class="install-ios" id="installIos" hidden>
    <li>Tap <b>Share</b>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M6 11H5v10h14V11h-1"/></svg>
      in Safari's toolbar</li>
    <li>Choose <b>Add to Home Screen</b></li>
  </ol>
</section>
