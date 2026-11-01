@extends('layouts.admin')

@section('title', 'Create your account · Wedding dashboard')
@section('head') @vite(['resources/css/admin.css', 'resources/js/install.js']) @endsection
@section('body-class', 'gate-page')

@section('content')
<main class="gate">
  <img class="gate-icon" src="/icons/app-192.png" alt="" width="72" height="72">
  <form class="gate-card" method="POST" action="{{ url('/admin/setup') }}">
    @csrf
    <p class="eyebrow">Wedding dashboard</p>
    <h1>Create your account</h1>
    <p class="gate-text">This is the first time the dashboard is opened. Create the one account that can see your guest list.</p>
    <label class="field">Your name
      <input name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120" autofocus>
    </label>
    <label class="field">Email
      <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required maxlength="190">
    </label>
    <label class="field">Password <small>at least 10 characters</small>
      <input name="password" type="password" autocomplete="new-password" required minlength="10">
    </label>
    <label class="field">Repeat password
      <input name="password_confirmation" type="password" autocomplete="new-password" required minlength="10">
    </label>
    @if ($errors->any())
      <p class="gate-err" role="alert">{{ $errors->first() }}</p>
    @endif
    <button class="primary" type="submit">Create account</button>
  </form>
  @include('admin.partials.install')
</main>
@endsection
