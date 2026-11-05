@php
    /**
     * A single print layout shared by every report. The report body is a plain
     * table of already-formatted strings, so adding a report to the print stack
     * does not mean writing another stylesheet.
     */
    $ink = '#2a1608';
    $maroon = '#7a1030';
    $line = '#d9c68a';
@endphp
@include('pdf.partials.fonts')
@if (request('format') === 'html' || request('view') === 'html' || request()->has('print'))
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Da+2:wght@500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <div class="no-print print-toolbar">
        <div class="print-toolbar-inner">
            <button onclick="window.print()" class="btn-print">&#128438; প্রিন্ট করুন (Print)</button>
            <a href="{{ request()->fullUrlWithQuery(['download' => 1, 'format' => null, 'print' => null]) }}" class="btn-download">&#128190; PDF ডাউনলোড করুন (Download PDF)</a>
            <button onclick="window.close()" class="btn-close">&#10005; বন্ধ করুন</button>
        </div>
    </div>
    <script>
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.addEventListener('load', function() {
                setTimeout(function() { window.print(); }, 400);
            });
        }
    </script>
@endif
<style>
    @page { margin: 14mm 16mm; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Hind Siliguri', 'solaiman-lipi', 'noto-sans-bengali', sans-serif;
        font-size: 11px;
        color: {{ $ink }};
    }

    @media screen {
        body { background-color: #f1f5f9; padding: 20px 0; }
        .report-container { max-width: 210mm; margin: 0 auto; background: #ffffff; padding: 30px; box-shadow: 0 4px 18px rgba(0,0,0,0.12); }
        .print-toolbar { position: sticky; top: 0; z-index: 9999; background: #0f172a; padding: 12px 16px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .print-toolbar-inner { max-width: 210mm; margin: 0 auto; display: flex; gap: 14px; align-items: center; }
        .btn-print { background: #c62828; color: #ffffff; border: none; padding: 9px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 13px; font-family: inherit; }
        .btn-download { background: #166534; color: #ffffff; text-decoration: none; padding: 9px 20px; border-radius: 6px; font-weight: bold; font-size: 13px; font-family: inherit; }
        .btn-close { background: #475569; color: #ffffff; border: none; padding: 9px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 13px; font-family: inherit; }
    }

    @media print {
        .no-print { display: none !important; }
    }

    .center { text-align: center; }
    .right { text-align: right; }

    .report-header { text-align: center; margin-bottom: 18px; }
    .shop-name { font-size: 18px; font-weight: 800; color: {{ $maroon }}; }
    .shop-line { font-size: 10px; color: #555; }

    .report-title { font-size: 15px; font-weight: 800; color: {{ $maroon }}; margin-top: 10px; }
    .report-period { font-size: 10px; color: #555; margin-top: 3px; }

    table.grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.grid th, table.grid td { border: 1px solid {{ $line }}; padding: 5px 7px; font-size: 10px; }
    table.grid th { background-color: #faf3e0; font-weight: 700; text-align: left; }
    table.grid td.num { text-align: right; }
    table.grid tfoot td { background-color: #faf3e0; font-weight: 700; }

    .summary { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .summary td { padding: 4px 7px; font-size: 10px; border-bottom: 1px solid #eee; }
    .summary td.label { width: 55%; color: #555; }
    .summary td.value { text-align: right; font-weight: 700; }
    .summary tr.total td { border-top: 2px solid {{ $maroon }}; border-bottom: none; font-size: 12px; }

    .empty { text-align: center; padding: 24px; color: #888; font-size: 11px; }

    .printed-at { margin-top: 24px; text-align: center; font-size: 9px; color: #888; }
</style>

<div class="report-container">
    <div class="report-header">
        @if ($shopLogo)
            <img src="{{ $shopLogo }}" alt="" style="height: 40px;">
        @endif
        <div class="shop-name">{{ $shop['shop_name'] }}</div>
        @if ($shop['shop_address'])
            <div class="shop-line">{{ $shop['shop_address'] }}</div>
        @endif
        @if ($shop['shop_phone'])
            <div class="shop-line">&#9742; {{ $shop['shop_phone'] }}</div>
        @endif
        <div class="report-title">{{ $title_bn }} <span style="font-size: 10px; color: #777;">{{ $title_en }}</span></div>
        @if ($period)
            <div class="report-period">{{ $period }}</div>
        @endif
    </div>

    @if (count($summary) > 0)
        <table class="summary">
            @foreach ($summary as $label => $value)
                <tr>
                    <td class="label">{{ $label }}</td>
                    <td class="value">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (count($rows) === 0 && $is_table)
        <div class="empty">{{ $empty_bn }}</div>
    @endif

    @if (count($rows) > 0)
        <table class="grid">
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $index => $cell)
                            <td class="{{ $index > 0 ? 'num' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if (count($totals) > 0)
                <tfoot>
                    <tr>
                        @foreach ($totals as $index => $cell)
                            <td class="{{ $index > 0 ? 'num' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif

    <div class="printed-at">{{ $printed_at }}</div>
</div>
