@php($d = app(\App\Services\InvitationDetails::class)->all())
@extends('layouts.guest')

@section('title', 'Private invitation')

@section('head')
@vite('resources/css/invite.css')
@endsection

@section('body-class', 'private')

@section('content')
<main class="private-card">
  <p class="eyebrow">Wedding invitation</p>
  <h1 class="names-line">{{ $d['groom'] }} <span class="amp">&amp;</span> {{ $d['bride'] }}</h1>
  <div class="rule"></div>
  <p class="private-text">This invitation is private. Please open the personal link you received from the family.</p>
</main>
@endsection
