@if (! empty($d['schedule']))
<section class="sec reveal">
  <h2 class="title">Schedule of events</h2>
  @include('invite.partials.ornament')
  <ol class="timeline">
    @foreach ($d['schedule'] as $item)
      <li><span class="tm">{{ $item['time'] }}</span><span class="dot"></span><span class="ev">{{ $item['title'] }}</span></li>
    @endforeach
  </ol>
</section>
@endif
