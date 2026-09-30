@props(['title'])
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 90px 36px 60px 36px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #3D3934; }

        header { position: fixed; top: -70px; left: 0; right: 0; height: 70px; border-bottom: 2px solid #F04D1B; padding-bottom: 8px; }
        header .logo { width: 40px; height: 40px; float: left; margin-right: 12px; }
        header .logo-fallback { width: 40px; height: 40px; float: left; margin-right: 12px; background: #F04D1B; color: #fff; text-align: center; line-height: 40px; font-weight: bold; font-size: 13px; border-radius: 4px; }
        header .company { float: left; }
        header .company .name { font-size: 13px; font-weight: bold; color: #211F1C; }
        header .company .subtitle { font-size: 10px; color: #7A7267; }
        header .report-title { float: right; text-align: right; padding-top: 4px; }
        header .report-title .title { font-size: 14px; font-weight: bold; color: #A3300E; }
        header .report-title .meta { font-size: 9px; color: #999087; }

        footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 40px; border-top: 1px solid #EBE9E5; padding-top: 6px; font-size: 9px; color: #999087; }
        footer .page-number:before { content: "Page " counter(page) " of " counter(pages); }

        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { padding: 5px 6px; text-align: left; border-bottom: 1px solid #EBE9E5; }
        thead th { background: #FFF4F0; color: #CC3D12; text-transform: uppercase; font-size: 9px; letter-spacing: .03em; border-bottom: 2px solid #F04D1B; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #7A7267; margin: 18px 0 6px; }
        .muted { color: #999087; }
    </style>
</head>
<body>
    <header>
        {{-- dompdf needs the GD or Imagick extension to decode a raster image; fall back to a plain text mark so the report still renders where neither is installed. --}}
        @if (extension_loaded('gd') || extension_loaded('imagick'))
            <img class="logo" src="{{ public_path('images/logo.png') }}">
        @else
            <div class="logo-fallback">PTBI</div>
        @endif
        <div class="company">
            <div class="name">PT Bekaert Indonesia</div>
            <div class="subtitle">Maintenance Department &middot; People Development System</div>
        </div>
        <div class="report-title">
            <div class="title">{{ $title }}</div>
            <div class="meta">Generated {{ now()->format('d M Y H:i') }} by {{ auth()->user()->name }}</div>
        </div>
    </header>

    <footer>
        <span class="page-number"></span> &middot; PTBI Maintenance Academy
    </footer>

    {{ $slot }}
</body>
</html>
