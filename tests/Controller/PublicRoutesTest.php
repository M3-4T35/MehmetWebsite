<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicRoutesTest extends WebTestCase
{
    #[ \PHPUnit\Framework\Attributes\DataProvider('getPublicUrls')]
    public function testPageIsSuccessful(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
    }

    public static function getPublicUrls(): \Generator
    {
        yield ['/fr/'];
        yield ['/en/'];
        yield ['/fr/travaux'];
        yield ['/en/travaux'];
        yield ['/fr/cv/public'];
        yield ['/en/cv/public'];
        yield ['/fr/mentions-legales'];
        yield ['/en/mentions-legales'];
    }

    public function testLocaleSwitching(): void
    {
        $client = static::createClient();
        
        // Start in FR
        $client->request('GET', '/fr/');
        $this->assertSelectorTextContains('h1', 'Mehmet ATES');
        $this->assertSelectorTextContains('h2', 'Ingénierie Système et Logiciel');

        // Switch to EN
        $client->request('GET', '/fr/change-locale/en');
        $client->followRedirect(); // Redirects to /en
        
        $this->assertSelectorTextContains('h2', 'System and Software Engineering');
    }
}
