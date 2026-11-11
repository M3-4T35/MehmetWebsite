<?php

namespace App\Tests\Controller;

use App\Entity\SiteSetting;
use App\Entity\User;
use App\Service\SiteDesign;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SiteDesignTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->reset();
    }

    protected function tearDown(): void
    {
        $this->reset();
        parent::tearDown();
    }

    private function reset(): void
    {
        $this->entityManager->createQuery('DELETE FROM '.SiteSetting::class)->execute();
        $this->entityManager->createQuery('DELETE FROM '.User::class.' u WHERE u.email = :email')
            ->setParameter('email', 'design-admin@example.com')->execute();
        static::getContainer()->get('cache.app')->deleteItem('site_design.active');
    }

    private function loginAsAdmin(): void
    {
        $user = (new User())
            ->setEmail('design-admin@example.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('not-used');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);
    }

    public function testDefaultDesignLoadsNoExtraStylesheet(): void
    {
        $crawler = $this->client->request('GET', '/fr/');

        $this->assertResponseIsSuccessful();
        $this->assertSame(SiteDesign::DEFAULT, $crawler->filter('html')->attr('data-design'));
        $this->assertCount(0, $crawler->filter('link[href*="/designs/"]'));
    }

    public function testEveryDesignHasItsStylesheet(): void
    {
        $directory = static::getContainer()->getParameter('kernel.project_dir').'/public/designs';
        $this->assertFileExists($directory.'/common.css');
        foreach (array_keys(SiteDesign::DESIGNS) as $design) {
            if (SiteDesign::DEFAULT !== $design) {
                $this->assertFileExists($directory.'/'.$design.'.css');
            }
        }
    }

    public function testActivatingADesignAppliesItToVisitors(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/fr/admin/design');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/admin/design/activate"]')->reduce(
            fn ($node) => 'iznik' === $node->filter('input[name="design"]')->attr('value')
        )->form();
        $this->client->submit($form);
        $this->assertResponseRedirects('/fr/admin/design');

        // A visitor without a session now gets the new design
        $this->client->restart();
        $crawler = $this->client->request('GET', '/fr/');
        $this->assertSame('iznik', $crawler->filter('html')->attr('data-design'));
        $this->assertCount(2, $crawler->filter('link[href*="/designs/"]'));
    }

    public function testPreviewOnlyAffectsTheAdminSession(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/fr/admin/design');
        $form = $crawler->filter('form[action$="/admin/design/preview"]')->reduce(
            fn ($node) => 'lettre' === $node->filter('input[name="design"]')->attr('value')
        )->form();
        $this->client->submit($form);
        $this->assertResponseRedirects('/fr/');

        $crawler = $this->client->followRedirect();
        $this->assertSame('lettre', $crawler->filter('html')->attr('data-design'));

        $this->client->restart();
        $crawler = $this->client->request('GET', '/fr/');
        $this->assertSame(SiteDesign::DEFAULT, $crawler->filter('html')->attr('data-design'));
    }

    public function testUnknownDesignIsIgnored(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/fr/admin/design');
        $form = $crawler->filter('form[action$="/admin/design/activate"]')->first()->form();
        $this->client->request('POST', '/fr/admin/design/activate', [
            '_token' => $form->get('_token')->getValue(),
            'design' => '../../etc/passwd',
        ]);

        $this->assertResponseRedirects('/fr/admin/design');
        $this->assertSame(SiteDesign::DEFAULT, static::getContainer()->get(SiteDesign::class)->getActive());
    }
}
