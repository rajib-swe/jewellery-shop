@php
    /**
     * Font declarations shared by every printable document.
     *
     * DomPDF 3 resolves custom fonts from `@font-face` rules rather than a
     * configuration map, so the bundled Bengali faces are declared here once and
     * included by each paper-size stylesheet. Remote font loading stays disabled.
     *
     * URLs are emitted with forward slashes: a backslash is an escape character
     * inside a CSS string and would stop DomPDF resolving the font file, which
     * in turn leaves Bangla text with fallback metrics and a blown-up line box.
     */
    $fontDir = str_replace('\\', '/', resource_path('fonts'));

    $fonts = [
        'hind-siliguri' => [
            'normal' => 'HindSiliguri-Regular',
            'bold' => 'HindSiliguri-Bold',
        ],
        'baloo-da-2' => [
            'normal' => 'BalooDa2-Bold',
            'bold' => 'BalooDa2-Bold',
        ],
        'noto-sans-bengali' => [
            'normal' => 'NotoSansBengali-Regular',
            'bold' => 'NotoSansBengali-Bold',
        ],
    ];

    $faces = [];

    foreach ($fonts as $family => $variants) {
        foreach ($variants as $weight => $file) {
            $path = "{$fontDir}/{$file}.ttf";

            if (is_file($path)) {
                $faces[] = "@font-face {\n    font-family: '{$family}';\n    font-style: normal;\n    font-weight: {$weight};\n    src: url('{$path}') format('truetype');\n}";
            }
        }
    }
@endphp
<style>
    {!! implode("\n", $faces) !!}
</style>

