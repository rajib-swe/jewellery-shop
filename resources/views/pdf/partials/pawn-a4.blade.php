@php
    /**
     * Print stylesheet for the A4 pawn documents (ticket, payment receipt and
     * redemption receipt). The layout is shared so the three papers a pawn desk
     * prints always look like they came from the same pad.
     */
    $ink = '#2a1608';
    $maroon = '#7a1030';
    $gold = '#b8860b';
    $goldLight = '#fdf1c4';
    $line = '#d9c68a';
@endphp
@include('pdf.partials.fonts')
@if (request('format') === 'html' || request('view') === 'html')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Da+2:wght@500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <div class="no-print print-toolbar">
        <div class="print-toolbar-inner">
            <button onclick="window.print()" class="btn-print">&#128438; {{ $labels['printA4']['bn'] }}</button>
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
        .pawn-container { max-width: 210mm; margin: 0 auto; background: #ffffff; padding: 30px; box-shadow: 0 4px 18px rgba(0,0,0,0.12); }
        .print-toolbar { position: sticky; top: 0; z-index: 9999; background: #0f172a; padding: 12px 16px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .print-toolbar-inner { max-width: 210mm; margin: 0 auto; display: flex; gap: 14px; align-items: center; }
        .btn-print { background: #c62828; color: #ffffff; border: none; padding: 9px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 13px; font-family: inherit; }
        .btn-print:hover { background: #b71c1c; }
        .btn-download { background: #b8860b; color: #ffffff; text-decoration: none; padding: 9px 20px; border-radius: 6px; font-weight: bold; font-size: 13px; font-family: inherit; display: inline-block; }
        .btn-download:hover { background: #996515; }
        .btn-close { background: #334155; color: #ffffff; border: none; padding: 9px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; margin-left: auto; font-family: inherit; }
        .btn-close:hover { background: #475569; }
    }

    @media print {
        .no-print { display: none !important; }
        body { margin: 0 !important; padding: 0 !important; background: transparent !important; }
        .pawn-container { box-shadow: none !important; padding: 0 !important; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; }
    }

    table { border-collapse: collapse; width: 100%; }

    .center { text-align: center; }
    .num { text-align: right; }
    .bold { font-weight: bold; }

    .shop-name {
        font-family: 'solaiman-lipi', 'baloo-da-2', 'hind-siliguri', sans-serif;
        font-size: 24px;
        font-weight: bold;
        color: {{ $gold }};
        line-height: 1.3;
    }

    .shop-line { font-size: 10px; color: #4a3216; line-height: 1.6; }

    .title {
        margin: 10px 0;
        text-align: center;
        border-top: 2px solid {{ $gold }};
        border-bottom: 2px solid {{ $gold }};
        padding: 5px 0;
        font-size: 14px;
        font-weight: bold;
        color: {{ $maroon }};
    }

    .en-gloss { font-size: 8.5px; color: #7a6a48; }

    .section-title {
        margin: 12px 0 5px;
        font-size: 11px;
        font-weight: bold;
        color: {{ $maroon }};
        border-bottom: 1px solid {{ $line }};
        padding-bottom: 2px;
    }

    .panel { border: 1px solid {{ $line }}; }
    .panel td { border: 1px solid {{ $line }}; padding: 5px 7px; vertical-align: top; }
    .panel .label { width: 24%; color: {{ $maroon }}; font-weight: bold; white-space: nowrap; }
    .panel .value { width: 26%; background-color: #fffdf6; }

    .grid { border: 1px solid {{ $line }}; }
    .grid th, .grid td { border: 1px solid {{ $line }}; padding: 4px 6px; }
    .grid th { background-color: #f7ecd0; color: {{ $maroon }}; font-size: 10px; }

    .amount {
        margin: 10px 0;
        background-color: {{ $goldLight }};
        border: 1px solid {{ $gold }};
        padding: 8px 10px;
        font-size: 18px;
        font-weight: bold;
        color: {{ $maroon }};
    }

    .words { font-size: 10px; color: #4a3d28; margin-bottom: 10px; }

    .terms { font-size: 9px; line-height: 1.7; color: #4a3d28; text-align: justify; }

    .sign td { width: 33.33%; text-align: center; vertical-align: bottom; padding-top: 26px; }
    .sign .line { border-top: 1px solid {{ $gold }}; padding-top: 3px; font-size: 10px; color: #4a3216; }

    .footer-note { margin-top: 14px; border-top: 1px dashed {{ $line }}; padding-top: 5px; font-size: 9px; line-height: 1.7; color: #4a3d28; text-align: justify; }

    .printed { margin-top: 6px; font-size: 8.5px; color: #8a7a58; text-align: center; }

    .void-stamp {
        display: inline-block;
        border: 2px solid #b3261e;
        color: #b3261e;
        font-weight: bold;
        padding: 2px 10px;
        transform: rotate(-6deg);
    }

    .item-photo { height: 34px; }
</style>
