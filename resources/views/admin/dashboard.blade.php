@extends('layouts.admin')

@section('title', $couple.' · Dashboard')
@section('head') @vite(['resources/css/admin.css', 'resources/js/admin.js']) @endsection
@section('body-class', 'app')

@section('content')
<header class="appbar">
  <div class="appbar-in">
    <div class="appbar-title">
      <p class="eyebrow">Wedding RSVPs</p>
      <h1 id="coupleTitle">{{ $couple }}</h1>
    </div>
    <div class="appbar-actions">
      <a class="icon-btn" href="{{ route('admin.export.pdf') }}" aria-label="Download the guest list as PDF">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
        <span>PDF</span>
      </a>
      <button class="icon-btn" id="refresh" type="button" aria-label="Refresh">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/></svg>
        <span>Refresh</span>
      </button>
    </div>
  </div>
</header>

<main class="screens">
  <section class="screen" id="screen-overview" aria-label="Overview">
    <nav class="shortcuts" aria-label="Shortcuts">
      <a class="sc primary" href="{{ route('admin.preview') }}" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
        Preview invitation
      </a>
      <a class="sc" href="#guests">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.8-3.2 3-5 5.5-5s4.7 1.8 5.5 5M16 6.5h5M16 10h5M17.5 13.5H21"/></svg>
        Guest list
      </a>
    </nav>
    @include('admin.partials.summary')
    <footer class="foot">
      <p id="updated">Loading…</p>
      <div class="foot-links">
        <a href="{{ route('admin.export.csv') }}">Export CSV</a>
        <a href="{{ route('admin.users') }}">Users</a>
        <form method="POST" action="{{ route('admin.logout') }}">
          @csrf
          <button type="submit" class="linkbtn">Sign out</button>
        </form>
      </div>
    </footer>
  </section>

  <section class="screen" id="screen-guests" aria-label="Guest list" hidden>
    @include('admin.partials.guest-list')
  </section>

  <section class="screen" id="screen-add" aria-label="Add guests" hidden>
    @include('admin.partials.add-guests')
  </section>

  <section class="screen" id="screen-invitation" aria-label="Invitation" hidden>
    @include('admin.partials.photos')
    @include('admin.partials.details')
  </section>
</main>

<nav class="tabbar" aria-label="Sections">
  <a href="#overview" data-tab="overview">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 11.5 12 5l8 6.5V19a1 1 0 0 1-1 1h-4.5v-5h-5v5H5a1 1 0 0 1-1-1z"/></svg>
    <span>Overview</span>
  </a>
  <a href="#guests" data-tab="guests">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.8-3.2 3-5 5.5-5s4.7 1.8 5.5 5"/><circle cx="17" cy="9" r="2.4"/><path d="M16.5 14c2 .2 3.4 1.6 4 4"/></svg>
    <span>Guests</span><b class="badge" id="badge" hidden></b>
  </a>
  <a href="#add" data-tab="add">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 8v8M8 12h8"/></svg>
    <span>Add</span>
  </a>
  <a href="#invitation" data-tab="invitation">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="3.5" width="16" height="17" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
    <span>Invitation</span>
  </a>
</nav>

<dialog class="sheet" id="sheet" aria-labelledby="sheetTitle">
  <div class="sheet-grip" aria-hidden="true"></div>
  <h2 class="sheet-title" id="sheetTitle"></h2>
  <div class="sheet-body" id="sheetBody"></div>
</dialog>

<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>

<script type="application/json" id="config">@json($config)</script>
@endsection
