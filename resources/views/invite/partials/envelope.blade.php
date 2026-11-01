<div class="intro" id="intro">
  <img class="env-art" src="{{ Vite::asset('resources/images/envelope.jpg') }}" alt="" width="940" height="1672" fetchpriority="high" decoding="async">
  <div class="glow"></div>
  @if (trim($d['cover'] ?? '') !== '')<p class="env-ar" lang="ar" dir="rtl">{{ $d['cover'] }}</p>@endif
  @if (trim($d['cover_tap'] ?? '') !== '')<p class="env-ar tap" lang="ar" dir="rtl">{{ $d['cover_tap'] }}</p>@endif
  <p class="sr-only">{{ $d['groom'] }} &amp; {{ $d['bride'] }} · Wedding invitation</p>
  <button class="seal-btn" id="seal" type="button" aria-label="Open the invitation"><img src="{{ Vite::asset('resources/images/seal.png') }}" alt="" width="272" height="272"></button>
</div>
