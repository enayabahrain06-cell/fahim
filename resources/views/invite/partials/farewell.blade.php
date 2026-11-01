<section class="sec olive-tail">
  <p class="farewell">Hope to see you there!</p>
  @if (trim($d['welcome'] ?? '') !== '')<p class="honor" lang="ar" dir="rtl">{{ $d['welcome'] }}</p>@endif
  <p class="sign">{{ $d['groom'] }} &amp; {{ $d['bride'] }}</p>
</section>
