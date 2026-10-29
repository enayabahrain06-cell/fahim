<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $couple }} · Guest list</title>
<style>
  @font-face { font-family: "Rubik"; font-weight: normal; src: url("{{ resource_path('fonts/Rubik-Regular.ttf') }}") format("truetype"); }
  @font-face { font-family: "Rubik"; font-weight: bold; src: url("{{ resource_path('fonts/Rubik-Medium.ttf') }}") format("truetype"); }
  @font-face { font-family: "Cormorant"; font-weight: normal; src: url("{{ resource_path('fonts/CormorantGaramond-Medium.ttf') }}") format("truetype"); }
  @font-face { font-family: "Cormorant"; font-weight: bold; src: url("{{ resource_path('fonts/CormorantGaramond-SemiBold.ttf') }}") format("truetype"); }
  @font-face { font-family: "Amiri"; font-weight: normal; src: url("{{ resource_path('fonts/Amiri-Regular.ttf') }}") format("truetype"); }
  @font-face { font-family: "Amiri"; font-weight: bold; src: url("{{ resource_path('fonts/Amiri-Regular.ttf') }}") format("truetype"); }
  @page { size: A4 portrait; margin: 16mm 14mm; }
  body { font-family: "Rubik", sans-serif; font-size: 9.5pt; color: #222; margin: 0; }
  .ar { font-family: "Amiri", serif; font-size: 1.1em; }
  h1 { font-family: "Cormorant", serif; font-weight: bold; font-size: 24pt; color: #343C31; margin: 0; line-height: 1.1; }
  .arnames { font-family: "Amiri", serif; font-size: 13pt; color: #56613F; margin: 2px 0 0; }
  .sub { color: #666; margin: 4px 0 14px; font-size: 9pt; }
  table.sum { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 16px; }
  table.sum td { border: 1px solid #d8d2c4; border-radius: 6px; padding: 8px 10px; width: 20%; vertical-align: top; }
  table.sum b { display: block; font-family: "Cormorant", serif; font-weight: bold; font-size: 20pt; color: #56613F; line-height: 1; }
  table.sum span { font-size: 8pt; color: #666; }
  h2 { font-size: 12pt; font-weight: bold; margin: 18px 0 6px; color: #56613F; }
  h2 small { font-weight: normal; color: #888; font-size: 9pt; }
  table.list { width: 100%; border-collapse: collapse; }
  table.list th { text-align: left; border-bottom: 1.5px solid #56613F; padding: 5px 6px; font-weight: bold; font-size: 8.5pt; }
  table.list td { border-bottom: 1px solid #e4dfd3; padding: 5px 6px; vertical-align: top; }
  table.list tr { page-break-inside: avoid; }
  .num { width: 22px; color: #888; }
  .c { text-align: center; white-space: nowrap; }
  .none { color: #888; font-style: italic; padding: 6px 0; }
</style>
</head>
<body>
  <h1>{{ $couple }} · Guest list</h1>
  @if ($d['groom_arabic'] || $d['bride_arabic'])
    <p class="arnames">{!! $t->html(trim($d['groom_arabic'].' & '.$d['bride_arabic'], ' &')) !!}</p>
  @endif
  <p class="sub">Generated {{ $generated->format('j F Y, g:i A') }} ({{ $tz }}) · {{ $d['day_name'] }}, {{ $d['date_long'] }} · {{ $d['venue'] }}</p>

  <table class="sum">
    <tr>
      <td><b>{{ $stats['guests_coming'] }}</b><span>Guests coming</span></td>
      <td><b>{{ $stats['accepted'] }}</b><span>Accepted</span></td>
      <td><b>{{ $stats['declined'] }}</b><span>Declined</span></td>
      <td><b>{{ $stats['waiting'] }}</b><span>No reply yet</span></td>
      <td><b>{{ $stats['seats_pct'] }}%</b><span>of {{ $stats['total_seats'] }} seats</span></td>
    </tr>
  </table>

  <h2>Coming <small>{{ $coming->count() }} invitations · {{ $stats['guests_coming'] }} guests</small></h2>
  @if ($coming->isEmpty())
    <p class="none">No one yet.</p>
  @else
  <table class="list">
    <thead><tr><th class="num">#</th><th>Invitation</th><th class="c">Guests / seats</th><th>Guest names</th><th>Replied</th></tr></thead>
    <tbody>
    @foreach ($coming as $i => $r)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td>{{ $t->html($r->name) }}</td>
        <td class="c">{{ $r->guests }} / {{ $r->seats }}</td>
        <td>{{ $t->html(implode(', ', $r->guest_names ?? [])) }}</td>
        <td>{{ $r->replied_at?->timezone($tz)->format('j M') }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  @endif

  <h2>Declined <small>{{ $declined->count() }} invitations</small></h2>
  @if ($declined->isEmpty())
    <p class="none">No one.</p>
  @else
  <table class="list">
    <thead><tr><th class="num">#</th><th>Invitation</th><th class="c">Seats</th><th>Phone</th></tr></thead>
    <tbody>
    @foreach ($declined as $i => $r)
      <tr><td class="num">{{ $i + 1 }}</td><td>{{ $t->html($r->name) }}</td><td class="c">{{ $r->seats }}</td><td>{{ $r->phone }}</td></tr>
    @endforeach
    </tbody>
  </table>
  @endif

  <h2>No reply yet <small>{{ $waiting->count() }} invitations</small></h2>
  @if ($waiting->isEmpty())
    <p class="none">Everyone has replied.</p>
  @else
  <table class="list">
    <thead><tr><th class="num">#</th><th>Invitation</th><th class="c">Seats</th><th>Phone</th></tr></thead>
    <tbody>
    @foreach ($waiting as $i => $r)
      <tr><td class="num">{{ $i + 1 }}</td><td>{{ $t->html($r->name) }}</td><td class="c">{{ $r->seats }}</td><td>{{ $r->phone }}</td></tr>
    @endforeach
    </tbody>
  </table>
  @endif
</body>
</html>
