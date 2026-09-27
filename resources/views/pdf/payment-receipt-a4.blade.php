@php
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
@endphp
@include('pdf.partials.payment-receipt-a4')

<table>
    <tr>
        <td class="center">
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
        </td>
    </tr>
    <tr>
        <td>
            <div class="title">
                {{ $labels['paymentReceipt']['bn'] }}
                <span class="en-gloss">{{ $labels['paymentReceipt']['en'] }}</span>
            </div>
        </td>
    </tr>
</table>

<table class="panel">
    <tr>
        <td class="label">{{ $labels['receiptNo']['bn'] }}</td>
        <td class="value">{{ $receipt['receipt_no'] }}</td>
        <td class="label">{{ $labels['date']['bn'] }}</td>
        <td class="value">{{ $receipt['date'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['customer']['bn'] }}</td>
        <td class="value">{{ $customer['name'] }}</td>
        <td class="label">{{ $labels['phone']['bn'] }}</td>
        <td class="value">{{ $customer['phone'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['invoiceNo']['bn'] }}</td>
        <td class="value">{{ $sale['invoice_no'] }}</td>
        <td class="label">{{ $labels['soldBy']['bn'] }}</td>
        <td class="value">{{ $receipt['received_by'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['method']['bn'] }}</td>
        <td class="value">{{ $receipt['method_label'] }}</td>
        <td class="label">{{ $labels['reference']['bn'] }}</td>
        <td class="value">{{ $receipt['reference'] ?: '—' }}</td>
    </tr>
</table>

<table>
    <tr>
        <td>
            <div class="amount">
                {{ $money($receipt['amount']) }}
                <span class="en-gloss" style="font-size: 9px;">{{ $labels['amountInWords']['bn'] }}: {{ $amount_in_words }}</span>
            </div>
        </td>
    </tr>
</table>

<table class="panel">
    <tr>
        <td class="label">{{ $labels['grandTotal']['bn'] }}</td>
        <td class="value num">{{ $money($sale['total']) }}</td>
        <td class="label">{{ $labels['paid']['bn'] }}</td>
        <td class="value num">{{ $money($sale['paid']) }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['due']['bn'] }}</td>
        <td class="value num">{{ $money($sale['due']) }}</td>
        <td class="label">{{ $labels['date']['bn'] }}</td>
        <td class="value">{{ $receipt['received_at'] }}</td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td><div class="line">{{ $labels['customerSign']['bn'] }}</div></td>
        <td><div class="line">{{ $labels['receivedBy']['bn'] }}</div></td>
        <td><div class="line">{{ $labels['sellerSign']['bn'] }}</div></td>
    </tr>
</table>

@if ($footer)
    <div class="footer-note">{!! nl2br(e($footer)) !!}</div>
@endif

<div class="printed">{{ $labels['printNote']['bn'] }} {{ $labels['printNote']['en'] }} &middot; {{ $printed_at }}</div>
