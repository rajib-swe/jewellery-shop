@php
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
@endphp
@include('pdf.partials.payment-receipt-thermal')

<div class="thermal-container">
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
        <td><div class="memo-title">{{ $labels['paymentReceipt']['bn'] }}</div></td>
    </tr>
</table>

<table class="kv">
    <tr>
        <td class="k">{{ $labels['receiptNo']['bn'] }}</td>
        <td class="v">{{ $receipt['receipt_no'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['date']['bn'] }}</td>
        <td class="v">{{ $receipt['date'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['customer']['bn'] }}</td>
        <td class="v">{{ $customer['name'] }}</td>
    </tr>
    @if ($customer['phone'])
        <tr>
            <td class="k">{{ $labels['phone']['bn'] }}</td>
            <td class="v">{{ $customer['phone'] }}</td>
        </tr>
    @endif
    <tr>
        <td class="k">{{ $labels['invoiceNo']['bn'] }}</td>
        <td class="v">{{ $sale['invoice_no'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['method']['bn'] }}</td>
        <td class="v">{{ $receipt['method_label'] }}</td>
    </tr>
    @if ($receipt['reference'])
        <tr>
            <td class="k">{{ $labels['reference']['bn'] }}</td>
            <td class="v">{{ $receipt['reference'] }}</td>
        </tr>
    @endif
    <tr>
        <td class="k">{{ $labels['receivedBy']['bn'] }}</td>
        <td class="v">{{ $receipt['received_by'] }}</td>
    </tr>
</table>

<div class="amount">{{ $money($receipt['amount']) }}</div>
<div class="words center">{{ $labels['amountInWords']['bn'] }}: {{ $amount_in_words }}</div>

<table class="kv">
    <tr>
        <td class="k">{{ $labels['grandTotal']['bn'] }}</td>
        <td class="v num">{{ $money($sale['total']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['paid']['bn'] }}</td>
        <td class="v num">{{ $money($sale['paid']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['due']['bn'] }}</td>
        <td class="v num bold">{{ $money($sale['due']) }}</td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td>{{ $labels['customerSign']['bn'] }}</td>
        <td>{{ $labels['receivedBy']['bn'] }}</td>
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
</div>
