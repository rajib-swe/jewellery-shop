@php
    /**
     * Print stylesheet for the A4 payment receipt.
     */
    $ink = '#2a1608';
    $maroon = '#7a1030';
    $gold = '#b8860b';
    $goldLight = '#fdf1c4';
    $line = '#d9c68a';
@endphp
@include('pdf.partials.fonts')
<style>
    @page { margin: 16mm 18mm; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'hind-siliguri', 'noto-sans-bengali', sans-serif;
        font-size: 11px;
        color: {{ $ink }};
    }

    table { border-collapse: collapse; width: 100%; }

    .center { text-align: center; }
    .num { text-align: right; }

    .shop-name {
        font-family: 'baloo-da-2', 'hind-siliguri', sans-serif;
        font-size: 24px;
        font-weight: bold;
        color: {{ $gold }};
        line-height: 1.3;
    }

    .shop-line { font-size: 10px; color: #4a3216; line-height: 1.6; }

    .title {
        margin: 10px 0;
        text-align: center;
        border-top: 2px solid {{ $gold }};
        border-bottom: 2px solid {{ $gold }};
        padding: 5px 0;
        font-size: 14px;
        font-weight: bold;
        color: {{ $maroon }};
    }

    .en-gloss { font-size: 8.5px; color: #7a6a48; }

    .panel { border: 1px solid {{ $line }}; }

    .panel td {
        border: 1px solid {{ $line }};
        padding: 5px 7px;
        vertical-align: top;
    }

    .panel .label { width: 26%; color: {{ $maroon }}; font-weight: bold; white-space: nowrap; }
    .panel .value { width: 24%; background-color: #fffdf6; }

    .amount {
        margin: 10px 0;
        background-color: {{ $goldLight }};
        border: 1px solid {{ $gold }};
        padding: 8px 10px;
        font-size: 18px;
        font-weight: bold;
        color: {{ $maroon }};
    }

    .words { font-size: 10px; color: #4a3d28; margin-bottom: 10px; }

    .sign td {
        width: 33.33%;
        text-align: center;
        vertical-align: bottom;
        padding-top: 26px;
    }

    .sign .line { border-top: 1px solid {{ $gold }}; padding-top: 3px; font-size: 10px; color: #4a3216; }

    .footer-note {
        margin-top: 14px;
        border-top: 1px dashed {{ $line }};
        padding-top: 5px;
        font-size: 9px;
        line-height: 1.7;
        color: #4a3d28;
        text-align: justify;
    }

    .printed { margin-top: 6px; font-size: 8.5px; color: #8a7a58; text-align: center; }
</style>
