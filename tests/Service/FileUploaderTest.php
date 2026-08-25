<?php

namespace App\Tests\Service;

use App\Service\FileUploader;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploaderTest extends TestCase
{
    private string $targetDir;

    protected function setUp(): void
    {
        $this->targetDir = sys_get_temp_dir().'/uploader_test_'.uniqid('', true);
        mkdir($this->targetDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->targetDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->targetDir);
    }

    #[TestDox('upload() sanitizes the filename and moves the file into the target directory')]
    public function testUploadSanitizesAndMovesFile(): void
    {
        $uploader = new FileUploader($this->targetDir);

        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($tmp, "%PDF-1.4\n1 0 obj\nendobj\ntrailer<<>>\n%%EOF");
        $file = new UploadedFile($tmp, 'Mon Fichier Été.pdf', 'application/pdf', null, true);

        $name = $uploader->upload($file);

        $this->assertMatchesRegularExpression('/^monfichierete-.+\.pdf$/', $name);
    }

    #[TestDox('remove() deletes files inside the target directory only')]
    public function testRemoveDeletesOnlyWithinTargetDirectory(): void
    {
        $uploader = new FileUploader($this->targetDir);

        touch($this->targetDir.'/a.jpg');
        $uploader->remove('a.jpg');
        $this->assertFileDoesNotExist($this->targetDir.'/a.jpg');

        // Path traversal attempt: must be ignored
        $outside = sys_get_temp_dir().'/outside_uploader_test.txt';
        touch($outside);
        $uploader->remove('../../'.basename(sys_get_temp_dir()).'/'.basename($outside));
        $this->assertFileExists($outside);

        unlink($outside);
    }

    #[TestDox('remove() ignores empty filenames and missing files')]
    public function testRemoveIsSafeOnMissingFiles(): void
    {
        $uploader = new FileUploader($this->targetDir);

        $uploader->remove(null);
        $uploader->remove('');
        $uploader->remove('does-not-exist.jpg');

        $this->expectNotToPerformAssertions();
    }
}
