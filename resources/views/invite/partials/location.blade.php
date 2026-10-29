@php($at = \Carbon\CarbonImmutable::parse($d['starts_at']))
<section class="sec reveal venue-sec">
  <p class="ar-kicker" lang="ar" dir="rtl">المكان المبارك</p>
  <h2 class="kicker">The celebration venue</h2>

  <div class="venue-card">
    <div class="venue-hero">
      @if ($photos['venue'])
        <img src="{{ $photos['venue'] }}" alt="">
      @else
        <img src="{{ Vite::asset('resources/images/venue-default.jpg') }}" alt="" width="720" height="901">
      @endif
      <div class="venue-cap">
        <p class="vname">{{ $d['venue'] }}</p>
        @if ($d['venue_address'])
        <p class="vaddr">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg>
          {{ $d['venue_address'] }}
        </p>
        @endif
      </div>
    </div>

    <div class="venue-body">
      <div class="venue-when">
        <div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>
          <p>{{ $at->format('D, j M Y') }}</p>
        </div>
        <div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
          <p>{{ $d['start_time'] }}</p>
        </div>
      </div>

      <div class="venue-btns">
        @if ($d['map_link'])
        <a class="btn solid" href="{{ $d['map_link'] }}" target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1.8 22.2 12 12 22.2 1.8 12Z" fill="currentColor"/><path d="M8.8 15.5v-3.2a1 1 0 0 1 1-1h5M12.6 8.8l2.5 2.5-2.5 2.5" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--band)"/></svg>
          Directions
        </a>
        @endif
        <a class="btn" href="{{ $googleCalendarUrl }}" target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4M12 12.5v5M9.5 15h5"/></svg>
          Save date
        </a>
      </div>
      <a class="ics-link" href="{{ $calendarUrl }}" download="wedding.ics">Apple / Outlook Calendar (.ics)</a>
      <p class="venue-note">Calendar reminder begins at {{ $at->format('g:i A') }}. The celebration continues onwards.</p>

      @if ($mapEmbed)
      <div class="venue-map">
        <iframe src="{{ $mapEmbed }}" title="Map showing {{ $d['venue'] }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      </div>
      @endif
    </div>
  </div>
</section>
