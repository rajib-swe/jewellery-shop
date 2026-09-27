@php
    $m = fn (string $key): string => $labels[$key]['bn'].' <span class="en-gloss">'.$labels[$key]['en'].'</span>';
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
@endphp
@include('pdf.partials.sale-invoice-a4')

<table class="frame">
    <tr>
        <td>
            <div class="frame-inner">
                <table class="head">
                    <tr>
                        <td style="width: 22%; vertical-align: middle;">
                            @if ($shopLogo)
                                <img class="shop-logo" src="{{ $shopLogo }}" alt="">
                            @endif
                        </td>
                        <td style="width: 56%;">
                            <span class="ribbon">{{ $labels['saleMemo']['bn'] }}</span>
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
                            <td colspan="3" style="background-color: {{ '#fbf3df' }};"><strong>{{ $labels['weight']['bn'] }}</strong></td>
                            <td class="num" colspan="2" style="background-color: {{ '#fbf3df' }};">
                                <strong>{{ $weight_summary['total_grams'] }} {{ $labels['gram']['en'] }}</strong>
                            </td>
                            <td colspan="3" class="num" style="background-color: {{ '#fbf3df' }};">
                                {{ $weight_summary['total_vori'] }} {{ $labels['vori']['en'] }}
                                / {{ $weight_summary['total_ana'] }} {{ 'আনা' }}
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
        </td>
    </tr>
</table>
