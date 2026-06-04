<?php

namespace App\Tests\Entity;

use App\Entity\CV;
use PHPUnit\Framework\TestCase;

class CVTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $cv = new CV();
        
        $cv->setTitle('Mon CV');
        $cv->setFilename('cv.pdf');
        $uploadedAt = new \DateTimeImmutable();
        $cv->setUploadedAt($uploadedAt);

        $this->assertEquals('Mon CV', $cv->getTitle());
        $this->assertEquals('cv.pdf', $cv->getFilename());
        $this->assertSame($uploadedAt, $cv->getUploadedAt());
    }
}
