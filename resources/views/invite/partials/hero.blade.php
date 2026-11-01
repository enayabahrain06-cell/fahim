<section class="hero" id="top">
  <div class="grain"></div>
  <div class="garland" id="garland"></div>
  <div class="sprig l" id="sprigL"></div>
  <div class="sprig r" id="sprigR"></div>
  <div class="hero-in">
    <h1 class="sr-only">{{ $d['groom'] }} &amp; {{ $d['bride'] }}</h1>
    <p class="basmala" lang="ar" dir="rtl">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</p>
    <p class="wd">Joyfully invite you<br>to their wedding</p>
    @if ($d['intro'])<p class="lead">{{ $d['intro'] }}</p>@endif
    @if ($d['join_line'])<p class="warm">{{ $d['join_line'] }}</p>@endif

    @unless ($hiddenPhotos['couple'])
    <div class="couple-art">
      @if ($photos['couple'])
        <img class="uploaded" src="{{ $photos['couple'] }}" alt="The bride and groom" width="600" height="600">
      @else
        <img src="{{ Vite::asset('resources/images/couple-default.jpg') }}" alt="Illustration of the bride and groom inside a wreath of leaves and white flowers" width="760" height="716">
      @endif
    </div>
    @endunless

    <div class="person">
      <p class="couple">{{ $d['groom'] }}</p>
      @if ($d['groom_arabic'])<p class="arname" lang="ar" dir="rtl">{{ $d['groom_arabic'] }}</p>@endif
      @if ($d['groom_parents'])<p class="parents"><small>S/O</small>{{ $d['groom_parents'] }}</p>@endif
    </div>
    @include('invite.partials.ornament')
    <div class="person">
      <p class="couple">{{ $d['bride'] }}</p>
      @if ($d['bride_arabic'])<p class="arname" lang="ar" dir="rtl">{{ $d['bride_arabic'] }}</p>@endif
      @if ($d['bride_parents'])<p class="parents"><small>D/O</small>{{ $d['bride_parents'] }}</p>@endif
    </div>

    <p class="lead when">{{ $d['day_name'] }}, {{ $d['date_long'] }}, {{ $d['start_time'] }}</p>
    @if (trim($d['verse'] ?? '') !== '')<p class="verse{{ mb_strlen($d['verse']) > 60 ? ' long' : '' }}" lang="ar" dir="rtl">{{ $d['verse'] }}</p>@endif
    @if ($d['note'])<span class="note">{{ $d['note'] }}</span>@endif
  </div>
</section>
