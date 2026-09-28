@php
    /**
     * Shared print stylesheet for the A4 sale memo.
     *
     * DomPDF ignores CSS grid, flexbox, gradients and box shadows, so the cash
     * memo design is rebuilt from nested tables, solid fills and borders.
     */
    $ink = '#2a1608';
    $maroon = '#7a1030';
    $red = '#c62828';
    $gold = '#b8860b';
    $goldLight = '#fdf1c4';
    $cream = '#fbf3df';
    $line = '#d9c68a';
@endphp
@if (request('format') === 'html' || request('view') === 'html')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Da+2:wght@500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <div class="no-print print-toolbar">
        <div class="print-toolbar-inner">
            <button onclick="window.print()" class="btn-print">&#128438; {{ $labels['printA4']['bn'] ?? 'প্রিন্ট করুন (Print A4)' }}</button>
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
    @page { size: A4 portrait; margin: 8mm 6mm; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Hind Siliguri', 'solaiman-lipi', 'noto-sans-bengali', sans-serif;
        font-size: 10.5px;
        color: {{ $ink }};
    }

    @media screen {
        body { background-color: #f1f5f9; padding: 20px 0; }
        .frame { max-width: 210mm; margin: 0 auto; background: #ffffff; box-shadow: 0 4px 18px rgba(0,0,0,0.12); }
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
        .frame { box-shadow: none !important; border: 2px solid {{ $gold }} !important; }
    }

    table { border-collapse: collapse; width: 100%; }

    .frame { border: 2px solid {{ $gold }}; padding: 0; }

    .frame-inner { border: 1px solid {{ $goldLight }}; padding: 8px 10px; }

    .head { text-align: center; }

    .ribbon {
        display: inline-block;
        background-color: {{ $red }};
        color: #ffffff;
        font-size: 12px;
        font-weight: bold;
        padding: 4px 26px;
        border: 1px solid {{ $maroon }};
    }

    .shop-name {
        font-family: 'solaiman-lipi', 'baloo-da-2', 'hind-siliguri', sans-serif;
        font-size: 30px;
        font-weight: bold;
        color: {{ $gold }};
        line-height: 1.3;
        margin-top: 6px;
    }

    .shop-tagline { font-size: 10px; color: #4a3216; line-height: 1.6; margin-top: 4px; }

    .shop-contact { font-size: 10px; color: {{ $maroon }}; margin-top: 3px; font-weight: bold; }

    .shop-logo { height: 46px; }

    .meta { margin-top: 8px; }

    .meta table { border: 1px solid {{ $line }}; }

    .meta td {
        border: 1px solid {{ $line }};
        padding: 5px 7px;
        font-size: 10.5px;
        vertical-align: top;
        width: 25%;
    }

    .meta .label { color: {{ $maroon }}; font-weight: bold; white-space: nowrap; width: 1%; }

    .meta .value { background-color: #fffdf6; }

    .en-gloss { font-size: 8px; color: #7a6a48; }

    .void-stamp {
        margin-top: 6px;
        text-align: center;
        border: 2px solid {{ $red }};
        color: {{ $red }};
        font-size: 13px;
        font-weight: bold;
        padding: 3px 0;
        letter-spacing: 1px;
    }

    .items { margin-top: 8px; }

    .items th {
        background-color: {{ $red }};
        color: #ffffff;
        border: 1px solid #9d1f1f;
        padding: 5px 4px;
        font-size: 10px;
        line-height: 1.3;
    }

    .items td {
        border: 1px solid {{ $line }};
        padding: 4px 4px;
        font-size: 10px;
        vertical-align: top;
    }

    .items .num { text-align: right; }
    .items .ctr { text-align: center; }
    .items .tag { font-size: 8.5px; color: #7a6a48; }

    .section-title {
        margin-top: 8px;
        background-color: {{ $goldLight }};
        border: 1px solid {{ $line }};
        border-bottom: none;
        padding: 3px 6px;
        font-weight: bold;
        color: {{ $maroon }};
        font-size: 10.5px;
    }

    .totals { margin-top: 8px; }

    .totals td {
        border: 1px solid {{ $line }};
        padding: 4px 6px;
        font-size: 10.5px;
    }

    .totals .label { width: 34%; font-weight: bold; color: {{ $maroon }}; }
    .totals .num { text-align: right; width: 16%; }
    .totals .grand { font-size: 12px; font-weight: bold; color: {{ $red }}; }
    .totals .due { font-size: 12px; font-weight: bold; color: {{ $ink }}; }
    .totals .words { font-size: 9.5px; color: #4a3d28; padding: 3px 6px; }

    .payments th, .exchanges th, .payments td, .exchanges td {
        border: 1px solid {{ $line }};
        padding: 4px 5px;
        font-size: 10px;
    }

    .payments th, .exchanges th { background-color: {{ $cream }}; color: {{ $maroon }}; }

    .sign-row { margin-top: 14px; }

    .sign-row td { width: 33.33%; text-align: center; vertical-align: bottom; }

    .sign-line { border-top: 1px solid {{ $gold }}; padding-top: 3px; font-size: 9.5px; color: #4a3216; }

    .thanks {
        font-family: 'solaiman-lipi', 'baloo-da-2', 'hind-siliguri', sans-serif;
        font-size: 13px;
        color: {{ $maroon }};
        font-weight: bold;
    }

    .footer-note {
        margin-top: 8px;
        border-top: 1px dashed {{ $line }};
        padding-top: 5px;
        font-size: 8.5px;
        line-height: 1.7;
        color: #4a3d28;
        text-align: justify;
    }

    .banner {
        margin-top: 6px;
        background-color: {{ $maroon }};
        color: {{ $goldLight }};
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        padding: 5px;
    }

    .printed { margin-top: 4px; font-size: 8px; color: #8a7a58; text-align: center; }
</style>
