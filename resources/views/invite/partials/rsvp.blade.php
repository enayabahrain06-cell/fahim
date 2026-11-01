<section class="sec reveal olive-sec" id="rsvpSec">
  <h2 class="title">Confirm your attendance</h2>
  @include('invite.partials.ornament')
  @if ($d['rsvp_by'])
  <p class="reply-by">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>
    <span>Kindly share your response by <b>{{ $d['rsvp_by'] }}</b></span>
  </p>
  @endif
  <button class="mini-env" id="openRsvp" type="button" aria-label="Open the RSVP card" style="--env-art: url('{{ Vite::asset('resources/images/envelope-mini.jpg') }}')">
    <svg class="env-defs" width="0" height="0" aria-hidden="true" focusable="false">
      <defs>
        <clipPath id="envFlap" clipPathUnits="objectBoundingBox"><path d="M0 0 H1 C.86 .055 .62 .555 .5 1 C.38 .555 .14 .055 0 0 Z"/></clipPath>
        <clipPath id="envPocket" clipPathUnits="objectBoundingBox"><path d="M0 0 C.14 .03 .38 .3 .5 .54 C.62 .3 .86 .03 1 0 V1 H0 Z"/></clipPath>
      </defs>
    </svg>
    <span class="env-inside"></span>
    <span class="env-pocket"><span class="env-tex"></span><svg class="env-seams" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0 100 C20 86 40 66 50 54"/><path d="M100 100 C80 86 60 66 50 54"/></svg></span>
    <span class="env-flap"><span class="env-flap-out"><span class="env-tex"></span></span></span>
    <span class="sheen"></span>
    <span class="ms"><img src="{{ Vite::asset('resources/images/seal-rsvp.png') }}" alt="" width="480" height="480"></span>
  </button>
  <p class="open-hint"><svg viewBox="0 0 16 10" aria-hidden="true"><path d="M1 9 L8 2 L15 9" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>Tap to open</p>
</section>
