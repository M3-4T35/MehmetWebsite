<?php

namespace App\Tests\Controller;

use App\Entity\Media;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicProjectDetailTest extends WebTestCase
{
    public function testProjectDetailRendersPdfMediaAndModal(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $project = new Project();
        $project->setTitle('Projet avec PDF');
        $project->setDescriptionCourte('Description courte');
        $project->setDescription('# Rapport');
        $project->setCreatedAt(new \DateTimeImmutable());
        $em->persist($project);

        $media = new Media();
        $media->setFilename('document_test.pdf');
        $media->setAlt('Cahier des charges');
        $media->setCreatedAt(new \DateTimeImmutable());
        $media->setPosition(1);
        $media->setProject($project);
        $em->persist($media);

        $em->flush();

        $crawler = $client->request('GET', '/fr/travaux/' . $project->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Projet avec PDF');
        $this->assertSelectorTextContains('.media-gallery', 'Cahier des charges');
        // Check for PDF icon
        $this->assertGreaterThan(0, $crawler->filter('.bi-file-earmark-pdf')->count());
        // Check modal exists with object tag
        $this->assertGreaterThan(0, $crawler->filter('#pdfModal' . $media->getId())->count());
        $this->assertGreaterThan(0, $crawler->filter('#pdfModal' . $media->getId() . ' object[type="application/pdf"]')->count());
    }
}
