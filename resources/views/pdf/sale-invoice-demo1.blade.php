@php
    $m = fn (string $key): string => $labels[$key]['bn'].' <span class="en-gloss">'.$labels[$key]['en'].'</span>';
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
    $isThermal = ($size ?? 'a4') === 'thermal' || request('size') === 'thermal';
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $labels['saleMemo']['bn'] }} - {{ $invoice['invoice_no'] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Da+2:wght@500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 8mm 6mm; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Hind Siliguri', 'solaiman-lipi', 'noto-sans-bengali', sans-serif;
        font-size: 10.5px;
        color: #2a1608;
        background: #f1f5f9;
        padding-bottom: 30px;
    }
    .toolbar {
        position: sticky;
        top: 0;
        z-index: 9999;
        background: #0f172a;
        padding: 10px 16px;
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    }
    .toolbar button, .toolbar a {
        background: #b8860b;
        color: #ffffff;
        border: none;
        padding: 7px 16px;
        border-radius: 6px;
        font-weight: bold;
        cursor: pointer;
        font-size: 13px;
        font-family: inherit;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .toolbar button:hover, .toolbar a:hover { filter: brightness(1.1); }
    .toolbar button.btn-print { background: #c62828; }
    .toolbar button.alt, .toolbar a.alt { background: #334155; }
    .toolbar button.btn-close { background: #475569; margin-left: auto; }

    @media print {
        .toolbar { display: none !important; }
        body { margin: 0 !important; padding: 0 !important; background: transparent !important; }
        .frame { box-shadow: none !important; border: 2px solid #b8860b !important; }
    }

    table { border-collapse: collapse; width: 100%; }
    .frame { max-width: 210mm; margin: 0 auto; background: #ffffff; box-shadow: 0 4px 18px rgba(0,0,0,0.12); border: 2px solid #b8860b; padding: 0; }
    .frame-inner { border: 1px solid #fdf1c4; padding: 8px 10px; }
    .head { text-align: center; }
    .ribbon {
        display: inline-block;
        background-color: #c62828;
        color: #ffffff;
        font-size: 12px;
        font-weight: bold;
        padding: 4px 26px;
        border: 1px solid #7a1030;
    }
    .shop-name {
        font-family: 'Baloo Da 2', 'solaiman-lipi', sans-serif;
        font-size: 30px;
        font-weight: bold;
        color: #b8860b;
        line-height: 1.3;
        margin-top: 6px;
    }
    .shop-tagline { font-size: 10px; color: #4a3216; line-height: 1.6; margin-top: 4px; }
    .shop-contact { font-size: 10px; color: #7a1030; margin-top: 3px; font-weight: bold; }
    .shop-logo { height: 46px; }
    .meta { margin-top: 8px; }
    .meta table { border: 1px solid #d9c68a; }
    .meta td {
        border: 1px solid #d9c68a;
        padding: 5px 7px;
        font-size: 10.5px;
        vertical-align: top;
        width: 25%;
    }
    .meta .label { color: #7a1030; font-weight: bold; white-space: nowrap; width: 1%; }
    .meta .value { background-color: #fffdf6; }
    .en-gloss { font-size: 8px; color: #7a6a48; }
    .void-stamp {
        margin-top: 6px;
        text-align: center;
        border: 2px solid #c62828;
        color: #c62828;
        font-size: 13px;
        font-weight: bold;
        padding: 3px 0;
        letter-spacing: 1px;
    }
    .items { margin-top: 8px; }
    .items th {
        background-color: #c62828;
        color: #ffffff;
        border: 1px solid #9d1f1f;
        padding: 5px 4px;
        font-size: 10px;
        line-height: 1.3;
    }
    .items td {
        border: 1px solid #d9c68a;
        padding: 4px 4px;
        font-size: 10px;
        vertical-align: top;
    }
    .items .num { text-align: right; }
    .items .ctr { text-align: center; }
    .items .tag { font-size: 8.5px; color: #7a6a48; }
    .section-title {
        margin-top: 8px;
        background-color: #fdf1c4;
        border: 1px solid #d9c68a;
        border-bottom: none;
        padding: 3px 6px;
        font-weight: bold;
        color: #7a1030;
        font-size: 10.5px;
    }
    .totals { margin-top: 8px; }
    .totals td {
        border: 1px solid #d9c68a;
        padding: 4px 6px;
        font-size: 10.5px;
    }
    .totals .label { width: 34%; font-weight: bold; color: #7a1030; }
    .totals .num { text-align: right; width: 16%; }
    .totals .grand { font-size: 12px; font-weight: bold; color: #c62828; }
    .totals .due { font-size: 12px; font-weight: bold; color: #2a1608; }
    .totals .words { font-size: 9.5px; color: #4a3d28; padding: 3px 6px; }
    .payments th, .exchanges th, .payments td, .exchanges td {
        border: 1px solid #d9c68a;
        padding: 4px 5px;
        font-size: 10px;
    }
    .payments th, .exchanges th { background-color: #fbf3df; color: #7a1030; }
    .sign-row { margin-top: 14px; }
    .sign-row td { width: 33.33%; text-align: center; vertical-align: bottom; }
    .sign-line { border-top: 1px solid #b8860b; padding-top: 3px; font-size: 9.5px; color: #4a3216; }
    .thanks {
        font-family: 'Baloo Da 2', sans-serif;
        font-size: 13px;
        color: #7a1030;
        font-weight: bold;
    }
    .footer-note {
        margin-top: 8px;
        border-top: 1px dashed #d9c68a;
        padding-top: 5px;
        font-size: 8.5px;
        line-height: 1.7;
        color: #4a3d28;
        text-align: justify;
    }
    .banner {
        margin-top: 6px;
        background-color: #7a1030;
        color: #fdf1c4;
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        padding: 5px;
    }
    .printed { margin-top: 4px; font-size: 8px; color: #8a7a58; text-align: center; }
</style>
</head>
<body>

@if (request('format') === 'html' || request('view') === 'html' || !request()->has('download'))
<div class="toolbar">
    <button onclick="window.print()" class="btn-print">&#128438; {{ $labels['printA4']['bn'] ?? 'প্রিন্ট করুন' }}</button>
    <a href="{{ request()->fullUrlWithQuery(['download' => 1, 'format' => null, 'print' => null]) }}" class="alt">&#128190; PDF ডাউনলোড</a>
    <a href="{{ request()->fullUrlWithQuery(['template' => 'demo2']) }}" class="alt">&#127912; ডিজাইন ২ (আধুনিক প্যাড)</a>
    <button onclick="window.close()" class="btn-close">&#10005; বন্ধ করুন</button>
</div>
<script>
    if (new URLSearchParams(window.location.search).get('print') === '1') {
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 400);
        });
    }
</script>
@endif

<div class="frame">
    <div class="frame-inner">
        <table class="head">
            <tr>
                <td style="width: 22%; vertical-align: middle;">
                    @if ($shopLogo)
                        <img class="shop-logo" src="{{ $shopLogo }}" alt="">
                    @endif
                </td>
                <td style="width: 56%;">
                    <div class="ribbon">{{ $labels['saleMemo']['bn'] }}</div>
                    <div class="shop-name">{{ $shop['shop_name'] }}</div>
                    @if ($shop['shop_address'])
                        <div class="shop-tagline">{{ $shop['shop_address'] }}</div>
                    @endif
                    @if ($shop['shop_phone'])
                        <div class="shop-contact">&#9742; {{ $shop['shop_phone'] }}</div>
                    @endif
                </td>
                <td style="width: 22%; vertical-align: middle; text-align: right;">
                    <span class="en-gloss">{{ $labels['duplicate']['en'] }}</span>
                </td>
            </tr>
        </table>

        @if ($invoice['is_void'])
            <div class="void-stamp">
                {{ $labels['voided']['bn'] }}
                @if ($invoice['void_reason'])
                    &mdash; {{ $invoice['void_reason'] }}
                @endif
            </div>
        @endif

        <table class="meta">
            <tr>
                <td class="label">{!! $m('invoiceNo') !!}</td>
                <td class="value">{{ $invoice['invoice_no'] }}</td>
                <td class="label">{!! $m('date') !!}</td>
                <td class="value">{{ $invoice['date'] }}</td>
            </tr>
            <tr>
                <td class="label">{!! $m('customer') !!}</td>
                <td class="value">{{ $invoice['customer_name'] }}</td>
                <td class="label">{!! $m('phone') !!}</td>
                <td class="value">{{ $invoice['customer_phone'] }}</td>
            </tr>
            @if ($invoice['customer_address'] || $invoice['customer_code'] || $invoice['seller_name'])
                <tr>
                    @if ($invoice['customer_address'])
                        <td class="label">{!! $m('address') !!}</td>
                        <td class="value" colspan="3">{{ $invoice['customer_address'] }}</td>
                    @else
                        <td class="label">{!! $m('customerCode') !!}</td>
                        <td class="value">{{ $invoice['customer_code'] }}</td>
                        <td class="label">{!! $m('soldBy') !!}</td>
                        <td class="value">{{ $invoice['seller_name'] }}</td>
                    @endif
                </tr>
            @endif
        </table>

        <div class="section-title">{{ $labels['itemsHeading']['bn'] }} <span class="en-gloss">{{ $labels['itemsHeading']['en'] }}</span></div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 5%;">{{ $labels['serial']['bn'] }}</th>
                    <th style="width: 31%;">{{ $labels['description']['bn'] }}<br><span class="en-gloss">{{ $labels['description']['en'] }}</span></th>
                    <th style="width: 7%;">{{ $labels['karat']['bn'] }}</th>
                    <th style="width: 11%;">{{ $labels['weight']['bn'] }}<br><span class="en-gloss">{{ $weightUnitLabel }}</span></th>
                    <th style="width: 12%;">{{ $labels['ratePerGram']['bn'] }}</th>
                    <th style="width: 12%;">{{ $labels['goldValue']['bn'] }}</th>
                    <th style="width: 10%;">{{ $labels['making']['bn'] }}</th>
                    <th style="width: 12%;">{{ $labels['lineTotal']['bn'] }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td class="ctr">{{ $line['serial'] }}</td>
                        <td>
                            {{ $line['name'] }}
                            <div class="tag">
                                {{ $line['tag_no'] ?: $labels['handwrittenTag']['bn'] }}
                            </div>
                        </td>
                        <td class="ctr">{{ $line['karat'] }}K</td>
                        <td class="num">{{ $line['weight']['value'] }}</td>
                        <td class="num">{{ $money($line['rate']) }}</td>
                        <td class="num">{{ $money($line['gold_value']) }}</td>
                        <td class="num">
                            {{ $money($line['making']) }}
                            @if ((float) $line['stone_price'] > 0)
                                <div class="tag">+ {{ $money($line['stone_price']) }} {{ $labels['stone']['en'] }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $money($line['line_total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ctr">{{ $labels['itemsHeading']['en'] }}: 0</td></tr>
                @endforelse
                <tr>
                    <td colspan="3" style="background-color: #fbf3df;"><strong>{{ $labels['weight']['bn'] }}</strong></td>
                    <td class="num" colspan="2" style="background-color: #fbf3df;">
                        <strong>{{ $weight_summary['total_grams'] }} {{ $labels['gram']['en'] }}</strong>
                    </td>
                    <td colspan="3" class="num" style="background-color: #fbf3df;">
                        {{ $weight_summary['total_vori'] }} {{ $labels['vori']['en'] }}
                        / {{ $weight_summary['total_ana'] }} আনা
                    </td>
                </tr>
            </tbody>
        </table>

        @if ($exchanges)
            <div class="section-title">{{ $labels['exchangeHeading']['bn'] }} <span class="en-gloss">{{ $labels['exchangeHeading']['en'] }}</span></div>
            <table class="exchanges">
                <thead>
                    <tr>
                        <th style="width: 40%;">{{ $labels['description']['bn'] }}</th>
                        <th style="width: 10%;">{{ $labels['karat']['bn'] }}</th>
                        <th style="width: 18%;">{{ $labels['weight']['bn'] }} ({{ $weightUnitLabel }})</th>
                        <th style="width: 16%;">{{ $labels['ratePerGram']['bn'] }}</th>
                        <th style="width: 16%;">{{ $labels['lineTotal']['bn'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exchanges as $exchange)
                        <tr>
                            <td>{{ $exchange['description'] }}</td>
                            <td class="ctr">{{ $exchange['karat'] }}K</td>
                            <td class="num">{{ $exchange['weight']['value'] }}</td>
                            <td class="num">{{ $money($exchange['rate']) }}</td>
                            <td class="num">{{ $money($exchange['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <table class="totals">
            <tr>
                <td class="label">{{ $labels['subtotal']['bn'] }} <span class="en-gloss">{{ $labels['subtotal']['en'] }}</span></td>
                <td class="num">{{ $money($totals['subtotal']) }}</td>
                <td class="label">{{ $labels['paid']['bn'] }} <span class="en-gloss">{{ $labels['paid']['en'] }}</span></td>
                <td class="num">{{ $money($totals['paid']) }}</td>
            </tr>
            <tr>
                <td class="label">{{ $labels['discount']['bn'] }} <span class="en-gloss">{{ $labels['discount']['en'] }}</span></td>
                <td class="num">- {{ $money($totals['discount']) }}</td>
                <td class="label">{{ $labels['due']['bn'] }} <span class="en-gloss">{{ $labels['due']['en'] }}</span></td>
                <td class="num due">{{ $money($totals['due']) }}</td>
            </tr>
            <tr>
                <td class="label">{{ $labels['vat']['bn'] }} <span class="en-gloss">{{ $labels['vat']['en'] }} {{ $totals['vat_percentage'] }}%</span></td>
                <td class="num">{{ $money($totals['vat']) }}</td>
                <td class="label">{{ $labels['exchange']['bn'] }} <span class="en-gloss">{{ $labels['exchange']['en'] }}</span></td>
                <td class="num">- {{ $money($totals['exchange_amount']) }}</td>
            </tr>
            <tr>
                <td class="label grand">{{ $labels['grandTotal']['bn'] }} <span class="en-gloss">{{ $labels['grandTotal']['en'] }}</span></td>
                <td class="num grand">{{ $money($totals['total']) }}</td>
                <td colspan="2"></td>
            </tr>
        </table>
        <div class="totals words">
            {{ $labels['amountInWords']['bn'] }}: {{ $totals['in_words'] }}
            @if ((float) $totals['due'] > 0)
                &nbsp;|&nbsp; {{ $labels['due']['bn'] }}: {{ $totals['due_in_words'] }}
            @endif
        </div>

        @if ($payments)
            <div class="section-title">{{ $labels['paymentsHeading']['bn'] }} <span class="en-gloss">{{ $labels['paymentsHeading']['en'] }}</span></div>
            <table class="payments">
                <thead>
                    <tr>
                        <th style="width: 22%;">{{ $labels['method']['bn'] }}</th>
                        <th style="width: 30%;">{{ $labels['reference']['bn'] }}</th>
                        <th style="width: 24%;">{{ $labels['receivedBy']['bn'] }}</th>
                        <th style="width: 24%;">{{ $money($totals['paid']) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ $payment['method_label'] }}</td>
                            <td>{{ $payment['reference'] ?: '—' }}</td>
                            <td>{{ $payment['received_by'] }}</td>
                            <td class="num">{{ $money($payment['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="footer-note">{{ $labels['paidInFull']['bn'] }} &mdash; {{ $labels['paidInFull']['en'] }}</div>
        @endif

        @if ($invoice['notes'])
            <div class="footer-note">{{ $invoice['notes'] }}</div>
        @endif

        <table class="sign-row">
            <tr>
                <td><div class="sign-line">{{ $labels['customerSign']['bn'] }}</div></td>
                <td class="thanks">{{ $labels['thanks']['bn'] }}</td>
                <td><div class="sign-line">{{ $labels['sellerSign']['bn'] }}</div></td>
            </tr>
        </table>

        @if ($footer)
            <div class="footer-note">{!! nl2br(e($footer)) !!}</div>
        @endif

        <div class="banner">&#10022; {{ $shop['shop_name'] }} &#10022;</div>
        <div class="printed">{{ $labels['printNote']['bn'] }} {{ $labels['printNote']['en'] }} &middot; {{ $printed_at }}</div>
    </div>
</div>

</body>
</html>
