@php
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
    $typeLabel = match ($receipt['type']) {
        'principal' => $labels['principalOnly']['bn'],
        default => $labels['interestOnly']['bn'],
    };
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
                {{ $labels['pawnPaymentReceipt']['bn'] }}
                <span class="en-gloss">{{ $labels['pawnPaymentReceipt']['en'] }}</span>
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
        <td class="label">{{ $labels['pawnNo']['bn'] }}</td>
        <td class="value">{{ $pawn['pawn_no'] }}</td>
        <td class="label">{{ $labels['description']['bn'] }}</td>
        <td class="value">{{ $typeLabel }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['customer']['bn'] }}</td>
        <td class="value" colspan="3">{{ $customer['name'] }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['phone']['bn'] }}</td>
        <td class="value">{{ $customer['phone'] }}</td>
        <td class="label">{{ $labels['receivedBy']['bn'] }}</td>
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

<div class="section-title">{{ $labels['ledger']['bn'] }} · {{ $labels['ledger']['en'] }}</div>

<table class="panel">
    <tr>
        <td class="label">{{ $labels['principal']['bn'] }}</td>
        <td class="value num">{{ $money($pawn['principal']) }}</td>
        <td class="label">{{ $labels['principalPaid']['bn'] }}</td>
        <td class="value num">{{ $money($summary['principal_paid']) }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['outstandingPrincipal']['bn'] }}</td>
        <td class="value num">{{ $money($summary['outstanding_principal']) }}</td>
        <td class="label">{{ $labels['interestRate']['bn'] }}</td>
        <td class="value num">{{ $pawn['interest_rate'] }}%</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['interestAccrued']['bn'] }}</td>
        <td class="value num">{{ $money($summary['interest_accrued']) }}</td>
        <td class="label">{{ $labels['interestPaid']['bn'] }}</td>
        <td class="value num">{{ $money($summary['interest_paid']) }}</td>
    </tr>
    <tr>
        <td class="label">{{ $labels['interestDue']['bn'] }}</td>
        <td class="value num">{{ $money($summary['interest_due']) }}</td>
        <td class="label">{{ $labels['totalPayable']['bn'] }}</td>
        <td class="value num bold">{{ $money($summary['total_payable']) }}</td>
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
</div>
