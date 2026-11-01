<section class="card" aria-label="One link for everyone">
  <p class="eyebrow">One link for everyone</p>
  <p class="note" style="margin:0 0 10px">Send this same link to as many guests as you like. Each person types their name and replies, and every reply is saved as its own entry.</p>
  <input class="in" id="sharedUrl" readonly value="{{ $sharedUrl }}" aria-label="Shared invitation link" style="width:100%;min-height:46px;border:1px solid var(--line);border-radius:12px;padding:8px 12px;background:var(--bg);font-size:16px;margin-bottom:8px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="primary" type="button" id="sharedWa" style="flex:1">Send on WhatsApp</button>
    <button class="secondary" type="button" id="sharedCopy" style="flex:1">Copy link</button>
  </div>

  @php
    $pvImage = $photos['couple'] ?: Vite::asset('resources/images/couple-default.jpg');
    $pvTitle = $d['share_title_text'];
    $pvDesc = $d['share_description_text'];
  @endphp
  <p class="eyebrow" style="margin:16px 0 8px">How it looks in WhatsApp</p>
  <div class="wa-preview" aria-label="Preview of the WhatsApp message">
    <div class="wa-bubble">
      <div class="wa-card">
        <img src="{{ $pvImage }}" alt="" loading="lazy">
        <div class="wa-card-text">
          <b>{{ $pvTitle }}</b>
          @if ($pvDesc !== '')<span>{{ $pvDesc }}</span>@endif
        </div>
      </div>
      <p id="waMsg">{!! nl2br(e($d['share_message_text'])) !!}<br><u>{{ $sharedUrl }}</u></p>
      <time>3:56 PM ✓✓</time>
    </div>
  </div>
  <button class="secondary" type="button" id="waEdit" style="margin-top:10px;width:100%" @unless (auth()->user()->canEdit()) hidden @endunless>Edit message</button>
  <div id="waEditor" hidden style="margin-top:10px">
    <textarea id="waText" rows="4" maxlength="500" aria-label="WhatsApp message text" style="width:100%;border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:var(--bg);font:inherit;font-size:16px"></textarea>
    <p class="note" style="margin:4px 0 8px">You can use {{ '{couple}, {date}, {day}, {venue}' }}. Leave empty to use the default. The link is added after your text.</p>
    <div style="display:flex;gap:8px">
      <button class="primary" type="button" id="waSave" style="flex:1">Save message</button>
      <button class="secondary" type="button" id="waCancel" style="flex:1">Cancel</button>
    </div>
    <p class="note" id="waNote" role="status" style="margin:6px 0 0"></p>
  </div>
</section>
