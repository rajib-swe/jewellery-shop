@php
    /**
     * Print stylesheet for the 80mm thermal sale memo.
     *
     * Thermal printers have no colour ribbon fidelity worth relying on and very
     * little width, so the cash memo design collapses to a single column with
     * bold black rules instead of fills and gradients.
     */
@endphp
@include('pdf.partials.fonts')
<style>
    @page { margin: 2mm; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        width: 76mm;
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

    .kv td { padding: 1px 0; font-size: 8.5px; vertical-align: top; }
    .kv .k { width: 34%; }
    .kv .v { width: 66%; font-weight: bold; }

    .rule { border-bottom: 1px dashed #000000; }

    .items th {
        border-bottom: 1px solid #000000;
        font-size: 8px;
        padding: 2px 1px;
        text-align: right;
    }

    .items th:first-child, .items td:first-child { text-align: left; }
    .items td { padding: 2px 1px; font-size: 8.5px; vertical-align: top; }

    .items .tag { font-size: 7.5px; }

    .totals td { padding: 1px 0; font-size: 8.5px; }
    .totals .k { width: 58%; }
    .totals .v { width: 42%; text-align: right; }
    .totals .grand td { border-top: 1px solid #000000; font-size: 10px; font-weight: bold; padding-top: 2px; }
    .totals .due td { font-size: 10px; font-weight: bold; }

    .words { font-size: 7.5px; line-height: 1.5; }

    .section { font-size: 8px; font-weight: bold; border-bottom: 1px solid #000000; margin-top: 3px; padding-bottom: 1px; }

    .exchanges td, .payments td { padding: 1px 0; font-size: 8px; }

    .sign td {
        width: 33.33%;
        text-align: center;
        font-size: 7.5px;
        padding-top: 12px;
        border-top: 1px solid #000000;
    }

    .footer { font-size: 7px; line-height: 1.5; text-align: center; margin-top: 3px; }

    .void-stamp {
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        border: 1px solid #000000;
        margin: 2px 0;
    }
</style>
