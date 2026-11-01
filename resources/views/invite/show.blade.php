@extends('layouts.guest')

@section('title', $d['share_title_text'])

@section('meta')
@php
  $ogTitle = $d['share_title_text'];
  $ogDesc = $d['share_description_text'];
  $ogImage = url($photos['couple'] ?: Vite::asset('resources/images/couple-default.jpg'));
@endphp
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $d['groom'] }} &amp; {{ $d['bride'] }}">
<meta property="og:title" content="{{ $ogTitle }}">
@if ($ogDesc !== '')<meta property="og:description" content="{{ $ogDesc }}">@endif
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:alt" content="The bride and groom">
<meta name="twitter:card" content="summary_large_image">
@if ($ogDesc !== '')<meta name="description" content="{{ $ogDesc }}">@endif
@endsection

@section('head')
@vite(['resources/css/invite.css', 'resources/js/invite.js'])
@endsection

@section('body-class', 'sealed')

@section('content')
@if ($config['preview'])
{{-- Admin preview only (the route needs sign-in): a way back, since the installed app has no browser bar. --}}
<a href="{{ route('admin.dashboard') }}" style="position:fixed;z-index:9999;top:max(12px,env(safe-area-inset-top));left:12px;display:inline-flex;align-items:center;gap:6px;min-height:40px;padding:0 16px;border-radius:999px;background:#7b0f17;color:#fff;font:600 14px/1 system-ui,sans-serif;text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.25)">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
  Back to dashboard
</a>
@endif
@include('invite.partials.envelope')

<main>
  @include('invite.partials.hero')
  @include('invite.partials.countdown')
  @include('invite.partials.schedule')
  @include('invite.partials.location')
  @include('invite.partials.rsvp')
  @include('invite.partials.farewell')
</main>

@include('invite.partials.dialog')

@if ($config['music'])
<div class="music-wrap">
  <button class="music" id="music" type="button" aria-pressed="false" aria-label="Play or pause music"></button>
</div>
@endif

<script type="application/json" id="config">@json($config)</script>
@endsection
