<section class="card" aria-labelledby="musicTitle">
  <p class="eyebrow" id="musicTitle">Invitation music</p>
  <p class="note" style="margin:0 0 10px">Plays quietly when a guest opens the envelope, and loops. Choose your own song (MP3, M4A, WAV or OGG).</p>
  <p class="slot-name" id="musicName" style="margin:0 0 8px">{{ $music ? $music['name'] : 'No music yet' }}</p>
  <audio id="musicPreview" controls preload="none" style="width:100%;margin-bottom:10px" @if ($music) src="{{ $music['url'] }}" @else hidden @endif></audio>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px">
    <label class="primary upl" id="musicUpl" style="flex:1;font-size:.95rem;min-height:46px;cursor:pointer">Choose a song<input type="file" id="musicFile" accept="audio/*,.mp3,.m4a,.wav,.ogg" hidden></label>
    <button type="button" class="secondary" id="musicRm" @if (! ($music['uploaded'] ?? false)) hidden @endif>Remove</button>
  </div>
  <fieldset class="share" id="cropBox" style="border-top:1px solid var(--line);padding-top:14px;margin-top:14px" @if (! $music) hidden @endif>
    <legend>Play only a part of the song <small>(optional)</small></legend>
    <p class="note" style="margin:0">Play the song, pause where you want it to begin or end, and tap the button to use that moment. The song loops between the two. The file isn’t changed.</p>
    <div class="form">
      <label>Start (m:ss)<input id="cropStart" inputmode="numeric" placeholder="0:00" value="{{ ($music['start'] ?? 0) ? sprintf('%d:%02d', intdiv((int) $music['start'], 60), (int) $music['start'] % 60) : '' }}"></label>
      <label>End (m:ss)<input id="cropEnd" inputmode="numeric" placeholder="to the end" value="{{ ($music['end'] ?? null) ? sprintf('%d:%02d', intdiv((int) $music['end'], 60), (int) $music['end'] % 60) : '' }}"></label>
      <button type="button" class="secondary" id="cropSetStart">Start here</button>
      <button type="button" class="secondary" id="cropSetEnd">End here</button>
    </div>
    <div style="display:flex;gap:8px">
      <button type="button" class="primary" id="cropSave" style="flex:1">Save the part</button>
      <button type="button" class="secondary" id="cropReset" style="flex:1">Whole song</button>
    </div>
  </fieldset>
  <p class="note" id="musicNote" style="margin-top:10px">Turn the music on or off with the "Background music" switch below.</p>
</section>
