<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    public function testLogin(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        // 1. Create a test user
        $userRepository = $container->get(UserRepository::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $testEmail = 'admin-test@example.com';
        $user = $userRepository->findOneBy(['email' => $testEmail]);
        
        if (!$user) {
            $user = new User();
            $user->setEmail($testEmail);
            $user->setRoles(['ROLE_ADMIN']);
            $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
            
            $entityManager = $container->get('doctrine.orm.entity_manager');
            $entityManager->persist($user);
            $entityManager->flush();
        }

        // 2. Go to login page
        $crawler = $client->request('GET', '/fr/login');
        $this->assertResponseIsSuccessful();

        // 3. Fill the login form
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $testEmail,
            '_password' => 'password123',
        ]);

        $client->submit($form);

        // 4. Should redirect to admin dashboard
        $this->assertResponseRedirects('/fr/admin');
        $client->followRedirect();
        
        $this->assertSelectorTextContains('h1', 'Dashboard Admin');
    }

    public function testInvalidLogin(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/fr/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'wrong@example.com',
            '_password' => 'wrong-password',
        ]);

        $client->submit($form);

        // Should redirect back to login
        $this->assertResponseRedirects('/fr/login');
        $client->followRedirect();
        
        $this->assertSelectorExists('.alert-danger');
    }
}
