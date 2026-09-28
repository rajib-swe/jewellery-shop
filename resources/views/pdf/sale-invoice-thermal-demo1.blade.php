@php
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
@endphp
@include('pdf.partials.sale-invoice-thermal')

<table>
    <tr>
        <td class="center">
            @if ($shopLogo)
                <img src="{{ $shopLogo }}" alt="" style="height: 26px;">
            @endif
            <div class="shop-name">{{ $shop['shop_name'] }}</div>
            @if ($shop['shop_address'])
                <div class="shop-line">{{ $shop['shop_address'] }}</div>
            @endif
            @if ($shop['shop_phone'])
                <div class="shop-line">&#9742; {{ $shop['shop_phone'] }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td><div class="memo-title">{{ $labels['saleMemo']['bn'] }}</div></td>
    </tr>
</table>

@if ($invoice['is_void'])
    <div class="void-stamp">
        {{ $labels['voided']['bn'] }}
        @if ($invoice['void_reason'])
            <br>{{ $invoice['void_reason'] }}
        @endif
    </div>
@endif

<table class="kv">
    <tr>
        <td class="k">{{ $labels['invoiceNo']['bn'] }}</td>
        <td class="v">{{ $invoice['invoice_no'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['date']['bn'] }}</td>
        <td class="v">{{ $invoice['date'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['customer']['bn'] }}</td>
        <td class="v">{{ $invoice['customer_name'] }}</td>
    </tr>
    @if ($invoice['customer_phone'])
        <tr>
            <td class="k">{{ $labels['phone']['bn'] }}</td>
            <td class="v">{{ $invoice['customer_phone'] }}</td>
        </tr>
    @endif
    @if ($invoice['customer_address'])
        <tr>
            <td class="k">{{ $labels['address']['bn'] }}</td>
            <td class="v">{{ $invoice['customer_address'] }}</td>
        </tr>
    @endif
    @if ($invoice['seller_name'])
        <tr>
            <td class="k">{{ $labels['soldBy']['bn'] }}</td>
            <td class="v">{{ $invoice['seller_name'] }}</td>
        </tr>
    @endif
</table>

<div class="rule"></div>
<div class="section">{{ $labels['itemsHeading']['bn'] }}</div>

<table class="items">
    <thead>
        <tr>
            <th style="width: 42%;">{{ $labels['description']['bn'] }}</th>
            <th style="width: 16%;">{{ $labels['karat']['bn'] }}</th>
            <th style="width: 20%;">{{ $labels['weight']['bn'] }}</th>
            <th style="width: 22%;">{{ $labels['lineTotal']['bn'] }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lines as $line)
            <tr>
                <td>
                    {{ $line['serial'] }}. {{ $line['name'] }}
                    <div class="tag">{{ $line['tag_no'] ?: $labels['handwrittenTag']['bn'] }}</div>
                </td>
                <td class="num">{{ $line['karat'] }}K</td>
                <td class="num">{{ $line['weight']['value'] }} {{ $line['weight']['unit'] === 'vori' ? $labels['vori']['bn'] : $labels['gram']['bn'] }}</td>
                <td class="num">{{ $money($line['line_total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="center">0</td></tr>
        @endforelse
        <tr>
            <td class="bold" colspan="2">{{ $labels['weight']['bn'] }}</td>
            <td class="num bold" colspan="2">{{ $weight_summary['total_grams'] }} {{ $labels['gram']['bn'] }}</td>
        </tr>
    </tbody>
</table>

@if ($exchanges)
    <div class="section">{{ $labels['exchangeHeading']['bn'] }}</div>
    <table class="exchanges">
        @foreach ($exchanges as $exchange)
            <tr>
                <td style="width: 50%;">{{ $exchange['description'] }}</td>
                <td style="width: 16%;">{{ $exchange['karat'] }}K</td>
                <td style="width: 16%;" class="num">{{ $exchange['weight']['value'] }}</td>
                <td style="width: 18%;" class="num">- {{ $money($exchange['amount']) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<div class="rule"></div>

<table class="totals">
    <tr>
        <td class="k">{{ $labels['subtotal']['bn'] }}</td>
        <td class="v">{{ $money($totals['subtotal']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['discount']['bn'] }}</td>
        <td class="v">- {{ $money($totals['discount']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['vat']['bn'] }} {{ $totals['vat_percentage'] }}%</td>
        <td class="v">{{ $money($totals['vat']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['exchange']['bn'] }}</td>
        <td class="v">- {{ $money($totals['exchange_amount']) }}</td>
    </tr>
    <tr class="grand">
        <td class="k">{{ $labels['grandTotal']['bn'] }}</td>
        <td class="v">{{ $money($totals['total']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['paid']['bn'] }}</td>
        <td class="v">{{ $money($totals['paid']) }}</td>
    </tr>
    <tr class="due">
        <td class="k">{{ $labels['due']['bn'] }}</td>
        <td class="v">{{ $money($totals['due']) }}</td>
    </tr>
</table>

<div class="words">{{ $labels['amountInWords']['bn'] }}: {{ $totals['in_words'] }}</div>

@if ($payments)
    <div class="section">{{ $labels['paymentsHeading']['bn'] }}</div>
    <table class="payments">
        @foreach ($payments as $payment)
            <tr>
                <td style="width: 34%;">{{ $payment['method_label'] }}</td>
                <td style="width: 30%;">{{ $payment['reference'] }}</td>
                <td style="width: 36%;" class="num">{{ $money($payment['amount']) }}</td>
            </tr>
        @endforeach
    </table>
@else
    <div class="words">{{ $labels['paidInFull']['bn'] }}</div>
@endif

@if ($invoice['notes'])
    <div class="words">{{ $invoice['notes'] }}</div>
@endif

<table class="sign">
    <tr>
        <td>{{ $labels['customerSign']['bn'] }}</td>
        <td>{{ $labels['cashierSign']['bn'] }}</td>
        <td>{{ $labels['sellerSign']['bn'] }}</td>
    </tr>
</table>

@if ($footer)
    <div class="footer">{!! nl2br(e($footer)) !!}</div>
@endif

<div class="footer">
    <div class="bold">{{ $labels['thanks']['bn'] }}</div>
    {{ $labels['printNote']['bn'] }}<br>{{ $printed_at }}
</div>
