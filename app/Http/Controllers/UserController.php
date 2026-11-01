<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', [
            'users' => User::orderBy('name')->get(),
            'logs' => ActivityLog::latest('id')->limit(300)->get(),
            'errors_log' => $this->errorEntries(),
            'tab' => request('tab') === 'logs' ? 'logs' : 'users',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'viewer'])],
            'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed'],
        ]);

        User::create($data);
        $request->attributes->set('log_detail', $data['email'].' ('.$data['role'].')');

        return redirect()->route('admin.users')->with('status', 'User added.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'viewer'])],
            'password' => ['nullable', 'string', 'min:10', 'max:200', 'confirmed'],
        ]);

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return redirect()->route('admin.users')->withErrors(['users' => 'You can’t make your own account view-only.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);
        $request->attributes->set('log_detail', $user->email.' ('.$user->role.')');

        return redirect()->route('admin.users')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->route('admin.users')->withErrors(['users' => 'You can’t delete your own account.']);
        }

        $request->attributes->set('log_detail', $user->email);
        $user->delete();

        return redirect()->route('admin.users')->with('status', 'User deleted.');
    }

    public function clearActivity(): RedirectResponse
    {
        ActivityLog::query()->delete();

        return redirect()->route('admin.users', ['tab' => 'logs'])->with('status', 'Activity log cleared.');
    }

    public function clearErrors(): RedirectResponse
    {
        $path = config('wedding.error_log_path');
        if (is_file($path) && is_writable($path)) {
            file_put_contents($path, '', LOCK_EX);
        }

        return redirect()->route('admin.users', ['tab' => 'logs'])->with('status', 'Error log cleared.');
    }

    /** @return array{entries: list<array{time: string, level: string, message: string}>, total: int} the newest error-log entries, newest first (only the end of the file is read) */
    private function errorEntries(int $show = 100): array
    {
        $path = config('wedding.error_log_path');
        if (! is_file($path) || ! is_readable($path)) {
            return ['entries' => [], 'total' => 0];
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - 600_000));
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.*)$/m', $text, $m, PREG_SET_ORDER);

        $entries = collect($m)->reverse()->take($show)->map(fn (array $e) => [
            'time' => CarbonImmutable::parse($e[1], config('app.timezone'))->timezone(config('wedding.timezone'))->format('j M Y, g:i A'),
            'level' => Str::lower($e[2]),
            'message' => Str::limit(trim(Str::before($e[3], ' {"')), 300),
        ])->values()->all();

        return ['entries' => $entries, 'total' => count($m)];
    }
}
