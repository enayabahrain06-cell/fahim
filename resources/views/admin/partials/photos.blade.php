<section class="card" aria-labelledby="photosTitle">
  <p class="eyebrow" id="photosTitle">Invitation photos</p>
  <div class="photos">
    @foreach (['couple' => 'Bride & groom', 'venue' => 'Venue'] as $slot => $label)
      @php $builtIn = Vite::asset('resources/images/'.$slot.'-default.jpg'); @endphp
      <div class="slot{{ $hiddenPhotos[$slot] ? ' is-hidden' : '' }}" data-slot="{{ $slot }}" data-default="{{ $builtIn }}">
        <div class="thumb">
          <img src="{{ $photos[$slot] ?: $builtIn }}" alt="{{ $label }}">
        </div>
        <p class="slot-name">{{ $label }}</p>
        <label class="primary upl">Upload from device<input type="file" accept="image/jpeg,image/png,image/webp" hidden></label>
        <a class="dl" href="{{ $photos[$slot] ?: $builtIn }}" download="{{ $slot }}-photo.jpg">Download</a>
        <button type="button" class="hd">{{ $hiddenPhotos[$slot] ? 'Show on invitation' : 'Hide from invitation' }}</button>
        <button type="button" class="rm" @if (! $photos[$slot]) disabled @endif>Remove</button>
      </div>
    @endforeach
  </div>
  <button type="button" class="primary wide" id="photoSave" style="margin-top:14px" disabled>Save photos</button>
  <p class="note" id="photoNote">Choose a photo, then tap Save photos to put it on the invitation. Without a photo, the invitation keeps its built-in artwork.</p>
</section>
