<?php

namespace App\Tests\Service;

use App\Service\MetadataStripper;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class MetadataStripperTest extends TestCase
{
    private string $directory;
    private MetadataStripper $stripper;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/metadata_stripper_test_'.uniqid();
        mkdir($this->directory);
        $this->stripper = new MetadataStripper();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    /**
     * A 1x1 JPEG carrying an EXIF block (camera make + orientation), an XMP block and a comment.
     */
    private function jpegWithMetadata(int $orientation): string
    {
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/yQALCAABAAEBAREA/8wABgAQEAX/2gAIAQEAAD8A0s8g/9k=');

        $make = "TestCam\0";
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"
            ."\x00\x02"
            ."\x01\x0F\x00\x02".pack('N', \strlen($make)).pack('N', 38)       // Make, ASCII, at offset 38
            ."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00" // Orientation
            ."\x00\x00\x00\x00"
            .$make;
        $segment = static fn (int $marker, string $payload): string => "\xFF".\chr($marker).pack('n', \strlen($payload) + 2).$payload;

        $metadata = $segment(0xE1, "Exif\0\0".$tiff)
            .$segment(0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>GPS 46.43,5.72</x:xmpmeta>")
            .$segment(0xFE, 'shot at home');

        // Insert after SOI + the JFIF APP0 segment (2 + 18 bytes)
        return substr($jpeg, 0, 20).$metadata.substr($jpeg, 20);
    }

    #[TestDox('JPEG: camera data, XMP and comments are removed, the image data is untouched')]
    public function testStripsJpegMetadata(): void
    {
        $path = $this->directory.'/photo.jpg';
        file_put_contents($path, $this->jpegWithMetadata(1));
        $this->assertSame('TestCam', exif_read_data($path)['Make']);

        $this->stripper->strip($path);

        $content = file_get_contents($path);
        $this->assertStringNotContainsString('TestCam', $content);
        $this->assertStringNotContainsString('GPS 46.43', $content);
        $this->assertStringNotContainsString('shot at home', $content);
        $this->assertNotFalse(getimagesize($path));

        // Same bytes as the original picture without its metadata
        $original = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/yQALCAABAAEBAREA/8wABgAQEAX/2gAIAQEAAD8A0s8g/9k=');
        $this->assertSame($original, $content);
    }

    #[TestDox('JPEG: the orientation is kept so phone photos are not displayed rotated')]
    public function testKeepsJpegOrientation(): void
    {
        $path = $this->directory.'/portrait.jpg';
        file_put_contents($path, $this->jpegWithMetadata(6));

        $this->stripper->strip($path);

        $exif = exif_read_data($path);
        $this->assertSame(6, $exif['Orientation']);
        $this->assertArrayNotHasKey('Make', $exif);
        $this->assertStringNotContainsString('GPS 46.43', file_get_contents($path));
    }

    #[TestDox('PNG: text, EXIF and date chunks are removed')]
    public function testStripsPngMetadata(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $withMetadata = substr($png, 0, 33)
            .$chunk('tEXt', "Comment\0taken in Moirans")
            .$chunk('eXIf', "MM\x00\x2A\x00\x00\x00\x08\x00\x00")
            .$chunk('tIME', "\x07\xEA\x0B\x0B\x12\x00\x00")
            .substr($png, 33);
        $path = $this->directory.'/image.png';
        file_put_contents($path, $withMetadata);

        $this->stripper->strip($path);

        $this->assertSame($png, file_get_contents($path));
    }

    #[TestDox('WebP: EXIF and XMP chunks are removed and the header is kept consistent')]
    public function testStripsWebpMetadata(): void
    {
        // 1x1 lossless WebP bitstream wrapped in the extended format with EXIF and XMP chunks
        $vp8l = base64_decode('LwAAAAAHEP4HAAAA') ?: '';
        $chunk = static fn (string $type, string $data): string => $type.pack('V', \strlen($data)).$data.(\strlen($data) % 2 ? "\0" : '');
        $body = static fn (int $flags, string $extra): string => 'WEBP'
            .$chunk('VP8X', \chr($flags)."\0\0\0"."\0\0\0"."\0\0\0")
            .$chunk('VP8L', $vp8l)
            .$extra;
        $riff = static fn (string $content): string => 'RIFF'.pack('V', \strlen($content)).$content;

        $path = $this->directory.'/image.webp';
        file_put_contents($path, $riff($body(0x0C, $chunk('EXIF', 'TestCam GPS').$chunk('XMP ', '<x:xmpmeta/>'))));

        $this->stripper->strip($path);

        $this->assertSame($riff($body(0x00, '')), file_get_contents($path));
    }

    #[TestDox('Video: location, title and dates are removed without re-encoding')]
    public function testStripsVideoMetadata(): void
    {
        $finder = new ExecutableFinder();
        $ffmpeg = $finder->find('ffmpeg');
        $ffprobe = $finder->find('ffprobe');
        if (!$ffmpeg || !$ffprobe) {
            $this->markTestSkipped('ffmpeg/ffprobe not installed.');
        }

        $path = $this->directory.'/clip.mp4';
        (new Process([
            $ffmpeg, '-nostdin', '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=64x64:rate=5',
            '-metadata', 'location=+46.4300+005.7200/', '-metadata', 'title=Chez moi', '-metadata', 'make=TestCam',
            '-metadata', 'creation_time=2026-11-11T12:00:00Z', '-movflags', 'use_metadata_tags', '-pix_fmt', 'yuv420p', $path,
        ]))->mustRun();
        $this->assertStringContainsString('46.4300', file_get_contents($path));

        $this->stripper->strip($path);

        $content = file_get_contents($path);
        $this->assertStringNotContainsString('46.4300', $content);
        $this->assertStringNotContainsString('Chez moi', $content);
        $this->assertStringNotContainsString('TestCam', $content);

        $probe = (new Process([$ffprobe, '-v', 'error', '-show_entries', 'format_tags:stream_tags:stream=codec_name,width', '-of', 'json', $path]))->mustRun();
        $data = json_decode($probe->getOutput(), true);
        $this->assertSame('h264', $data['streams'][0]['codec_name'] ?? null);
        $tags = array_keys(($data['format']['tags'] ?? []) + ($data['streams'][0]['tags'] ?? []));
        $this->assertSame([], array_values(array_diff($tags, ['major_brand', 'minor_version', 'compatible_brands', 'language', 'handler_name', 'vendor_id', 'encoder'])));
        $this->assertArrayNotHasKey('creation_time', $data['format']['tags'] ?? []);
    }

    #[TestDox('Other files (PDF, GIF...) are left untouched')]
    public function testLeavesOtherFilesAlone(): void
    {
        $path = $this->directory.'/doc.pdf';
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        $this->stripper->strip($path);

        $this->assertSame("%PDF-1.4\n%%EOF\n", file_get_contents($path));
    }

    #[TestDox('A truncated image is rejected instead of being kept with its metadata')]
    public function testRejectsMalformedJpeg(): void
    {
        $path = $this->directory.'/broken.jpg';
        file_put_contents($path, substr($this->jpegWithMetadata(1), 0, 40));

        $this->expectException(\RuntimeException::class);
        $this->stripper->strip($path);
    }
}
