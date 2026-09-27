<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class TmpMetricsTest extends TestCase
{
    public function test_font_line_boxes(): void
    {
        $dir = str_replace('\\', '/', resource_path('fonts'));

        $fonts = [
            'hs' => 'HindSiliguri-Regular',
            'hsb' => 'HindSiliguri-Bold',
            'baloo' => 'BalooDa2-Bold',
            'noto' => 'NotoSansBengali-Regular',
        ];

        $faces = '';

        foreach ($fonts as $family => $file) {
            $faces .= "@font-face{font-family:{$family};src:url('{$dir}/{$file}.ttf') format('truetype')}\n";
        }

        foreach ($fonts as $family => $file) {
            foreach ([8.5, 15] as $size) {
                $html = '<html><head><style>'.$faces
                    ."body{font-family:{$family};font-size:{$size}px;margin:0;line-height:normal;width:76mm}"
                    .'</style></head><body><p>আনোয়ার হোসেন টাকা</p></body></html>';

                $pdf = Pdf::loadHTML($html)->setPaper([0, 0, 80, 60]);
                $pdf->getDomPDF()->render();
                $canvas = $pdf->getDomPDF()->getCanvas();

                fwrite(STDERR, sprintf(
                    "  %-6s %4.1fpx  font_height=%5.2fpt baseline=%5.2fpt pages=%d\n",
                    $family,
                    $size,
                    $canvas->get_font_height($family, $size),
                    $canvas->get_font_baseline($family, $size),
                    $canvas->get_page_count(),
                ));
            }
        }

        $this->assertTrue(true);
    }
}
