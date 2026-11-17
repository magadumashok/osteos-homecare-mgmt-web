<?php

namespace App\Support;

/**
 * A minimal PDF writer: A4 pages with text (Helvetica, regular and bold),
 * filled rectangles and JPEG images. Enough for one-page documents built on
 * the server without a PDF library. Coordinates are in points from the
 * top-left corner (A4 is 595 x 842).
 *
 * Text uses the standard WinAnsi encoding, so characters outside Latin-1
 * are transliterated (or dropped) — keep generated text in English.
 */
class SimplePdf
{
    public const WIDTH = 595.28;

    public const HEIGHT = 841.89;

    /** @var list<string> content stream of each page */
    private array $pages = [];

    /** @var list<array{data: string, width: int, height: int}> */
    private array $images = [];

    /** @var list<list<int>> image indexes used on each page */
    private array $pageImages = [];

    public function addPage(): self
    {
        $this->pages[] = '';
        $this->pageImages[] = [];

        return $this;
    }

    /** Text with its top-left corner at ($x, $y). */
    public function text(float $x, float $y, string $text, float $size = 11, bool $bold = false, array $rgb = [0, 0, 0]): self
    {
        $font = $bold ? 'F2' : 'F1';
        $baseline = self::HEIGHT - $y - $size;
        $this->write(sprintf(
            "BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $this->color($rgb), $font, $size, $x, $baseline, $this->escape($text),
        ));

        return $this;
    }

    /**
     * Text wrapped to $width, line by line from ($x, $y). Returns the y
     * below the last line.
     */
    public function paragraph(float $x, float $y, float $width, string $text, float $size = 11, bool $bold = false, array $rgb = [0, 0, 0]): float
    {
        // Helvetica averages about half the font size per character.
        $perLine = max(10, (int) floor($width / ($size * 0.5)));
        foreach (preg_split('/\R/', $text) as $paragraph) {
            foreach (explode("\n", wordwrap($paragraph, $perLine, "\n", true)) as $line) {
                $this->text($x, $y, $line, $size, $bold, $rgb);
                $y += $size * 1.4;
            }
        }

        return $y;
    }

    public function rect(float $x, float $y, float $width, float $height, array $rgb): self
    {
        $this->write(sprintf(
            "%s rg %.2F %.2F %.2F %.2F re f\n",
            $this->color($rgb), $x, self::HEIGHT - $y - $height, $width, $height,
        ));

        return $this;
    }

    /** A JPEG drawn into the box at ($x, $y), scaled to fit and centred. Returns the drawn height. */
    public function jpeg(string $jpeg, int $pixelWidth, int $pixelHeight, float $x, float $y, float $maxWidth, float $maxHeight): float
    {
        if (! $this->pages) {
            $this->addPage();
        }
        $scale = min($maxWidth / $pixelWidth, $maxHeight / $pixelHeight);
        $width = $pixelWidth * $scale;
        $height = $pixelHeight * $scale;
        $left = $x + ($maxWidth - $width) / 2;

        $this->images[] = ['data' => $jpeg, 'width' => $pixelWidth, 'height' => $pixelHeight];
        $index = count($this->images) - 1;
        $this->pageImages[count($this->pages) - 1][] = $index;

        $this->write(sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q\n",
            $width, $height, $left, self::HEIGHT - $y - $height, $index,
        ));

        return $height;
    }

    public function output(): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        // 2 = page tree, filled in below once the page objects are numbered.
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $next = 5;
        $imageObjects = [];
        foreach ($this->images as $i => $image) {
            $imageObjects[$i] = $next;
            $objects[$next++] = [
                sprintf(
                    '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>',
                    $image['width'], $image['height'], strlen($image['data']),
                ),
                $image['data'],
            ];
        }

        $pageRefs = [];
        foreach ($this->pages as $p => $content) {
            $stream = gzcompress($content);
            $contentObject = $next++;
            $objects[$contentObject] = [sprintf('<< /Filter /FlateDecode /Length %d >>', strlen($stream)), $stream];

            $xobjects = implode(' ', array_map(fn ($i) => "/Im{$i} {$imageObjects[$i]} 0 R", $this->pageImages[$p]));
            $pageObject = $next++;
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << %s >> >> /Contents %d 0 R >>',
                self::WIDTH, self::HEIGHT, $xobjects, $contentObject,
            );
            $pageRefs[] = "{$pageObject} 0 R";
        }
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $pageRefs), count($pageRefs));
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n";
            $pdf .= is_array($object) ? "{$object[0]}\nstream\n{$object[1]}\nendstream" : $object;
            $pdf .= "\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function write(string $operators): void
    {
        if (! $this->pages) {
            $this->addPage();
        }
        $this->pages[count($this->pages) - 1] .= $operators;
    }

    private function color(array $rgb): string
    {
        return vsprintf('%.3F %.3F %.3F', array_map(fn ($c) => $c / 255, $rgb));
    }

    private function escape(string $text): string
    {
        $latin = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);

        return strtr($latin === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $latin, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }
}
