<?php

namespace App\Tests\Entity;

use App\Entity\Project;
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $project = new Project();
        
        $project->setTitle('Mon Projet');
        $project->setTitleEn('My Project');
        $project->setDescriptionCourte('Une description');
        $project->setDescriptionCourteEn('A description');
        $project->setDescription('# Markdown content');
        $project->setDescriptionEn('# Markdown English');
        $project->setGithubUrl('https://github.com/test');
        $project->setProductionUrl('https://test.com');
        
        $createdAt = new \DateTimeImmutable();
        $project->setCreatedAt($createdAt);

        $this->assertEquals('Mon Projet', $project->getTitle());
        $this->assertEquals('My Project', $project->getTitleEn());
        $this->assertEquals('Une description', $project->getDescriptionCourte());
        $this->assertEquals('A description', $project->getDescriptionCourteEn());
        $this->assertEquals('# Markdown content', $project->getDescription());
        $this->assertEquals('# Markdown English', $project->getDescriptionEn());
        $this->assertEquals('https://github.com/test', $project->getGithubUrl());
        $this->assertEquals('https://test.com', $project->getProductionUrl());
        $this->assertSame($createdAt, $project->getCreatedAt());
    }
}
