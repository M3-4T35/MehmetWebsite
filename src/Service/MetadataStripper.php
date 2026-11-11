<?php

namespace App\Service;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Removes embedded metadata (camera model, GPS position, dates, software, comments...)
 * from uploaded photos and videos, without re-encoding them.
 *
 * - JPEG, PNG, WebP: the metadata blocks are dropped in pure PHP; pixels are untouched.
 *   For JPEG the EXIF orientation is kept, otherwise phone photos would display rotated.
 * - Videos: remuxed by ffmpeg with stream copy (no quality loss), keeping only video and audio.
 * - Other files (GIF, PDF...) are left as they are.
 *
 * Every method fails loudly rather than leaving a file with its metadata in place.
 */
final class MetadataStripper
{
    private const VIDEO_TIMEOUT = 600;

    public function strip(string $path): void
    {
        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($path) ?: '';

        match (true) {
            'image/jpeg' === $mimeType => $this->rewrite($path, $this->stripJpeg(...)),
            'image/png' === $mimeType => $this->rewrite($path, $this->stripPng(...)),
            'image/webp' === $mimeType => $this->rewrite($path, $this->stripWebp(...)),
            str_starts_with($mimeType, 'video/'), 'application/ogg' === $mimeType => $this->stripVideo($path),
            default => null,
        };
    }

    /**
     * Runs a stripper that reads $path and writes the cleaned copy, then swaps it in place.
     *
     * @param callable(string, resource, resource): void $stripper
     */
    private function rewrite(string $path, callable $stripper): void
    {
        $temporary = $path.'.strip';
        $in = fopen($path, 'rb');
        $out = fopen($temporary, 'wb');
        if (!$in || !$out) {
            throw new \RuntimeException(sprintf('Impossible d\'ouvrir "%s" pour en retirer les métadonnées.', basename($path)));
        }

        try {
            $stripper($path, $in, $out);
        } catch (\Throwable $e) {
            fclose($in);
            fclose($out);
            @unlink($temporary);

            throw $e;
        }
        fclose($in);
        fclose($out);

        $this->replace($temporary, $path);
    }

    private function replace(string $temporary, string $path): void
    {
        @chmod($temporary, fileperms($path) & 0777);
        if (!rename($temporary, $path)) {
            @unlink($temporary);

            throw new \RuntimeException(sprintf('Impossible de remplacer "%s" par sa version sans métadonnées.', basename($path)));
        }
    }

    /**
     * @param resource $in
     * @param resource $out
     */
    private function stripJpeg(string $path, $in, $out): void
    {
        $exif = \function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = \is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $orientationPending = $orientation >= 2 && $orientation <= 8;

        if ("\xFF\xD8" !== $this->read($in, 2)) {
            throw $this->malformed($path);
        }
        fwrite($out, "\xFF\xD8");

        while (true) {
            if ("\xFF" !== $this->read($in, 1)) {
                throw $this->malformed($path);
            }
            do {
                $marker = \ord($this->read($in, 1));
            } while (0xFF === $marker); // fill bytes

            // The orientation goes right after the JFIF header (APP0), before anything else
            if ($orientationPending && 0xE0 !== $marker) {
                fwrite($out, $this->orientationSegment($orientation));
                $orientationPending = false;
            }

            if (0xDA === $marker) {
                // Start of scan: the rest of the file is image data
                fwrite($out, "\xFF\xDA");
                stream_copy_to_stream($in, $out);

                return;
            }
            if (0xD9 === $marker) {
                fwrite($out, "\xFF\xD9");

                return;
            }
            if (0x01 === $marker || ($marker >= 0xD0 && $marker <= 0xD7)) {
                fwrite($out, "\xFF".\chr($marker));
                continue;
            }

            $lengthBytes = $this->read($in, 2);
            $length = unpack('n', $lengthBytes)[1];
            if ($length < 2) {
                throw $this->malformed($path);
            }
            $payload = $length > 2 ? $this->read($in, $length - 2) : '';

            $keep = match (true) {
                0xE0 === $marker, 0xEE === $marker => true,                    // JFIF, Adobe colour transform
                0xE2 === $marker => str_starts_with($payload, "ICC_PROFILE\0"), // colour profile only
                $marker >= 0xE1 && $marker <= 0xEF => false,                    // EXIF, XMP, IPTC, maker data
                0xFE === $marker => false,                                      // comment
                default => true,
            };
            if ($keep) {
                fwrite($out, "\xFF".\chr($marker).$lengthBytes.$payload);
            }
        }
    }

    /**
     * A minimal EXIF block holding nothing but the orientation.
     */
    private function orientationSegment(int $orientation): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"          // big-endian TIFF header, first IFD at offset 8
            ."\x00\x01"                                // one entry
            ."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00" // Orientation, SHORT
            ."\x00\x00\x00\x00";                       // no next IFD
        $payload = "Exif\x00\x00".$tiff;

        return "\xFF\xE1".pack('n', \strlen($payload) + 2).$payload;
    }

    /**
     * @param resource $in
     * @param resource $out
     */
    private function stripPng(string $path, $in, $out): void
    {
        $signature = "\x89PNG\r\n\x1A\n";
        if ($signature !== $this->read($in, 8)) {
            throw $this->malformed($path);
        }
        fwrite($out, $signature);

        while (true) {
            $header = fread($in, 8);
            if ('' === $header || false === $header) {
                return;
            }
            if (8 !== \strlen($header)) {
                throw $this->malformed($path);
            }
            ['length' => $length, 'type' => $type] = unpack('Nlength/a4type', $header);
            $size = $length + 4; // data + CRC

            // Text, EXIF and "last modified" chunks
            if (\in_array($type, ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'], true)) {
                if (0 !== fseek($in, $size, \SEEK_CUR)) {
                    throw $this->malformed($path);
                }
                continue;
            }

            fwrite($out, $header);
            if ($size !== stream_copy_to_stream($in, $out, $size)) {
                throw $this->malformed($path);
            }
            if ('IEND' === $type) {
                return;
            }
        }
    }

    /**
     * @param resource $in
     * @param resource $out
     */
    private function stripWebp(string $path, $in, $out): void
    {
        $header = $this->read($in, 12);
        if (!str_starts_with($header, 'RIFF') || 'WEBP' !== substr($header, 8, 4)) {
            throw $this->malformed($path);
        }
        fwrite($out, $header);

        while (true) {
            $chunkHeader = fread($in, 8);
            if ('' === $chunkHeader || false === $chunkHeader) {
                break;
            }
            if (8 !== \strlen($chunkHeader)) {
                throw $this->malformed($path);
            }
            ['type' => $type, 'size' => $size] = unpack('a4type/Vsize', $chunkHeader);
            $padded = $size + ($size % 2);

            if ('EXIF' === $type || 'XMP ' === $type) {
                if (0 !== fseek($in, $padded, \SEEK_CUR)) {
                    throw $this->malformed($path);
                }
                continue;
            }

            if ('VP8X' === $type) {
                $payload = $this->read($in, $padded);
                // Clear the "has EXIF" (0x08) and "has XMP" (0x04) feature flags
                $payload[0] = \chr(\ord($payload[0]) & ~0x0C);
                fwrite($out, $chunkHeader.$payload);
                continue;
            }

            fwrite($out, $chunkHeader);
            if ($padded !== stream_copy_to_stream($in, $out, $padded)) {
                throw $this->malformed($path);
            }
        }

        // The RIFF header stores the size of everything after its first 8 bytes
        $total = ftell($out);
        fseek($out, 4);
        fwrite($out, pack('V', $total - 8));
    }

    private function stripVideo(string $path): void
    {
        $ffmpeg = (new ExecutableFinder())->find('ffmpeg');
        if (null === $ffmpeg) {
            throw new \RuntimeException('ffmpeg est introuvable sur le serveur : impossible de retirer les métadonnées de la vidéo. Installez-le (sudo apt install ffmpeg) puis réessayez.');
        }

        $extension = pathinfo($path, \PATHINFO_EXTENSION);
        $temporary = $path.'.strip.'.$extension;
        $command = [
            $ffmpeg, '-nostdin', '-y', '-loglevel', 'error', '-i', $path,
            // Video and audio only: phones also record data tracks that can hold the location
            '-map', '0:v', '-map', '0:a?', '-dn', '-sn',
            '-map_metadata', '-1', '-map_metadata:s:v', '-1', '-map_metadata:s:a', '-1', '-map_chapters', '-1',
            '-c', 'copy', '-fflags', '+bitexact',
        ];
        if (\in_array(strtolower($extension), ['mp4', 'mov', 'm4v'], true)) {
            // Also lets the video start playing before it is fully downloaded
            array_push($command, '-movflags', '+faststart');
        }
        $command[] = $temporary;

        $process = new Process($command);
        $process->setTimeout(self::VIDEO_TIMEOUT);
        $process->run();

        if (!$process->isSuccessful() || !is_file($temporary) || 0 === filesize($temporary)) {
            @unlink($temporary);

            throw new \RuntimeException(sprintf('Les métadonnées de la vidéo "%s" n\'ont pas pu être retirées : %s', basename($path), trim($process->getErrorOutput()) ?: 'erreur ffmpeg inconnue'));
        }

        $this->replace($temporary, $path);
    }

    /**
     * @param resource $stream
     */
    private function read($stream, int $length): string
    {
        $data = $length > 0 ? fread($stream, $length) : '';
        if (false === $data || \strlen($data) !== $length) {
            throw new \RuntimeException('Fichier tronqué ou illisible : les métadonnées n\'ont pas pu être retirées.');
        }

        return $data;
    }

    private function malformed(string $path): \RuntimeException
    {
        return new \RuntimeException(sprintf('Le fichier "%s" est mal formé : ses métadonnées n\'ont pas pu être retirées.', basename($path)));
    }
}
