<section class="kpis" aria-label="Guest totals">
  <div class="kpi yes"><b id="kComing">0</b><span>People coming</span></div>
  <div class="kpi no"><b id="kDeclined">0</b><span>Declined</span></div>
</section>
<div class="tools">
  <div class="tabs" role="group" aria-label="Filter">
    <button type="button" data-f="all" aria-pressed="true">All</button>
    <button type="button" data-f="yes" aria-pressed="false">Respondents</button>
    <button type="button" data-f="no" aria-pressed="false">Declined</button>
  </div>
  <input class="search" id="search" type="search" placeholder="Search names or numbers" aria-label="Search names or numbers" autocomplete="off">
  <button type="button" class="secondary select-toggle" id="selectMode" aria-pressed="false" @unless (auth()->user()->canEdit()) hidden @endunless>Select</button>
</div>
<ul class="list" id="list"></ul>
<div class="selbar" id="selbar" hidden role="region" aria-label="Selected guests">
  <b id="selCount" aria-live="polite">0 selected</b>
  <button type="button" class="secondary" id="selAll">Select all</button>
  <button type="button" class="secondary danger" id="selDelete" disabled>Delete</button>
</div>
