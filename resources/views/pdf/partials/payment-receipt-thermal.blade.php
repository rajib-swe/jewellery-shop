@include('pdf.partials.fonts')
<style>
    @page { margin: 2mm; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'hind-siliguri', 'noto-sans-bengali', sans-serif;
        font-size: 8.5px;
        color: #000000;
    }

    table { border-collapse: collapse; width: 100%; }

    .center { text-align: center; }
    .num { text-align: right; }
    .bold { font-weight: bold; }

    .shop-name {
        font-family: 'baloo-da-2', 'hind-siliguri', sans-serif;
        font-size: 15px;
        font-weight: bold;
        line-height: 1.3;
    }

    .shop-line { font-size: 8px; line-height: 1.5; }

    .memo-title {
        margin: 3px 0;
        border-top: 1px solid #000000;
        border-bottom: 1px solid #000000;
        font-size: 10px;
        font-weight: bold;
        text-align: center;
        padding: 2px 0;
    }

    .kv td { padding: 1px 0; vertical-align: top; }
    .kv .k { width: 36%; }
    .kv .v { width: 64%; font-weight: bold; }

    .amount {
        margin: 4px 0;
        border: 1px solid #000000;
        padding: 4px 5px;
        font-size: 13px;
        font-weight: bold;
        text-align: center;
    }

    .words { font-size: 7.5px; line-height: 1.5; }

    .sign td {
        width: 33.33%;
        text-align: center;
        font-size: 7.5px;
        padding-top: 12px;
        border-top: 1px solid #000000;
    }

    .footer { font-size: 7px; line-height: 1.5; text-align: center; margin-top: 3px; }
</style>
