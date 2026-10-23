<!doctype html>
<html lang="de">
<body style="margin:0;background:#05070c;font-family:Arial,Helvetica,sans-serif;color:#e6edf7">
<div style="max-width:560px;margin:0 auto;padding:28px">
  <h1 style="font-size:22px;margin:0 0 6px">Hallo {{ $user->name }}</h1>
  <p style="color:#93a1b8;margin:0 0 22px">Das war die letzte Woche auf deinen Linknest-Seiten.</p>
  @foreach ($rows as $row)
    <div style="background:#0d1320;border:1px solid #1b2538;border-radius:14px;padding:18px;margin-bottom:14px">
      <div style="font-weight:700;font-size:16px">{{ $row['page']->title }}</div>
      <div style="color:#93a1b8;font-size:13px;margin-bottom:10px">{{ route('profile.show', $row['page']->slug) }}</div>
      <table role="presentation" width="100%"><tr>
        <td><div style="font-size:26px;font-weight:700">{{ $row['stats']['views'] }}</div><div style="color:#93a1b8;font-size:12px">Aufrufe</div></td>
        <td><div style="font-size:26px;font-weight:700">{{ $row['stats']['clicks'] }}</div><div style="color:#93a1b8;font-size:12px">Klicks</div></td>
      </tr></table>
      @if ($row['best'])
        <div style="margin-top:10px;font-size:13px;color:#93a1b8">Bester Link: <strong style="color:#e6edf7">{{ $row['best']->title ?: $row['best']->url }}</strong></div>
      @endif
    </div>
  @endforeach
  <p style="color:#5f6d85;font-size:12px">Du kannst den Wochenbericht jederzeit in deinen Konto-Einstellungen ausschalten: {{ route('account') }}</p>
</div>
</body>
</html>
