@php
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
@endphp
@include('pdf.partials.pawn-thermal')

<div class="pawn-container">
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
        <td><div class="memo-title">{{ $labels['redemptionReceipt']['bn'] }}</div></td>
    </tr>
</table>

<table class="kv">
    <tr>
        <td class="k">{{ $labels['receiptNo']['bn'] }}</td>
        <td class="v">{{ $receipt['receipt_no'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['releasedOn']['bn'] }}</td>
        <td class="v">{{ $receipt['date'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['pawnNo']['bn'] }}</td>
        <td class="v">{{ $pawn['pawn_no'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['customer']['bn'] }}</td>
        <td class="v">{{ $customer['name'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['phone']['bn'] }}</td>
        <td class="v">{{ $customer['phone'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['method']['bn'] }}</td>
        <td class="v">{{ $receipt['method_label'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['receivedBy']['bn'] }}</td>
        <td class="v">{{ $receipt['received_by'] }}</td>
    </tr>
</table>

<div class="amount">{{ $money($receipt['amount']) }}</div>
<div class="words center">{{ $labels['amountInWords']['bn'] }}: {{ $amount_in_words }}</div>

<table class="kv">
    <tr>
        <td class="k">{{ $labels['principal']['bn'] }}</td>
        <td class="v num">{{ $money($pawn['principal']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['interestAccrued']['bn'] }}</td>
        <td class="v num">{{ $money($summary['interest_accrued']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['grandTotal']['bn'] }}</td>
        <td class="v num bold">{{ $money($summary['total_payable']) }}</td>
    </tr>
</table>

<div class="section-title">{{ $labels['goodsReturned']['bn'] }}</div>

<table class="items">
    <thead>
        <tr>
            <th>{{ $labels['description']['bn'] }}</th>
            <th style="width: 12%;">{{ $labels['karat']['bn'] }}</th>
            <th style="width: 24%;">{{ $labels['weight']['bn'] }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td class="center">{{ $item['karat'] }}K</td>
                <td class="num">{{ $item['net_weight']['value'] }} {{ $weightUnitLabel }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="sign">
    <tr>
        <td>{{ $labels['customerSign']['bn'] }}</td>
        <td>{{ $labels['receivedBy']['bn'] }}</td>
        <td>{{ $labels['cashierSign']['bn'] }}</td>
    </tr>
</table>

@if ($terms)
    <div class="terms">{!! nl2br(e($terms)) !!}</div>
@endif

@if ($footer)
    <div class="footer">{!! nl2br(e($footer)) !!}</div>
@endif

<div class="footer">
    <div class="bold">{{ $labels['thanks']['bn'] }}</div>
    {{ $labels['printNote']['bn'] }}<br>{{ $printed_at }}
</div>
</div>
