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
                {{ $labels['pawnTicket']['bn'] }}
                <span class="en-gloss">{{ $labels['pawnTicket']['en'] }}</span>
            </div>
        </td>
    </tr>
</table>

@if ($pawn['is_forfeited'])
    <div class="center"><span class="void-stamp">{{ $labels['forfeited']['bn'] }}</span></div>
@endif

<table class="panel">
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
        <td class="label">{{ $labels['address']['bn'] }}</td>
        <td class="value" colspan="3">{{ $customer['address'] ?: '—' }}</td>
    </tr>
</table>

<div class="section-title">{{ $labels['pledgedItems']['bn'] }} · {{ $labels['pledgedItems']['en'] }}</div>

<table class="grid">
    <thead>
        <tr>
            <th style="width: 6%;">{{ $labels['serial']['bn'] }}</th>
            <th style="width: 32%;">{{ $labels['description']['bn'] }}</th>
            <th style="width: 8%;">{{ $labels['karat']['bn'] }}</th>
            <th style="width: 16%;">{{ $labels['weight']['bn'] }}</th>
            <th style="width: 18%;">{{ $labels['estimatedValue']['bn'] }}</th>
            <th style="width: 20%;">{{ $labels['itemPhoto']['bn'] }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $item)
            <tr>
                <td class="center">{{ $item['serial'] }}</td>
                <td>{{ $item['description'] }}</td>
                <td class="center">{{ $item['karat'] }}K</td>
                <td class="num">
                    {{ $item['net_weight']['value'] }} {{ $weightUnitLabel }}
                    <div class="en-gloss">{{ $item['gross_weight']['value'] }} g gross</div>
                </td>
                <td class="num">{{ $money($item['estimated_value']) }}</td>
                <td class="center">
                    @if ($item['photo'])
                        <img src="{{ $item['photo'] }}" alt="" class="item-photo">
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="center">—</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">{{ $labels['ledger']['bn'] }} · {{ $labels['ledger']['en'] }}</div>

<table class="panel">
    <tr>
        <td class="label">{{ $labels['principal']['bn'] }}</td>
        <td class="value num">{{ $money($pawn['principal']) }}</td>
        <td class="label">{{ $labels['interestRate']['bn'] }}</td>
        <td class="value num">{{ $pawn['interest_rate'] }}%</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['interestType']['bn'] }}</td>
        <td class="value">{{ $labels['simpleInterest']['bn'] }}</td>
        <td class="label">{{ $labels['loanToValue']['bn'] }}</td>
        <td class="value num">{{ $pawn['loan_to_value'] }}%</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['termStart']['bn'] }}</td>
        <td class="value">{{ $pawn['term_start'] }}</td>
        <td class="label">{{ $labels['dueDate']['bn'] }}</td>
        <td class="value num">{{ $pawn['due_date'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['outstandingPrincipal']['bn'] }}</td>
        <td class="value num">{{ $money($summary['outstanding_principal']) }}</td>
        <td class="label">{{ $labels['interestDue']['bn'] }}</td>
        <td class="value num">{{ $money($summary['interest_due']) }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['grandTotal']['bn'] }}</td>
        <td class="value num bold" colspan="3">{{ $money($summary['total_payable']) }}</td>
    </tr>
</table>

<div class="words">
    {{ $labels['amountInWords']['bn'] }}: {{ \App\Support\DocumentFormat::amountInWords($summary['total_payable']) }}
</div>

@if ($terms)
    <div class="section-title">{{ $labels['termsHeading']['bn'] }} · {{ $labels['termsHeading']['en'] }}</div>
    <div class="terms">{!! nl2br(e($terms)) !!}</div>
@endif

<table class="sign">
    <tr>
        <td><div class="line">{{ $labels['customerSign']['bn'] }}</div></td>
        <td><div class="line">{{ $labels['receivedBy']['bn'] }}</div></td>
        <td><div class="line">{{ $labels['cashierSign']['bn'] }}</div></td>
    </tr>
</table>

@if ($footer)
    <div class="footer-note">{!! nl2br(e($footer)) !!}</div>
@endif

<div class="printed">{{ $labels['printNote']['bn'] }} {{ $labels['printNote']['en'] }} &middot; {{ $printed_at }}</div>
</div>
