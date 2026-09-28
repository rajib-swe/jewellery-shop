@php
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
@endphp
@include('pdf.partials.pawn-a4')

<div class="pawn-container">
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
                {{ $labels['redemptionReceipt']['bn'] }}
                <span class="en-gloss">{{ $labels['redemptionReceipt']['en'] }}</span>
            </div>
        </td>
    </tr>
</table>

<table class="panel">
    <tr>
        <td class="label">{{ $labels['receiptNo']['bn'] }}</td>
        <td class="value">{{ $receipt['receipt_no'] }}</td>
        <td class="label">{{ $labels['releasedOn']['bn'] }}</td>
        <td class="value">{{ $receipt['date'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['pawnNo']['bn'] }}</td>
        <td class="value">{{ $pawn['pawn_no'] }}</td>
        <td class="label">{{ $labels['pledgedOn']['bn'] }}</td>
        <td class="value">{{ $pawn['date'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['customer']['bn'] }}</td>
        <td class="value" colspan="3">{{ $customer['name'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['phone']['bn'] }}</td>
        <td class="value">{{ $customer['phone'] }}</td>
        <td class="label">{{ $labels['customerCode']['bn'] }}</td>
        <td class="value">{{ $customer['code'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['method']['bn'] }}</td>
        <td class="value">{{ $receipt['method_label'] }}</td>
        <td class="label">{{ $labels['receivedBy']['bn'] }}</td>
        <td class="value">{{ $receipt['received_by'] }}</td>
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

<div class="section-title">{{ $labels['grandTotal']['bn'] }} · {{ $labels['grandTotal']['en'] }}</div>

<table class="panel">
    <tr>
        <td class="label">{{ $labels['principal']['bn'] }}</td>
        <td class="value num">{{ $money($pawn['principal']) }}</td>
        <td class="label">{{ $labels['interestAccrued']['bn'] }}</td>
        <td class="value num">{{ $money($summary['interest_accrued']) }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['interestRate']['bn'] }}</td>
        <td class="value num">{{ $pawn['interest_rate'] }}%</td>
        <td class="label">{{ $labels['grandTotal']['bn'] }}</td>
        <td class="value num bold" colspan="3">{{ $money($receipt['amount']) }}</td>
    </tr>
</table>

<div class="section-title">{{ $labels['goodsReturned']['bn'] }} · {{ $labels['goodsReturned']['en'] }}</div>

<table class="grid">
    <thead>
        <tr>
            <th style="width: 8%;">{{ $labels['serial']['bn'] }}</th>
            <th style="width: 40%;">{{ $labels['description']['bn'] }}</th>
            <th style="width: 12%;">{{ $labels['karat']['bn'] }}</th>
            <th style="width: 20%;">{{ $labels['weight']['bn'] }}</th>
            <th style="width: 20%;">{{ $labels['estimatedValue']['bn'] }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $index => $item)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $item['description'] }}</td>
                <td class="center">{{ $item['karat'] }}K</td>
                <td class="num">{{ $item['net_weight']['value'] }} {{ $weightUnitLabel }}</td>
                <td class="num">{{ $money($item['estimated_value']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="sign">
    <tr>
        <td><div class="line">{{ $labels['customerSign']['bn'] }}</div></td>
        <td><div class="line">{{ $labels['receivedBy']['bn'] }}</div></td>
        <td><div class="line">{{ $labels['cashierSign']['bn'] }}</div></td>
    </tr>
</table>

@if ($terms)
    <div class="footer-note">{!! nl2br(e($terms)) !!}</div>
@endif

@if ($footer)
    <div class="footer-note">{!! nl2br(e($footer)) !!}</div>
@endif

<div class="printed">{{ $labels['printNote']['bn'] }} {{ $labels['printNote']['en'] }} &middot; {{ $printed_at }}</div>
</div>
