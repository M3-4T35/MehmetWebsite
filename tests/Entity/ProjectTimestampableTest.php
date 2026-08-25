<?php

namespace App\Tests\Entity;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectTimestampableTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        if ($this->entityManager !== null) {
            foreach ($this->entityManager->getRepository(Project::class)->findBy(['title' => 'project-timestampable-test']) as $project) {
                $this->entityManager->remove($project);
            }
            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testCreatedAtAndUpdatedAtAreSetAutomaticallyOnPersist(): void
    {
        $project = new Project();
        $project->setTitle('project-timestampable-test');
        $project->setDescription('# test');

        $this->assertNull($project->getCreatedAt());

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $this->assertNotNull($project->getCreatedAt(), 'created_at must be set automatically by the Timestampable listener');
        $this->assertNotNull($project->getUpdatedAt(), 'updated_at must be set automatically on first insert');

        // updatedAt must change when the entity is modified later
        $originalUpdatedAt = $project->getUpdatedAt();
        sleep(1);
        $project->setGithubUrl('https://github.com/example/test');
        $this->entityManager->flush();

        $this->assertGreaterThan($originalUpdatedAt, $project->getUpdatedAt());
    }
}
