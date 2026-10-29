<section class="sec reveal olive-sec" id="rsvpSec">
  <h2 class="title">Confirm your attendance</h2>
  @include('invite.partials.ornament')
  <p class="lead">To help us prepare for this celebration, kindly confirm your attendance.</p>
  @if ($d['rsvp_by'])
  <p class="reply-by">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>
    <span>Please reply before <b>{{ $d['rsvp_by'] }}</b></span>
  </p>
  @endif
  <button class="mini-env" id="openRsvp" type="button" aria-label="Open the RSVP card">
    <span class="grain"></span>
    <svg class="lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0 0 L50 52 L100 0" vector-effect="non-scaling-stroke"/>
      <path d="M0 100 L50 52 L100 100" vector-effect="non-scaling-stroke"/>
    </svg>
    <span class="ms" id="rsvpSeal"></span>
  </button>
  <p class="open-hint"><svg viewBox="0 0 16 10" aria-hidden="true"><path d="M1 9 L8 2 L15 9" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>Tap to open</p>
</section>
