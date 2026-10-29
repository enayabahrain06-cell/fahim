<dialog id="rsvp" aria-labelledby="rsvpTitle">
  <button class="x" id="closeRsvp" type="button" aria-label="Close">×</button>
  <h3 id="rsvpTitle">Confirm your attendance</h3>
  @if ($d['rsvp_by'])
  <p class="sub">Kindly reply before <b class="sub-date">{{ $d['rsvp_by'] }}</b></p>
  @else
  <p class="sub">We would love to know if you can join us</p>
  @endif

  <form id="rsvpForm" novalidate>
    <fieldset class="radios">
      <legend class="fl">Will you be attending?</legend>
      <label class="radio"><input type="radio" name="att" value="yes"> Accepts with pleasure</label>
      <label class="radio"><input type="radio" name="att" value="no"> Declines with regret</label>
    </fieldset>

    <div id="attendBlock" hidden>
      <label class="fl" for="yourName">Your name</label>
      <input class="in" id="yourName" type="text" maxlength="80" autocomplete="name" required>
      <div class="counter" id="counter">
        <span class="fl">Number of guests</span>
        <div class="stepper">
          <button type="button" id="minus" aria-label="Fewer guests">−</button>
          <output id="count" aria-live="polite">1</output>
          <button type="button" id="plus" aria-label="More guests">+</button>
        </div>
      </div>
    </div>

    <button class="btn solid" id="send" type="submit">Send RSVP</button>
    <p class="err" id="err" role="alert"></p>
  </form>
  <p class="done" id="done" hidden></p>
</dialog>
