<?php

namespace Tests\Unit;

use App\Support\PdfGuard;
use PHPUnit\Framework\TestCase;

class PdfGuardTest extends TestCase
{
    private function pdf(string $body): string
    {
        return "%PDF-1.7\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R {$body} >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
    }

    public function test_clean_pdf_passes(): void
    {
        $this->assertNull(PdfGuard::inspect($this->pdf('')));
    }

    public function test_open_action_javascript_is_rejected(): void
    {
        $this->assertNotNull(PdfGuard::inspect($this->pdf('/OpenAction << /S /JavaScript /JS (app.alert(1)) >>')));
    }

    public function test_obfuscated_names_are_rejected(): void
    {
        $this->assertNotNull(PdfGuard::inspect($this->pdf('/Open#41ction << /S /J#61vaScript /J#53 (x) >>')));
    }

    public function test_javascript_hidden_in_a_compressed_object_stream_is_rejected(): void
    {
        $inner = '3 0 << /S /JavaScript /JS (app.alert(1)) >>';
        $stream = gzcompress($inner);
        $pdf = "%PDF-1.7\n4 0 obj\n<< /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream\nendobj\n%%EOF";

        $this->assertNotNull(PdfGuard::inspect($pdf));
    }

    public function test_undecodable_stream_fails_closed(): void
    {
        $pdf = "%PDF-1.7\n4 0 obj\n<< /Filter /FlateDecode /Length 5 >>\nstream\nnotzl\nendstream\nendobj\n%%EOF";

        $this->assertNotNull(PdfGuard::inspect($pdf));
    }
}
