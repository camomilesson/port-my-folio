<?php

use App\Actions\ValidateCvPdf;
use Illuminate\Validation\ValidationException;

function pdfWithPages(int $pages, bool $encrypted = false): string
{
    $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Count '.$pages.' /Kids ['.implode(' ', array_map(fn (int $page): string => ($page + 2).' 0 R', range(1, $pages))).'] >>'];
    for ($page = 0; $page < $pages; $page++) {
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>';
    }
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer << /Size '.count($offsets).' /Root 1 0 R'.($encrypted ? ' /Encrypt 1 0 R' : '')." >>\nstartxref\n".$xref."\n%%EOF\n";
}

it('accepts a twenty-page PDF without needing text extraction', function () {
    app(ValidateCvPdf::class)->handle(pdfWithPages(20));
    expect(true)->toBeTrue();
});

it('rejects CVs over twenty pages', function () {
    expect(fn () => app(ValidateCvPdf::class)->handle(pdfWithPages(21)))->toThrow(ValidationException::class, 'between 1 and 20 pages');
});

it('rejects encrypted or malformed PDFs', function (string $bytes) {
    expect(fn () => app(ValidateCvPdf::class)->handle($bytes))->toThrow(ValidationException::class, 'valid, unencrypted PDF');
})->with([
    'encrypted' => fn () => pdfWithPages(1, true),
    'malformed' => "%PDF-1.4\ngarbage\n%%EOF\n",
]);
