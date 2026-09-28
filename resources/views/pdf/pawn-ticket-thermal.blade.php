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
        <td><div class="memo-title">{{ $labels['pawnTicket']['bn'] }}</div></td>
    </tr>
</table>

@if ($pawn['is_forfeited'])
    <div class="center bold">{{ $labels['forfeited']['bn'] }}</div>
@endif

<table class="kv">
    <tr>
        <td class="k">{{ $labels['pawnNo']['bn'] }}</td>
        <td class="v">{{ $pawn['pawn_no'] }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['pledgedOn']['bn'] }}</td>
        <td class="v">{{ $pawn['date'] }}</td>
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
        <td class="k">{{ $labels['dueDate']['bn'] }}</td>
        <td class="v">{{ $pawn['due_date'] }}</td>
    </tr>
</table>

<div class="section-title">{{ $labels['pledgedItems']['bn'] }}</div>

<table class="items">
    <thead>
        <tr>
            <th>{{ $labels['description']['bn'] }}</th>
            <th style="width: 12%;">{{ $labels['karat']['bn'] }}</th>
            <th style="width: 22%;">{{ $labels['weight']['bn'] }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td class="center">{{ $item['karat'] }}K</td>
                <td class="num">{{ $item['net_weight']['value'] }} {{ $weightUnitLabel }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="center">—</td></tr>
        @endforelse
    </tbody>
</table>

<table class="kv">
    <tr>
        <td class="k">{{ $labels['principal']['bn'] }}</td>
        <td class="v num">{{ $money($pawn['principal']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['interestRate']['bn'] }}</td>
        <td class="v num">{{ $pawn['interest_rate'] }}%</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['interestAccrued']['bn'] }}</td>
        <td class="v num">{{ $money($summary['interest_accrued']) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $labels['interestDue']['bn'] }}</td>
        <td class="v num">{{ $money($summary['interest_due']) }}</td>
    </tr>
</table>

<div class="amount">{{ $money($summary['total_payable']) }}</div>
<div class="words center">{{ $labels['totalPayable']['bn'] }} · {{ $labels['amountInWords']['bn'] }}: {{ \App\Support\DocumentFormat::amountInWords($summary['total_payable']) }}</div>

@if ($terms)
    <div class="section-title">{{ $labels['termsHeading']['bn'] }}</div>
    <div class="terms">{!! nl2br(e($terms)) !!}</div>
@endif

<table class="sign">
    <tr>
        <td>{{ $labels['customerSign']['bn'] }}</td>
        <td>{{ $labels['receivedBy']['bn'] }}</td>
        <td>{{ $labels['cashierSign']['bn'] }}</td>
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
