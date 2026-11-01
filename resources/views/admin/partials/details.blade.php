<section class="card" aria-labelledby="detailsTitle">
  <p class="eyebrow" id="detailsTitle">Invitation details</p>
  <form id="detailsForm" class="form" autocomplete="off" novalidate>
    <label>Groom name<input name="groom" required maxlength="60" value="{{ $d['groom'] }}"></label>
    <label>Bride name<input name="bride" required maxlength="60" value="{{ $d['bride'] }}"></label>
    <label>Groom name in Arabic<input name="groom_arabic" dir="rtl" lang="ar" maxlength="60" value="{{ $d['groom_arabic'] }}"></label>
    <label>Bride name in Arabic<input name="bride_arabic" dir="rtl" lang="ar" maxlength="60" value="{{ $d['bride_arabic'] }}"></label>
    <label>Groom’s parents<input name="groom_parents" maxlength="120" value="{{ $d['groom_parents'] }}"></label>
    <label>Bride’s parents<input name="bride_parents" maxlength="120" value="{{ $d['bride_parents'] }}"></label>
    <label>Date<input name="date" type="date" required value="{{ $d['date'] }}"></label>
    <label>Time<input name="time" type="time" required value="{{ $d['time'] }}"></label>
    <label class="wide">Venue<input name="venue" required maxlength="120" value="{{ $d['venue'] }}"></label>
    <label class="wide">Hall or address<input name="venue_address" maxlength="160" value="{{ $d['venue_address'] }}"></label>
    <label class="wide">Google Maps link<input name="map_link" type="url" inputmode="url" placeholder="https://maps.app.goo.gl/…" maxlength="600" value="{{ $d['map_link'] }}"></label>
    <label class="wide">Reply by<input name="rsvp_by" placeholder="e.g. 10 November" maxlength="60" value="{{ $d['rsvp_by'] }}"></label>
    <label class="wide">Invitation text<textarea name="intro" rows="3" maxlength="400">{{ $d['intro'] }}</textarea></label>
    <label class="wide">Join line<input name="join_line" maxlength="120" value="{{ $d['join_line'] }}"></label>
    <label class="wide">Note on the invitation (optional)<input name="note" placeholder="e.g. Ladies only" maxlength="120" value="{{ $d['note'] }}"></label>

    <fieldset class="wide share">
      <legend>WhatsApp message <small>(leave empty for the default; you can use {couple}, {date}, {day}, {venue})</small></legend>
      <label class="wide">Card title<input name="share_title" maxlength="80" placeholder="{{ '{couple}' }} · Wedding Invitation" value="{{ $d['share_title'] }}"></label>
      <label class="wide">Card description<textarea name="share_description" rows="3" maxlength="200" placeholder="Optional. Leave empty for no description.">{{ $d['share_description'] }}</textarea></label>
      <label class="wide">Message text above the link<textarea name="share_message" rows="3" maxlength="500" placeholder="With joy, we invite you to the wedding of {{ '{couple}' }}…">{{ $d['share_message'] }}</textarea></label>
    </fieldset>

    <fieldset class="wide schedule">
      <legend>Schedule of events <small>(optional, up to 8)</small></legend>
      <div id="scheduleRows"></div>
      <button type="button" class="secondary" id="addMoment">Add a moment</button>
    </fieldset>

    <label class="wide switch">
      <input type="checkbox" name="music" value="1" @checked($d['music'])>
      <span class="switch-ui" aria-hidden="true"></span>
      Background music
    </label>

    <button class="primary wide" type="submit" id="saveDetails">Save details</button>
  </form>
  <p class="note" id="detailsNote">Changes show on the invitation as soon as you save.</p>
</section>
