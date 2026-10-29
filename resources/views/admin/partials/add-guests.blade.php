<section class="card" aria-labelledby="addTitle">
  <p class="eyebrow" id="addTitle">Add guests</p>
  <form id="addForm" class="form" autocomplete="off">
    <label class="wide">Name<input name="name" required maxlength="120" placeholder="e.g. Ahmed Ali &amp; family"></label>
    <label>Seats<input name="seats" type="number" min="1" max="{{ config('wedding.max_seats') }}" value="1" inputmode="numeric"></label>
    <label>WhatsApp number (optional)<input name="phone" type="tel" inputmode="tel" placeholder="97333334444" maxlength="24"></label>
    <button class="primary wide" type="submit">Add guest</button>
  </form>
  <p class="note" id="addNote">Each guest gets a personal link. Only people with a link can open the invitation.</p>
</section>

<details class="card bulk" id="bulk">
  <summary>Add many at once</summary>
  <p class="note">One guest per line: <b>name, seats, WhatsApp number</b>. Seats and number are optional; a 1–2 digit value is the seats, a longer one is the number.</p>
  <textarea id="bulkText" rows="6" placeholder="Ahmed Ali &amp; family, 3, 97333334444&#10;Fatima Rashid, 2&#10;Rahul Nair"></textarea>
  <div class="bulk-actions">
    <button class="primary" type="button" id="bulkAdd">Add all</button>
    <label class="secondary file-btn">Upload CSV<input type="file" id="bulkFile" accept=".csv,text/csv" hidden></label>
  </div>
  <div class="report" id="bulkReport" hidden></div>
</details>
