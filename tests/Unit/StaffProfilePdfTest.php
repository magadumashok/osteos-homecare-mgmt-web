<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FileStorageService;
use App\Services\StaffProfilePdfService;
use App\Services\TenantService;
use App\Support\SimplePdf;
use ReflectionMethod;
use Tests\TestCase;

class StaffProfilePdfTest extends TestCase
{
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 140, 200));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** The service with files read from $files (URL => bytes). */
    private function service(array $files): StaffProfilePdfService
    {
        $storage = $this->createMock(FileStorageService::class);
        $storage->method('read')->willReturnCallback(fn ($url) => $files[$url] ?? null);
        $tenants = $this->createMock(TenantService::class);
        $tenants->method('displayName')->willReturn('Osteos Homecare');

        return new StaffProfilePdfService($storage, $tenants);
    }

    private function staff(array $attributes = []): User
    {
        return new User([
            'name' => 'Jyoti Patil', 'username' => 'osncaretaker1', 'roles' => ['caretaker'],
            'experience' => '5 years, ICU and home care',
            'profile_image' => 'https://docs.test/photo.png', 'aadhaar_card' => 'https://docs.test/aadhaar.png',
            ...$attributes,
        ]);
    }

    private function build(StaffProfilePdfService $service, User $user): ?string
    {
        return (new ReflectionMethod($service, 'build'))->invoke($service, $user);
    }

    public function test_the_pdf_has_the_photo_aadhaar_name_and_experience(): void
    {
        $service = $this->service([
            'https://docs.test/photo.png' => $this->png(300, 400),
            'https://docs.test/aadhaar.png' => $this->png(860, 540),
        ]);

        $pdf = $this->build($service, $this->staff());

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertSame(2, substr_count($pdf, '/Subtype /Image'));
        $text = gzuncompress($this->stream($pdf));
        $this->assertStringContainsString('(Jyoti Patil)', $text);
        $this->assertStringContainsString('(5 years, ICU and home care)', $text);
        $this->assertStringContainsString('(Aadhaar card)', $text);
        $this->assertStringContainsString('(Osteos Homecare)', $text);
    }

    public function test_no_pdf_without_both_photo_and_aadhaar(): void
    {
        $service = $this->service([]);

        $this->assertFalse($service->hasSources($this->staff(['aadhaar_card' => null])));
        $this->assertFalse($service->hasSources($this->staff(['profile_image' => ''])));
        $this->assertNull($service->forWhatsApp($this->staff(['aadhaar_card' => null])));
        $this->assertNull($service->forWhatsApp(null));
        $this->assertTrue($service->hasSources($this->staff()));
    }

    public function test_an_aadhaar_pdf_or_unreadable_file_builds_nothing(): void
    {
        $service = $this->service([
            'https://docs.test/photo.png' => $this->png(300, 400),
            'https://docs.test/aadhaar.png' => "%PDF-1.7\n...",
        ]);

        $this->assertNull($this->build($service, $this->staff()));
    }

    public function test_simple_pdf_lists_every_object_in_its_xref(): void
    {
        $pdf = (new SimplePdf)->addPage()->text(50, 50, 'Hello (world)')->output();

        preg_match('/xref\n0 (\d+)\n/', $pdf, $m);
        $this->assertSame((int) $m[1] - 1, preg_match_all('/^\d+ 0 obj$/m', $pdf));
        $this->assertStringContainsString('(Hello \(world\))', gzuncompress($this->stream($pdf)));
    }

    /** The page content stream (the last stream in the file). */
    private function stream(string $pdf): string
    {
        preg_match_all('/FlateDecode \/Length (\d+) >>\nstream\n/', $pdf, $m, PREG_OFFSET_CAPTURE);
        $last = count($m[0]) - 1;
        $start = $m[0][$last][1] + strlen($m[0][$last][0]);

        return substr($pdf, $start, (int) $m[1][$last][0]);
    }
}
