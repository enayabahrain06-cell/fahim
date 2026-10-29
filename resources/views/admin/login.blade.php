@extends('layouts.admin')

@section('title', 'Sign in · Wedding dashboard')
@section('head') @vite(['resources/css/admin.css', 'resources/js/install.js']) @endsection
@section('body-class', 'gate-page')

@section('content')
<main class="gate">
  <img class="gate-icon" src="/icons/icon-192.png" alt="" width="72" height="72">
  <form class="gate-card" method="POST" action="{{ url('/admin/login') }}">
    @csrf
    <p class="eyebrow">Wedding dashboard</p>
    <h1>Sign in</h1>
    <label class="field">Email
      <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    </label>
    <label class="field">Password
      <input name="password" type="password" autocomplete="current-password" required>
    </label>
    <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
    @if ($errors->any())
      <p class="gate-err" role="alert">{{ $errors->first() }}</p>
    @endif
    <button class="primary" type="submit">Sign in</button>
  </form>
  @include('admin.partials.install')
</main>
@endsection
