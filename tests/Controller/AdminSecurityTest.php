<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminSecurityTest extends WebTestCase
{
    #[ \PHPUnit\Framework\Attributes\DataProvider('getAdminUrls')]
    public function testAdminPagesAreRestricted(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        // Should redirect to login page
        $this->assertResponseRedirects('/fr/login');
    }

    public static function getAdminUrls(): \Generator
    {
        yield ['/fr/admin'];
        yield ['/fr/project'];
        yield ['/fr/project/new'];
        yield ['/fr/media'];
        yield ['/fr/cv'];
    }
}
