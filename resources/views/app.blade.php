<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>

    <meta name="description" content="Jewellery shop operations: gold rates, inventory, sales, pawns, and purchases.">
    <meta name="theme-color" content="#8a6a32">
    <meta name="color-scheme" content="light">
    <meta name="format-detection" content="telephone=no">

    <link rel="manifest" href="/build/manifest.webmanifest">
    <link rel="icon" href="/icons/favicon.ico" sizes="48x48">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Jewellery Shop">

    @vite(['resources/css/app.css', 'resources/js/main.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
