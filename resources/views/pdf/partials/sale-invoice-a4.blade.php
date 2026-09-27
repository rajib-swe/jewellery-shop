@php
    /**
     * Shared print stylesheet for the A4 sale memo.
     *
     * DomPDF ignores CSS grid, flexbox, gradients and box shadows, so the cash
     * memo design is rebuilt from nested tables, solid fills and borders.
     */
    $ink = '#2a1608';
    $maroon = '#7a1030';
    $red = '#c62828';
    $gold = '#b8860b';
    $goldLight = '#fdf1c4';
    $cream = '#fbf3df';
    $line = '#d9c68a';
@endphp
@include('pdf.partials.fonts')
<style>
    @page { margin: 10mm 8mm; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'hind-siliguri', 'noto-sans-bengali', sans-serif;
        font-size: 10.5px;
        color: {{ $ink }};
    }

    table { border-collapse: collapse; width: 100%; }

    .frame { border: 2px solid {{ $gold }}; padding: 0; }

    .frame-inner { border: 1px solid {{ $goldLight }}; padding: 8px 10px; }

    .head { text-align: center; }

    .ribbon {
        display: inline-block;
        background-color: {{ $red }};
        color: #ffffff;
        font-size: 12px;
        font-weight: bold;
        padding: 4px 26px;
        border: 1px solid {{ $maroon }};
    }

    .shop-name {
        font-family: 'baloo-da-2', 'hind-siliguri', sans-serif;
        font-size: 30px;
        font-weight: bold;
        color: {{ $gold }};
        line-height: 1.3;
        margin-top: 6px;
    }

    .shop-tagline { font-size: 10px; color: #4a3216; line-height: 1.6; margin-top: 4px; }

    .shop-contact { font-size: 10px; color: {{ $maroon }}; margin-top: 3px; font-weight: bold; }

    .shop-logo { height: 46px; }

    .meta { margin-top: 8px; }

    .meta table { border: 1px solid {{ $line }}; }

    .meta td {
        border: 1px solid {{ $line }};
        padding: 5px 7px;
        font-size: 10.5px;
        vertical-align: top;
        width: 25%;
    }

    .meta .label { color: {{ $maroon }}; font-weight: bold; white-space: nowrap; width: 1%; }

    .meta .value { background-color: #fffdf6; }

    .en-gloss { font-size: 8px; color: #7a6a48; }

    .void-stamp {
        margin-top: 6px;
        text-align: center;
        border: 2px solid {{ $red }};
        color: {{ $red }};
        font-size: 13px;
        font-weight: bold;
        padding: 3px 0;
        letter-spacing: 1px;
    }

    .items { margin-top: 8px; }

    .items th {
        background-color: {{ $red }};
        color: #ffffff;
        border: 1px solid #9d1f1f;
        padding: 5px 4px;
        font-size: 10px;
        line-height: 1.3;
    }

    .items td {
        border: 1px solid {{ $line }};
        padding: 4px 4px;
        font-size: 10px;
        vertical-align: top;
    }

    .items .num { text-align: right; }
    .items .ctr { text-align: center; }
    .items .tag { font-size: 8.5px; color: #7a6a48; }

    .section-title {
        margin-top: 8px;
        background-color: {{ $goldLight }};
        border: 1px solid {{ $line }};
        border-bottom: none;
        padding: 3px 6px;
        font-weight: bold;
        color: {{ $maroon }};
        font-size: 10.5px;
    }

    .totals { margin-top: 8px; }

    .totals td {
        border: 1px solid {{ $line }};
        padding: 4px 6px;
        font-size: 10.5px;
    }

    .totals .label { width: 34%; font-weight: bold; color: {{ $maroon }}; }
    .totals .num { text-align: right; width: 16%; }
    .totals .grand { font-size: 12px; font-weight: bold; color: {{ $red }}; }
    .totals .due { font-size: 12px; font-weight: bold; color: {{ $ink }}; }
    .totals .words { font-size: 9.5px; color: #4a3d28; padding: 3px 6px; }

    .payments th, .exchanges th, .payments td, .exchanges td {
        border: 1px solid {{ $line }};
        padding: 4px 5px;
        font-size: 10px;
    }

    .payments th, .exchanges th { background-color: {{ $cream }}; color: {{ $maroon }}; }

    .sign-row { margin-top: 14px; }

    .sign-row td { width: 33.33%; text-align: center; vertical-align: bottom; }

    .sign-line { border-top: 1px solid {{ $gold }}; padding-top: 3px; font-size: 9.5px; color: #4a3216; }

    .thanks {
        font-family: 'baloo-da-2', 'hind-siliguri', sans-serif;
        font-size: 13px;
        color: {{ $maroon }};
        font-weight: bold;
    }

    .footer-note {
        margin-top: 8px;
        border-top: 1px dashed {{ $line }};
        padding-top: 5px;
        font-size: 8.5px;
        line-height: 1.7;
        color: #4a3d28;
        text-align: justify;
    }

    .banner {
        margin-top: 6px;
        background-color: {{ $maroon }};
        color: {{ $goldLight }};
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        padding: 5px;
    }

    .printed { margin-top: 4px; font-size: 8px; color: #8a7a58; text-align: center; }
</style>
