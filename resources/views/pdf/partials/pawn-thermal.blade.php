@include('pdf.partials.fonts')
@if (request('format') === 'html' || request('view') === 'html')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Da+2:wght@500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <div class="no-print print-toolbar">
        <div class="print-toolbar-inner">
            <button onclick="window.print()" class="btn-print">&#128438; {{ $labels['printThermal']['bn'] }}</button>
            <a href="{{ request()->fullUrlWithQuery(['download' => 1, 'format' => null, 'print' => null]) }}" class="btn-download">&#128190; PDF ডাউনলোড করুন</a>
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
    @page { margin: 2mm; size: 80mm auto; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Hind Siliguri', 'solaiman-lipi', 'noto-sans-bengali', sans-serif;
        font-size: 8.5px;
        color: #000000;
    }

    @media screen {
        body { background-color: #f1f5f9; padding: 20px 0; }
        .pawn-container { max-width: 80mm; margin: 0 auto; background: #ffffff; padding: 12px 10px; box-shadow: 0 4px 18px rgba(0,0,0,0.12); border-radius: 4px; }
        .print-toolbar { position: sticky; top: 0; z-index: 9999; background: #0f172a; padding: 10px 14px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .print-toolbar-inner { max-width: 80mm; margin: 0 auto; display: flex; gap: 8px; align-items: center; }
        .btn-print { background: #c62828; color: #ffffff; border: none; padding: 8px 14px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 12px; font-family: inherit; }
        .btn-print:hover { background: #b71c1c; }
        .btn-download { background: #b8860b; color: #ffffff; text-decoration: none; padding: 8px 14px; border-radius: 6px; font-weight: bold; font-size: 12px; font-family: inherit; display: inline-block; }
        .btn-download:hover { background: #996515; }
        .btn-close { background: #334155; color: #ffffff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; margin-left: auto; font-family: inherit; }
        .btn-close:hover { background: #475569; }
    }

    @media print {
        .no-print { display: none !important; }
        body { margin: 0 !important; padding: 0 !important; background: transparent !important; }
        .pawn-container { box-shadow: none !important; padding: 0 !important; }
    }

    table { border-collapse: collapse; width: 100%; }

    .center { text-align: center; }
    .num { text-align: right; }
    .bold { font-weight: bold; }

    .shop-name {
        font-family: 'solaiman-lipi', 'baloo-da-2', 'hind-siliguri', sans-serif;
        font-size: 15px;
        font-weight: bold;
        line-height: 1.3;
    }

    .shop-line { font-size: 8px; line-height: 1.5; }

    .memo-title {
        margin: 3px 0;
        border-top: 1px solid #000000;
        border-bottom: 1px solid #000000;
        font-size: 10px;
        font-weight: bold;
        text-align: center;
        padding: 2px 0;
    }

    .section-title {
        margin: 4px 0 2px;
        font-size: 8.5px;
        font-weight: bold;
        text-align: center;
        border-bottom: 1px dashed #000000;
        padding-bottom: 1px;
    }

    .kv td { padding: 1px 0; vertical-align: top; }
    .kv .k { width: 38%; }
    .kv .v { width: 62%; font-weight: bold; }

    .items th, .items td { border: 1px solid #000000; padding: 2px 3px; font-size: 7.5px; }
    .items th { text-align: left; }

    .amount {
        margin: 4px 0;
        border: 1px solid #000000;
        padding: 4px 5px;
        font-size: 13px;
        font-weight: bold;
        text-align: center;
    }

    .words { font-size: 7.5px; line-height: 1.5; }
    .terms { font-size: 6.5px; line-height: 1.5; text-align: justify; }

    .sign td {
        width: 33.33%;
        text-align: center;
        font-size: 7.5px;
        padding-top: 12px;
        border-top: 1px solid #000000;
    }

    .footer { font-size: 7px; line-height: 1.5; text-align: center; margin-top: 3px; }
    .item-photo { height: 22px; }
</style>
