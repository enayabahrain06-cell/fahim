@extends('layouts.guest')

@section('title', $d['groom'].' & '.$d['bride'].' · Wedding Invitation')

@section('head')
@vite(['resources/css/invite.css', 'resources/js/invite.js'])
@endsection

@section('body-class', 'sealed')

@section('content')
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
