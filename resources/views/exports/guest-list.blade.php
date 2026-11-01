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
  @page { size: A4 portrait; margin: 16mm 15mm 18mm; }
  body { font-family: "Rubik", sans-serif; font-size: 9.5pt; color: #3A2A2A; margin: 0; }
  .ar { font-family: "Amiri", serif; font-size: 1.1em; }
  .head { text-align: center; padding: 6px 0 14px; border-top: 3px solid #781D2B; border-bottom: 1px solid #781D2B; }
  .head .arnames { font-family: "Amiri", serif; font-size: 15pt; color: #781D2B; margin: 12px 0 0; }
  .head h1 { font-family: "Cormorant", serif; font-weight: bold; font-size: 30pt; color: #3A2A2A; margin: 2px 0 0; line-height: 1.1; }
  .head .kind { font-size: 7.5pt; letter-spacing: 3px; text-transform: uppercase; color: #7D6B68; margin: 4px 0 0; }
  .head .where { font-size: 9pt; color: #3A2A2A; margin: 10px 0 0; }
  .stamp { text-align: center; font-size: 7.5pt; color: #7D6B68; margin: 6px 0 16px; }
  /* DOMPDF has no gap or flex: plain table cells that always fit the page. */
  table.sum { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.sum td { text-align: center; padding: 4px 6px 6px; border-left: 1px solid #E6DCCE; }
  table.sum td:first-child { border-left: 0; }
  table.sum b { display: block; font-family: "Cormorant", serif; font-weight: bold; font-size: 24pt; color: #3A2A2A; line-height: 1.1; }
  table.sum td.key b { color: #781D2B; }
  table.sum span { font-size: 7.5pt; letter-spacing: 1px; text-transform: uppercase; color: #7D6B68; }
  table.bar { width: 100%; border-collapse: collapse; margin: 14px 0 0; }
  table.bar td { height: 5px; padding: 0; font-size: 1px; line-height: 1px; }
  .note { text-align: center; color: #7D6B68; font-size: 8.5pt; margin: 6px 0 0; }
  h2 { font-family: "Cormorant", serif; font-size: 17pt; font-weight: bold; margin: 24px 0 6px; color: #781D2B; page-break-after: avoid; line-height: 1.1; }
  h2 small { font-family: "Rubik", sans-serif; font-weight: normal; color: #7D6B68; font-size: 8.5pt; }
  table.list { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.list thead { display: table-header-group; }
  table.list td { word-wrap: break-word; }
  table.list th { text-align: left; border-top: 1px solid #781D2B; border-bottom: 1px solid #781D2B; padding: 5px 6px; font-weight: bold; font-size: 7.5pt; letter-spacing: 1px; text-transform: uppercase; color: #781D2B; }
  table.list td { border-bottom: 1px solid #E6DCCE; padding: 6px; vertical-align: top; }
  table.list tr { page-break-inside: avoid; }
  .num { width: 28px; color: #7D6B68; }
  .c { width: 58px; text-align: center; white-space: nowrap; }
  th.c { text-align: center; }
  .w-date { width: 62px; }
  .w-phone { width: 120px; }
  .w-name { width: 28%; }
  .none { color: #7D6B68; font-style: italic; padding: 6px 0; }
</style>
</head>
<body>
  <script type="text/php">
    if (isset($pdf)) { $f = $fontMetrics->getFont('Helvetica'); $pdf->page_text($pdf->get_width() / 2 - 25, $pdf->get_height() - 28, '{PAGE_NUM} / {PAGE_COUNT}', $f, 8, [0.49, 0.42, 0.41]); }
  </script>

  <div class="head">
    @if ($d['groom_arabic'] || $d['bride_arabic'])
      <p class="arnames">{!! $t->html(trim($d['groom_arabic'].' & '.$d['bride_arabic'], ' &')) !!}</p>
    @endif
    <h1>{{ $couple }}</h1>
    <p class="kind">Guest list</p>
    <p class="where">{{ $d['day_name'] }}, {{ $d['date_long'] }} · {{ $d['start_time'] }} · {{ $d['venue'] }}</p>
  </div>
  <p class="stamp">Generated {{ $generated->format('j F Y, g:i A') }} ({{ $tz }})</p>

  <table class="sum">
    <tr>
      <td class="key"><b>{{ $stats['guests_coming'] }}</b><span>People coming</span></td>
      <td><b>{{ $stats['accepted'] }}</b><span>Yes replies</span></td>
      <td><b>{{ $stats['declined'] }}</b><span>No replies</span></td>
    </tr>
  </table>
  @if ($stats['answered'] > 0)
  @php($pct = fn ($n) => round($n / $stats['answered'] * 100, 2))
  <table class="bar"><tr>
    @if ($stats['accepted'])<td style="width: {{ $pct($stats['accepted']) }}%; background: #781D2B"></td>@endif
    @if ($stats['declined'])<td style="width: {{ $pct($stats['declined']) }}%; background: #D9A79A"></td>@endif
  </tr></table>
  @endif
  <p class="note">{{ $stats['answered'] }} {{ \Illuminate\Support\Str::plural('reply', $stats['answered']) }} · {{ $stats['acceptance_pct'] }}% said yes</p>

  <h2>Coming <small>{{ $coming->count() }} {{ \Illuminate\Support\Str::plural('reply', $coming->count()) }} · {{ $stats['guests_coming'] }} {{ \Illuminate\Support\Str::plural('guest', $stats['guests_coming']) }}</small></h2>
  @if ($coming->isEmpty())
    <p class="none">No one yet.</p>
  @else
  <table class="list">
    <thead><tr><th class="num">#</th><th class="w-name">Name</th><th class="c">Guests</th><th>Guest names</th><th class="w-date">Replied</th></tr></thead>
    <tbody>
    @foreach ($coming as $i => $r)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td>{{ $t->html($r->name) }}</td>
        <td class="c">{{ $r->guests }}</td>
        <td>{{ $t->html(implode(', ', $r->guest_names ?? [])) }}</td>
        <td class="w-date">{{ $r->replied_at?->timezone($tz)->format('j M') }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  @endif

  <h2>Declined <small>{{ $declined->count() }} {{ \Illuminate\Support\Str::plural('reply', $declined->count()) }}</small></h2>
  @if ($declined->isEmpty())
    <p class="none">No one.</p>
  @else
  <table class="list">
    <thead><tr><th class="num">#</th><th>Name</th><th class="w-phone">Phone</th></tr></thead>
    <tbody>
    @foreach ($declined as $i => $r)
      <tr><td class="num">{{ $i + 1 }}</td><td>{{ $t->html($r->name) }}</td><td class="w-phone">{{ $r->phone }}</td></tr>
    @endforeach
    </tbody>
  </table>
  @endif

</body>
</html>
