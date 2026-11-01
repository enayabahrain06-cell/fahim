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
.users-page .tabs { display: flex; gap: 8px; margin: 0 0 16px; }
.users-page .tabs a { flex: 1; text-align: center; min-height: 44px; display: grid; place-items: center; border: 1px solid var(--line); border-radius: 12px; background: var(--bg); color: var(--ink); text-decoration: none; font-size: .92rem; }
.users-page .tabs a[aria-current="page"] { background: var(--sage-soft); font-weight: 600; }
.users-page .logs { list-style: none; margin: 0; padding: 0; }
.users-page .logs li { padding: 12px 2px; border-bottom: 1px solid var(--line); display: grid; gap: 2px; }
.users-page .logs .what { font-size: .98rem; }
.users-page .logs .meta { font-size: .82rem; opacity: .75; }
.users-page .clear-logs { margin: 12px 0 0; }
.users-page .clear-logs summary { cursor: pointer; color: var(--rose); font-size: .9rem; padding: 8px 0; }
.users-page .clear-logs .danger { color: var(--rose); border-color: var(--rose); }
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

    <nav class="tabs" aria-label="Users sections">
      <a href="{{ route('admin.users') }}" @if ($tab === 'users') aria-current="page" @endif>Users</a>
      <a href="{{ route('admin.users', ['tab' => 'logs']) }}" @if ($tab === 'logs') aria-current="page" @endif>Logs</a>
    </nav>

    @if ($tab === 'logs')
      <p class="eyebrow" style="margin:6px 0">Activity</p>
      <ul class="logs">
        @forelse ($logs as $log)
          <li>
            <span class="what">{{ $log->action }}@if ($log->detail) · {{ $log->detail }}@endif</span>
            <span class="meta">{{ $log->user_name ?: 'Unknown' }} · {{ $log->created_at->timezone(config('wedding.timezone'))->format('j M Y, g:i A') }} · {{ $log->ip }}</span>
          </li>
        @empty
          <li class="note">No activity yet.</li>
        @endforelse
      </ul>
      @if (auth()->user()->canEdit() && $logs->isNotEmpty())
        <details class="clear-logs">
          <summary>Clear activity log</summary>
          <p class="note">This deletes the activity entries above. A single line recording who cleared it is kept.</p>
          <form method="POST" action="{{ route('admin.logs.activity.clear') }}">@csrf @method('DELETE')<button class="secondary danger" type="submit">Yes, clear the activity log</button></form>
        </details>
      @endif

      <p class="eyebrow" style="margin:26px 0 6px">Errors @if ($errors_log['total'])<small style="font-weight:400">· {{ $errors_log['total'] }} in the file, newest {{ count($errors_log['entries']) }} shown</small>@endif</p>
      <ul class="logs">
        @forelse ($errors_log['entries'] as $e)
          <li>
            <span class="what">{{ $e['message'] }}</span>
            <span class="meta">{{ $e['time'] }} · {{ $e['level'] }}</span>
          </li>
        @empty
          <li class="note">No errors. 🎉</li>
        @endforelse
      </ul>
      @if (auth()->user()->canEdit() && $errors_log['total'])
        <details class="clear-logs">
          <summary>Clear error log</summary>
          <p class="note">This empties the server error log file. It can’t be undone.</p>
          <form method="POST" action="{{ route('admin.logs.errors.clear') }}">@csrf @method('DELETE')<button class="secondary danger" type="submit">Yes, clear the error log</button></form>
        </details>
      @endif
    @else
    <ul class="list">
      @foreach ($users as $u)
        <li class="row">
          <span class="nm">{{ $u->name }}@if ($u->is(auth()->user())) (you)@endif</span>
          <span class="meta">{{ $u->username ? $u->username.' · ' : '' }}{{ $u->email }}{{ $u->role === 'viewer' ? ' · view only' : '' }}</span>
          <details class="meta">
            <summary>Edit</summary>
            <form class="form" method="POST" action="{{ route('admin.users.update', $u) }}">
              @csrf @method('PATCH')
              <label class="field">Name <input name="name" value="{{ $u->name }}" required maxlength="120"></label>
              <label class="field">Username <small>letters, numbers . _ -</small> <input name="username" value="{{ $u->username }}" maxlength="60" autocapitalize="none"></label>
              <label class="field">Email <input name="email" type="email" value="{{ $u->email }}" required maxlength="190"></label>
              <label class="field">Access
                <select name="role"><option value="admin"{{ $u->role === 'admin' ? ' selected' : '' }}>Admin (can edit)</option><option value="viewer"{{ $u->role === 'viewer' ? ' selected' : '' }}>Viewer (view only)</option></select></label>
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
      <label class="field">Access
        <select name="role"><option value="admin">Admin (can edit)</option><option value="viewer">Viewer (view only)</option></select></label>
      <label class="field">Password <small>at least 10 characters</small>
        <input name="password" type="password" autocomplete="new-password" required minlength="10"></label>
      <label class="field">Repeat password
        <input name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></label>
      <button class="primary" type="submit">Add user</button>
    </form>
    @endif
  </section>
</main>
@endsection
