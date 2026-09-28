@php
    $m = fn (string $key): string => $labels[$key]['bn'] ?? $key;
    $money = fn ($value): string => $currency.number_format((float) $value, 2, '.', ',');
    $isThermal = ($size ?? 'a4') === 'thermal' || request('size') === 'thermal';
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $m('saleMemo') }} - {{ $invoice['invoice_no'] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Da+2:wght@500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --gold: #9a7420;
    --gold-light: #f6efdc;
    --gold-border: #d4af37;
    --ink: #1f1f1f;
    --muted: #6b6b6b;
    --line: #d9cfb4;
    --red: #b3261e;
  }
  * { box-sizing: border-box; }
  .box h3, .terms h4, .doc-title h2, .brand h1 { letter-spacing: 0 !important; text-transform: none !important; }
  body {
    margin: 0;
    background: #e9e9e9;
    color: var(--ink);
    font-family: 'Hind Siliguri', 'Noto Sans Bengali', 'SolaimanLipi', sans-serif;
    font-size: 12px;
    line-height: 1.45;
  }

  .toolbar {
    position: sticky;
    top: 0;
    background: #1e293b;
    color: #fff;
    padding: 10px 16px;
    display: flex;
    gap: 10px;
    align-items: center;
    z-index: 999;
    box-shadow: 0 2px 8px rgba(0,0,0,.25);
  }
  .toolbar button, .toolbar a {
    background: var(--gold);
    color: #fff;
    border: 0;
    padding: 7px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }
  .toolbar button:hover, .toolbar a:hover { filter: brightness(1.1); }
  .toolbar button.alt { background: #334155; }
  .toolbar button.btn-close { background: #475569; margin-left: auto; }
  .toolbar .active-btn { background: #b8860b !important; box-shadow: 0 0 0 2px #fff; }
  .toolbar span { font-size: 12px; opacity: .8; }

  /* ---------- A4 sheet ---------- */
  .sheet {
    width: 210mm;
    min-height: 297mm;
    margin: 16px auto;
    background: #fff;
    padding: 12mm;
    position: relative;
    box-shadow: 0 2px 14px rgba(0,0,0,.15);
    border-top: 6px solid var(--gold);
  }
  .sheet::before {
    content: "";
    position: absolute;
    inset: 0;
    margin: auto;
    width: 95mm;
    height: 95mm;
    opacity: .035;
    background: radial-gradient(circle, var(--gold) 0 30%, transparent 31% 40%, var(--gold) 41% 43%, transparent 44%);
    pointer-events: none;
  }

  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--gold);
    padding-bottom: 10px;
  }
  .brand { display: flex; gap: 12px; align-items: center; }
  .logo {
    width: 58px;
    height: 58px;
    border: 2px solid var(--gold);
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: var(--gold);
    font-weight: 700;
    font-size: 24px;
    background: var(--gold-light);
    overflow: hidden;
  }
  .logo img { width: 100%; height: 100%; object-fit: contain; }
  .brand h1 { margin: 0; font-size: 24px; color: var(--gold); font-family: 'Baloo Da 2', sans-serif; font-weight: 700; }
  .brand p { margin: 2px 0; color: var(--muted); font-size: 11px; }
  .doc-title { text-align: right; }
  .doc-title h2 { margin: 0; font-size: 20px; color: var(--ink); font-family: 'Baloo Da 2', sans-serif; }
  .doc-title .badge {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 10px;
    border: 1px solid var(--gold);
    color: var(--gold);
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
  }
  .void-stamp {
    border: 2px solid var(--red);
    color: var(--red);
    padding: 2px 8px;
    font-weight: bold;
    border-radius: 4px;
    display: inline-block;
    margin-top: 4px;
  }

  .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 12px 0; }
  .box { border: 1px solid var(--line); border-radius: 4px; padding: 8px 10px; background: #fff; }
  .box h3 { margin: 0 0 4px; font-size: 10.5px; color: var(--gold); font-weight: 700; }
  .box table { width: 100%; border-collapse: collapse; }
  .box td { padding: 2px 0; vertical-align: top; font-size: 11.5px; }
  .box td:first-child { color: var(--muted); width: 85px; }

  .rate-strip {
    background: var(--gold-light);
    border: 1px solid var(--line);
    border-radius: 4px;
    padding: 5px 10px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    align-items: center;
  }
  .rate-strip strong { color: var(--gold); font-weight: 700; }

  table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
  table.items th {
    background: var(--gold);
    color: #fff;
    font-weight: 600;
    padding: 6px 5px;
    font-size: 11px;
    text-align: left;
  }
  table.items td { padding: 6px 5px; border-bottom: 1px solid var(--line); font-size: 11px; }
  table.items tbody tr:nth-child(even) { background: #fcfaf3; }
  .r { text-align: right; }
  .c { text-align: center; }
  .sub { display: block; color: var(--muted); font-size: 9.5px; }
  .summary-row td { background: var(--gold-light) !important; font-weight: bold; }

  .bottom { display: grid; grid-template-columns: 1.1fr 1fr; gap: 14px; margin-top: 12px; }
  .words { font-size: 11px; color: var(--muted); margin-bottom: 8px; line-height: 1.5; }
  .totals table { width: 100%; border-collapse: collapse; }
  .totals td { padding: 4px 6px; font-size: 11.5px; }
  .totals tr.grand td { background: var(--gold); color: #fff; font-weight: 700; font-size: 13.5px; }
  .totals tr.due td { font-weight: 700; color: var(--red); font-size: 12px; }
  .totals td:last-child { text-align: right; }

  .terms { margin-top: 12px; font-size: 10px; color: var(--muted); }
  .terms h4 { margin: 0 0 3px; color: var(--gold); font-size: 10.5px; font-weight: 700; }
  .terms ol { margin: 0; padding-left: 16px; line-height: 1.5; }

  .signs { display: flex; justify-content: space-between; margin-top: 36px; }
  .signs div { width: 38%; border-top: 1px dashed #888; text-align: center; padding-top: 4px; font-size: 11px; color: var(--muted); }
  footer {
    position: absolute;
    left: 12mm;
    right: 12mm;
    bottom: 8mm;
    text-align: center;
    font-size: 10px;
    color: var(--muted);
    border-top: 1px solid var(--line);
    padding-top: 5px;
  }

  /* ---------- Thermal 80mm Mode ---------- */
  body.thermal .sheet {
    width: 80mm;
    min-height: 0;
    padding: 4mm;
    margin: 16px auto;
    border-top: 0;
    font-size: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.2);
  }
  body.thermal .sheet::before { display: none; }
  body.thermal header { flex-direction: column; text-align: center; gap: 4px; border-bottom: 1px dashed #000; }
  body.thermal .brand { flex-direction: column; gap: 2px; }
  body.thermal .logo { display: none; }
  body.thermal .brand h1 { font-size: 16px; color: #000; }
  body.thermal .doc-title { text-align: center; }
  body.thermal .meta, body.thermal .bottom { grid-template-columns: 1fr; gap: 6px; }
  body.thermal .box { border: 0; border-bottom: 1px dashed #000; border-radius: 0; padding: 4px 0; }
  body.thermal .rate-strip { display: none; }
  body.thermal table.items th { background: #000; font-size: 9.5px; }
  body.thermal table.items .hide-t { display: none; }
  body.thermal table.items td { padding: 3px 2px; font-size: 9px; }
  body.thermal .totals td { font-size: 9.5px; padding: 2px 4px; }
  body.thermal .totals tr.grand td { background: #000; }
  body.thermal footer { position: static; margin-top: 8px; border-top: 1px dashed #000; }
  body.thermal .signs { margin-top: 20px; }
  body.thermal .terms { display: none; }

  /* ---------- Print Rules ---------- */
  @page { size: A4 portrait; margin: 6mm; }
  @media print {
    body { background: #fff !important; }
    .toolbar { display: none !important; }
    .sheet { margin: 0 auto !important; box-shadow: none !important; border-top: 4px solid var(--gold) !important; width: 100% !important; min-height: 0 !important; }
    body.thermal { font-size: 9px; }
    body.thermal .sheet { width: 80mm !important; margin: 0 auto !important; padding: 2mm !important; border: 0 !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  }
</style>
</head>
<body class="{{ $isThermal ? 'thermal' : '' }}">

@if (request('format') === 'html' || request('view') === 'html' || !request()->has('download'))
<div class="toolbar">
  <button onclick="window.print()">&#128438; প্রিন্ট করুন</button>
  <button class="alt {{ !$isThermal ? 'active-btn' : '' }}" onclick="switchSize('a4')">📄 A4 সাইজ</button>
  <button class="alt {{ $isThermal ? 'active-btn' : '' }}" onclick="switchSize('thermal')">🧾 থার্মাল ৮০ মি.মি.</button>
  <a href="{{ request()->fullUrlWithQuery(['download' => 1, 'format' => null, 'print' => null]) }}" class="alt">&#128190; PDF ডাউনলোড</a>
  <a href="{{ request()->fullUrlWithQuery(['template' => 'demo1']) }}" class="alt">&#127912; ডিজাইন ১ (ঐতিহ্যবাহী)</a>
  <button class="btn-close" onclick="window.close()">✕ বন্ধ করুন</button>
</div>
<script>
  function switchSize(size) {
    if (size === 'thermal') {
      document.body.classList.add('thermal');
    } else {
      document.body.classList.remove('thermal');
    }
  }
  if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', function() {
      setTimeout(function() { window.print(); }, 400);
    });
  }
</script>
@endif

<div class="sheet">

  <header>
    <div class="brand">
      <div class="logo">
        @if ($shopLogo)
          <img src="{{ $shopLogo }}" alt="">
        @else
          {{ mb_substr($shop['shop_name'], 0, 1) }}
        @endif
      </div>
      <div>
        <h1>{{ $shop['shop_name'] }}</h1>
        @if ($shop['shop_address'])
          <p>{{ $shop['shop_address'] }}</p>
        @endif
        <p>
          @if ($shop['shop_phone'])
            ফোন: {{ $shop['shop_phone'] }}
          @endif
        </p>
      </div>
    </div>
    <div class="doc-title">
      <h2>{{ $m('saleMemo') }}</h2>
      @if ($invoice['is_void'])
        <div class="void-stamp">
          {{ $labels['voided']['bn'] }}
          @if ($invoice['void_reason'])
            &mdash; {{ $invoice['void_reason'] }}
          @endif
        </div>
      @else
        <span class="badge">{{ $labels['duplicate']['en'] ?? 'গ্রাহক কপি' }}</span>
      @endif
    </div>
  </header>

  <section class="meta">
    <div class="box">
      <h3>{{ $labels['customer']['bn'] }}র তথ্য</h3>
      <table>
        <tr><td>নাম</td><td><strong>{{ $invoice['customer_name'] }}</strong></td></tr>
        @if ($invoice['customer_phone'])
          <tr><td>মোবাইল</td><td>{{ $invoice['customer_phone'] }}</td></tr>
        @endif
        @if ($invoice['customer_address'])
          <tr><td>ঠিকানা</td><td>{{ $invoice['customer_address'] }}</td></tr>
        @endif
        @if ($invoice['customer_code'])
          <tr><td>কোড</td><td>{{ $invoice['customer_code'] }}</td></tr>
        @endif
      </table>
    </div>
    <div class="box">
      <h3>চালানের তথ্য</h3>
      <table>
        <tr><td>চালান নং</td><td><strong>{{ $invoice['invoice_no'] }}</strong></td></tr>
        <tr><td>তারিখ</td><td>{{ $invoice['date'] }}</td></tr>
        @if ($invoice['seller_name'])
          <tr><td>বিক্রেতা</td><td>{{ $invoice['seller_name'] }}</td></tr>
        @endif
        @if ($payments)
          <tr>
            <td>পরিশোধ</td>
            <td>
              {{ implode(', ', array_map(fn($p) => $p['method_label'], $payments)) }}
            </td>
          </tr>
        @endif
      </table>
    </div>
  </section>

  @if (!empty($latestRates))
    <div class="rate-strip">
      <span>আজকের স্বর্ণের দর (প্রতি গ্রাম):</span>
      @foreach ([24, 22, 21, 18] as $k)
        @if (isset($latestRates[$k]))
          <span>{{ $k }} ক্যারেট: <strong>{{ $latestRates[$k] }}</strong></span>
        @endif
      @endforeach
    </div>
  @endif

  <table class="items">
    <thead>
      <tr>
        <th class="c" style="width:24px">#</th>
        <th>{{ $labels['description']['bn'] }}</th>
        <th class="hide-t">{{ $labels['karat']['bn'] }}</th>
        <th class="r">{{ $labels['weight']['bn'] }} ({{ $weightUnitLabel }})</th>
        <th class="r hide-t">{{ $labels['ratePerGram']['bn'] }}</th>
        <th class="r hide-t">{{ $labels['making']['bn'] }}</th>
        <th class="r hide-t">{{ $labels['stone']['bn'] ?? 'পাথর' }}</th>
        <th class="r">{{ $labels['lineTotal']['bn'] }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($lines as $line)
        <tr>
          <td class="c">{{ $line['serial'] }}</td>
          <td>
            <strong>{{ $line['name'] }}</strong>
            <span class="sub">ট্যাগ: {{ $line['tag_no'] ?: $labels['handwrittenTag']['bn'] }} &middot; স্বর্ণের মূল্য: {{ $money($line['gold_value']) }}</span>
          </td>
          <td class="hide-t">{{ $line['karat'] }}K</td>
          <td class="r">{{ $line['weight']['value'] }}</td>
          <td class="r hide-t">{{ $money($line['rate']) }}</td>
          <td class="r hide-t">{{ $money($line['making']) }}</td>
          <td class="r hide-t">{{ (float) $line['stone_price'] > 0 ? $money($line['stone_price']) : '০' }}</td>
          <td class="r"><strong>{{ $money($line['line_total']) }}</strong></td>
        </tr>
      @empty
        <tr><td colspan="8" class="c">কোনো আইটেম নেই</td></tr>
      @endforelse
      <tr class="summary-row">
        <td colspan="3" class="hide-t"><strong>মোট ওজন</strong></td>
        <td colspan="2" class="r"><strong>{{ $weight_summary['total_grams'] }} গ্রাম</strong></td>
        <td colspan="3" class="r">
          {{ $weight_summary['total_vori'] }} ভরি / {{ $weight_summary['total_ana'] }} আনা
        </td>
      </tr>
    </tbody>
  </table>

  <section class="bottom">
    <div>
      <div class="words"><strong>{{ $labels['amountInWords']['bn'] }}:</strong> {{ $totals['in_words'] }}</div>
      @if ((float) $totals['due'] > 0)
        <div class="words" style="color: var(--red);"><strong>বাকি (কথায়):</strong> {{ $totals['due_in_words'] }}</div>
      @endif

      @if ($exchanges)
        <div class="box" style="margin-top: 8px;">
          <h3>{{ $labels['exchangeHeading']['bn'] }}</h3>
          <table>
            @foreach ($exchanges as $exchange)
              <tr>
                <td>{{ $exchange['description'] }} ({{ $exchange['karat'] }}K)</td>
                <td class="r">{{ $exchange['weight']['value'] }} {{ $weightUnitLabel }}</td>
                <td class="r"><strong>- {{ $money($exchange['amount']) }}</strong></td>
              </tr>
            @endforeach
          </table>
        </div>
      @endif

      @if ($payments)
        <div class="box" style="margin-top: 8px;">
          <h3>পরিশোধের বিবরণ</h3>
          <table>
            @foreach ($payments as $payment)
              <tr>
                <td>{{ $payment['method_label'] }} @if ($payment['reference']) ({{ $payment['reference'] }}) @endif</td>
                <td class="r"><strong>{{ $money($payment['amount']) }}</strong></td>
              </tr>
            @endforeach
          </table>
        </div>
      @endif
    </div>

    <div class="totals">
      <table>
        <tr>
          <td>{{ $labels['subtotal']['bn'] }}</td>
          <td>{{ $money($totals['subtotal']) }}</td>
        </tr>
        @if ((float) $totals['discount'] > 0)
          <tr>
            <td>{{ $labels['discount']['bn'] }}</td>
            <td style="color: var(--red);">- {{ $money($totals['discount']) }}</td>
          </tr>
        @endif
        @if ((float) $totals['vat'] > 0)
          <tr>
            <td>{{ $labels['vat']['bn'] }} ({{ $totals['vat_percentage'] }}%)</td>
            <td>+ {{ $money($totals['vat']) }}</td>
          </tr>
        @endif
        @if ((float) $totals['exchange_amount'] > 0)
          <tr>
            <td>{{ $labels['exchange']['bn'] }}</td>
            <td style="color: var(--red);">- {{ $money($totals['exchange_amount']) }}</td>
          </tr>
        @endif
        <tr class="grand">
          <td>{{ $labels['grandTotal']['bn'] }}</td>
          <td>{{ $money($totals['total']) }}</td>
        </tr>
        <tr>
          <td>{{ $labels['paid']['bn'] }}</td>
          <td>{{ $money($totals['paid']) }}</td>
        </tr>
        <tr class="due">
          <td>{{ $labels['due']['bn'] }}</td>
          <td>{{ $money($totals['due']) }}</td>
        </tr>
      </table>
    </div>
  </section>

  <section class="terms">
    <h4>শর্তাবলি</h4>
    <ol>
      <li>বিক্রীত স্বর্ণালংকার প্রযোজ্য ক্ষয় কর্তন সাপেক্ষে সেদিনের বাজারদরে পরিবর্তন বা বিনিময় করা যাবে।</li>
      <li>কাউন্টার ত্যাগের পূর্বে ওজন ও ক্যারেট সঠিকভাবে মিলিয়ে নিন।</li>
      <li>ভবিষ্যতে যেকোনো বিনিময় বা বিক্রয়ের জন্য মূল চালানটি সঙ্গে আনুন।</li>
    </ol>
  </section>

  <div class="signs">
    <div>{{ $labels['customerSign']['bn'] }}</div>
    <div>{{ $labels['sellerSign']['bn'] }}</div>
  </div>

  <footer>
    @if ($footer)
      {!! nl2br(e($footer)) !!} &middot;
    @endif
    আমাদের সঙ্গে কেনাকাটার জন্য ধন্যবাদ &middot; মুদ্রিত: {{ $printed_at }}
  </footer>
</div>

</body>
</html>
