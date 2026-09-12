<?php

namespace App\Tests\Entity;

use App\Entity\Media;
use App\Entity\Project;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $media = new Media();
        $project = new Project();
        $project->setTitle('Projet Test');

        $now = new \DateTimeImmutable();

        $media->setFilename('document-test.pdf');
        $media->setAlt('Mon rapport de projet');
        $media->setPosition(2);
        $media->setCreatedAt($now);
        $media->setProject($project);

        $this->assertEquals('document-test.pdf', $media->getFilename());
        $this->assertEquals('Mon rapport de projet', $media->getAlt());
        $this->assertEquals(2, $media->getPosition());
        $this->assertSame($now, $media->getCreatedAt());
        $this->assertSame($project, $media->getProject());
    }
}
