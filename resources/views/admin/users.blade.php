@extends('layouts.admin')

@section('title', 'Users · Wedding dashboard')
@section('head')
@vite(['resources/css/admin.css'])
<style>
.users-page .screens { padding-bottom: calc(32px + var(--bottom)); }
.users-page .appbar h1 { font-size: 1.6rem; }
.users-page .icon-btn span { display: inline; }
.users-page .msg { margin: 0 0 12px; padding: 10px 14px; border-radius: 14px; font-size: .9rem; background: var(--sage-soft); }
.users-page .msg.err { background: var(--rose-soft); color: var(--rose); }
.users-page .list { margin-bottom: 20px; }
.users-page .row .nm { font-size: 1.02rem; }
.users-page .row .meta { min-width: 0; }
.users-page .row details { padding: 0; }
.users-page .row summary { display: inline-flex; align-items: center; min-height: 44px; padding: 0 14px; border: 1px solid var(--line); border-radius: 12px; background: var(--bg); cursor: pointer; list-style: none; font-size: .88rem; color: var(--ink); }
.users-page .row summary::-webkit-details-marker { display: none; }
.users-page .row details[open] summary { margin-bottom: 12px; }
.users-page .form { grid-template-columns: 1fr; }
.users-page .form .primary, .users-page .row details .linkbtn { width: 100%; }
.users-page .row details > form + form { margin-top: 10px; text-align: center; }
.users-page .row details .linkbtn { min-height: 44px; color: var(--rose); }
.users-page .card.form { padding: 18px 16px; }
@media (min-width: 640px) { .users-page .form .primary { width: auto; justify-self: start; } }
</style>
@endsection
@section('body-class', 'app users-page')

@section('content')
<header class="appbar">
  <div class="appbar-in">
    <div class="appbar-title">
      <p class="eyebrow">Wedding RSVPs</p>
      <h1>Users</h1>
    </div>
    <div class="appbar-actions">
      <a class="icon-btn" href="{{ route('admin.dashboard') }}"><span>Back</span></a>
    </div>
  </div>
</header>

<main class="screens">
  <section class="screen">
    @if (session('status'))
      <p class="msg" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
      <p class="msg err" role="alert">{{ $errors->first() }}</p>
    @endif

    <ul class="list">
      @foreach ($users as $u)
        <li class="row">
          <span class="nm">{{ $u->name }}@if ($u->is(auth()->user())) (you)@endif</span>
          <span class="meta">{{ $u->username ? $u->username.' · ' : '' }}{{ $u->email }}</span>
          <details class="meta">
            <summary>Edit</summary>
            <form class="form" method="POST" action="{{ route('admin.users.update', $u) }}">
              @csrf @method('PATCH')
              <label class="field">Name <input name="name" value="{{ $u->name }}" required maxlength="120"></label>
              <label class="field">Username <small>letters, numbers . _ -</small> <input name="username" value="{{ $u->username }}" maxlength="60" autocapitalize="none"></label>
              <label class="field">Email <input name="email" type="email" value="{{ $u->email }}" required maxlength="190"></label>
              <label class="field">New password <small>leave blank to keep, at least 10 characters</small>
                <input name="password" type="password" autocomplete="new-password" minlength="10"></label>
              <label class="field">Repeat new password
                <input name="password_confirmation" type="password" autocomplete="new-password" minlength="10"></label>
              <button class="primary" type="submit">Save</button>
            </form>
            @unless ($u->is(auth()->user()))
              <form method="POST" action="{{ route('admin.users.destroy', $u) }}">
                @csrf @method('DELETE')
                <button class="linkbtn" type="submit">Delete user</button>
              </form>
            @endunless
          </details>
        </li>
      @endforeach
    </ul>

    <form class="card form" method="POST" action="{{ route('admin.users.store') }}">
      @csrf
      <p class="eyebrow">Add a user</p>
      <label class="field">Name <input name="name" required maxlength="120" autocomplete="off"></label>
      <label class="field">Username <small>letters, numbers . _ -</small> <input name="username" maxlength="60" autocomplete="off" autocapitalize="none"></label>
      <label class="field">Email <input name="email" type="email" required maxlength="190" autocomplete="off"></label>
      <label class="field">Password <small>at least 10 characters</small>
        <input name="password" type="password" autocomplete="new-password" required minlength="10"></label>
      <label class="field">Repeat password
        <input name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></label>
      <button class="primary" type="submit">Add user</button>
    </form>
  </section>
</main>
@endsection
