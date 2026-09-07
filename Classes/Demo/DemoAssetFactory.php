<?php

declare(strict_types=1);

namespace BalatD\KernUx\Demo;

use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * Generates the files the demo content refers to.
 *
 * Generated rather than shipped. Binaries in a repository have to be licensed,
 * reviewed and carried forever, and placeholder photos are the kind of asset that
 * quietly acquires a licensing problem. A PNG, a WAV, a PDF and a WebVTT track can all
 * be written from a handful of bytes, so the demo gets real FAL references - real image
 * processing, real file sizes in the download list - without any of that.
 *
 * The images are deliberately abstract. A placeholder that looked like a photograph
 * would invite judging the design by the photo.
 */
final class DemoAssetFactory
{
    public const FOLDER = 'kern-ux-demo';

    private const WIDTH = 1600;
    private const HEIGHT = 900;

    /**
     * GD's signatures require a positive width and height, and a constant is only an int
     * as far as static analysis is concerned. Narrowed once here rather than at each call.
     */
    private const MIN_DIMENSION = 1;

    /**
     * Distinct hues so the images in a gallery are visibly different from one another,
     * which is what makes a wrong sort order or a duplicated reference obvious.
     *
     * @var array<string, array{int, int, int}>
     */
    private const TINTS = [
        'buergerbuero' => [19, 21, 37],
        'rathaus' => [30, 58, 95],
        'ausweis' => [14, 79, 90],
        'stadtpark' => [22, 74, 51],
        'baustelle' => [110, 62, 15],
    ];

    public function __construct(private readonly StorageRepository $storageRepository) {}

    /**
     * @param array<string, array<string, mixed>> $definitions Keyed by file key.
     * @return array<string, int> File key to sys_file uid.
     */
    public function create(array $definitions): array
    {
        $storage = $this->defaultStorage();
        $folder = $this->folder($storage);

        $uids = [];
        foreach ($definitions as $key => $definition) {
            $type = is_string($definition['type'] ?? null) ? $definition['type'] : 'image';
            $title = is_string($definition['title'] ?? null) ? $definition['title'] : $key;
            $alternative = is_string($definition['alternative'] ?? null) ? $definition['alternative'] : '';

            $file = $this->write($folder, $key, $type, $title);

            // Title and alternative text live in the file's metadata, not on the file
            // itself, and that is where the templates read them from - so a demo that
            // skipped this step would show every image with an empty alt.
            $metaData = ['title' => $title];
            if ($alternative !== '') {
                $metaData['alternative'] = $alternative;
            }
            $file->getMetaData()->add($metaData)->save();

            $uids[$key] = $file->getUid();
        }

        return $uids;
    }

    private function write(Folder $folder, string $key, string $type, string $title): File
    {
        [$extension, $contents] = match ($type) {
            'pdf' => ['pdf', $this->pdf($title)],
            'audio' => ['wav', $this->silentWav()],
            'captions' => ['vtt', $this->captions()],
            default => ['png', $this->image($key, $title)],
        };

        $name = $key . '.' . $extension;

        // Replaced rather than overwritten. Writing new bytes into the existing file
        // leaves its processed derivatives behind: they are outdated but still indexed,
        // and TYPO3 then hands out a ProcessedFile it has just marked deleted - every
        // f:image on that file dies with "File has been deleted." until the whole
        // _processed_ folder is purged by hand. Deleting the file first runs core's own
        // cleanup, which removes the derivatives and their index entries together.
        //
        // Safe because the caller has already removed the demo page tree, so nothing
        // references these files any more - and the folder is the demo's own.
        try {
            $existing = $folder->getFile($name);
            if ($existing instanceof File) {
                $existing->delete();
            }
        } catch (FileDoesNotExistException|FolderDoesNotExistException) {
            // Nothing to replace on a first install.
        }

        $file = $folder->createFile($name);
        $file->setContents($contents);

        return $file;
    }

    /**
     * A flat tint with a diagonal band and the key drawn on it. No TrueType font is
     * involved: GD's built-in font is tiny, so the label is drawn small and then scaled
     * up with nearest-neighbour, which stays legible where a smooth upscale would not.
     */
    private function image(string $key, string $title): string
    {
        $canvas = imagecreatetruecolor(max(self::MIN_DIMENSION, self::WIDTH), max(self::MIN_DIMENSION, self::HEIGHT));
        if ($canvas === false) {
            throw new \RuntimeException('GD could not allocate the demo image.', 1756200002);
        }

        [$r, $g, $b] = self::TINTS[$key] ?? [40, 44, 66];
        $background = (int)imagecolorallocate($canvas, $r, $g, $b);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $background);

        $band = (int)imagecolorallocate($canvas, min(255, $r + 42), min(255, $g + 42), min(255, $b + 42));
        imagefilledpolygon($canvas, [
            0, self::HEIGHT,
            0, (int)(self::HEIGHT * 0.55),
            self::WIDTH, (int)(self::HEIGHT * 0.2),
            self::WIDTH, self::HEIGHT,
        ], $band);

        $this->stampLabel($canvas, $title);

        try {
            ob_start();
            try {
                $written = imagepng($canvas, null, 6);
                $png = (string)ob_get_clean();
            } catch (\Throwable $throwable) {
                // ob_get_clean() has not run, so the buffer is still on the stack.
                ob_end_clean();
                throw $throwable;
            }
        } finally {
            imagedestroy($canvas);
        }

        // An empty string here used to be written to FAL as if it were a PNG, giving
        // the demo a file that every consumer would treat as a valid image.
        if ($written === false || $png === '') {
            throw new \RuntimeException('GD could not encode the demo image.', 1756200005);
        }

        return $png;
    }

    private function stampLabel(\GdImage $canvas, string $title): void
    {
        $font = 5;
        $scale = 4;
        $width = (int)imagefontwidth($font) * mb_strlen($title) + 16;
        $height = (int)imagefontheight($font) + 12;

        $label = imagecreatetruecolor(max(self::MIN_DIMENSION, $width), max(self::MIN_DIMENSION, $height));
        if ($label === false) {
            return;
        }
        $plate = (int)imagecolorallocate($label, 255, 255, 255);
        $ink = (int)imagecolorallocate($label, 20, 20, 30);
        imagefilledrectangle($label, 0, 0, $width, $height, $plate);
        // The built-in font is Latin-1, so anything outside it would come out as noise.
        imagestring($label, $font, 8, 6, (string)mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8'), $ink);

        $scaled = imagescale($label, $width * $scale, $height * $scale, IMG_NEAREST_NEIGHBOUR);
        imagedestroy($label);
        if ($scaled === false) {
            return;
        }

        imagecopy($canvas, $scaled, 60, self::HEIGHT - $height * $scale - 60, 0, 0, $width * $scale, $height * $scale);
        imagedestroy($scaled);
    }

    /**
     * One second of silence: 8 kHz, mono, 8 bit unsigned, whose zero point is 128.
     * Enough for the player to show a real duration and a working timeline.
     */
    private function silentWav(): string
    {
        $rate = 8000;
        $samples = str_repeat(chr(128), $rate);
        $dataSize = strlen($samples);

        return 'RIFF'
            . pack('V', 36 + $dataSize)
            . 'WAVEfmt '
            . pack('V', 16)          // size of the format chunk
            . pack('v', 1)           // PCM
            . pack('v', 1)           // channels
            . pack('V', $rate)
            . pack('V', $rate)       // byte rate: rate * channels * bits / 8
            . pack('v', 1)           // block align
            . pack('v', 8)           // bits per sample
            . 'data'
            . pack('V', $dataSize)
            . $samples;
    }

    private function captions(): string
    {
        return "WEBVTT\n\n"
            . "1\n00:00:00.000 --> 00:00:00.500\n"
            . "Das Bürgerbüro ist montags bis freitags von 8 bis 12 Uhr geöffnet.\n\n"
            . "2\n00:00:00.500 --> 00:00:01.000\n"
            . "Donnerstags zusätzlich von 14 bis 18 Uhr.\n";
    }

    /**
     * A single-page PDF, assembled by hand because the byte offsets in the xref table
     * have to match the real ones - a reader that repairs a broken table would still
     * report the file as damaged, which is not what a download demo should show.
     */
    private function pdf(string $title): string
    {
        $text = $this->pdfString($title);
        $stream = "BT /F1 16 Tf 72 780 Td ({$text}) Tj ET\n"
            . 'BT /F1 11 Tf 72 750 Td (' . $this->pdfString('Demo-Datei aus EXT:kern_ux, erzeugt beim Installieren der Demo-Inhalte.') . ") Tj ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                . '/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }

    /**
     * Parentheses and backslashes end a PDF string literal, so they have to be escaped;
     * WinAnsi is what the font declares, so the text is converted to it.
     */
    private function pdfString(string $value): string
    {
        $encoded = (string)mb_convert_encoding($value, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function defaultStorage(): ResourceStorage
    {
        $storage = $this->storageRepository->getDefaultStorage();
        if (!$storage instanceof ResourceStorage) {
            throw new \RuntimeException(
                'No default file storage exists. Run `typo3 setup` or create one in the backend first.',
                1756200003,
            );
        }

        return $storage;
    }

    private function folder(ResourceStorage $storage): Folder
    {
        if ($storage->hasFolder(self::FOLDER)) {
            return $storage->getFolder(self::FOLDER);
        }

        return $storage->createFolder(self::FOLDER);
    }
}
