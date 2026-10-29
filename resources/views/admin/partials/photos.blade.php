<section class="card" aria-labelledby="photosTitle">
  <p class="eyebrow" id="photosTitle">Invitation photos</p>
  <div class="photos">
    @foreach (['couple' => 'Bride & groom', 'venue' => 'Venue'] as $slot => $label)
      <div class="slot" data-slot="{{ $slot }}">
        <div class="thumb">
          @if ($photos[$slot])
            <img src="{{ $photos[$slot] }}" alt="{{ $label }}">
          @else
            <span>Built-in artwork</span>
          @endif
        </div>
        <p class="slot-name">{{ $label }}</p>
        <label class="primary upl">Upload from device<input type="file" accept="image/jpeg,image/png,image/webp" hidden></label>
        <button type="button" class="rm" @if (! $photos[$slot]) hidden @endif>Remove</button>
      </div>
    @endforeach
  </div>
  <p class="note" id="photoNote">Photos appear on the invitation right away. Without a photo, the invitation keeps its built-in artwork.</p>
</section>
