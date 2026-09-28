@php
    $selectedTemplate = $template ?? ($shop['invoice_template'] ?? 'demo2');
@endphp

@if ($selectedTemplate === 'demo1')
    @include('pdf.sale-invoice-demo1')
@else
    @include('pdf.sale-invoice-demo2')
@endif
