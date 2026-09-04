<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Creates or updates an admin/user account in the database',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'The email address of the user')
            ->addArgument('password', InputArgument::OPTIONAL, 'The plain text password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Assign the ROLE_ADMIN role')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getArgument('email')) {
            $question = new Question('Enter email address: ');
            $question->setValidator(function (?string $email) {
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Please enter a valid email address.');
                }
                return $email;
            });
            $input->setArgument('email', $io->askQuestion($question));
        }

        if (!$input->getArgument('password')) {
            $question = new Question('Enter password: ');
            $question->setHidden(true);
            $question->setValidator(function (?string $password) {
                if (empty($password)) {
                    throw new \RuntimeException('Password cannot be empty.');
                }
                return $password;
            });
            $input->setArgument('password', $io->askQuestion($question));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $plainPassword = (string) $input->getArgument('password');
        $isAdmin = $input->getOption('admin');

        if (empty($email) || empty($plainPassword)) {
            $io->error('Both email and password are required.');
            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNew = false;
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $isNew = true;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $roles = $user->getRoles();
        // Default new users via this command or explicit --admin flag to ROLE_ADMIN
        if ($isAdmin || $isNew) {
            if (!in_array('ROLE_ADMIN', $roles, true)) {
                $roles[] = 'ROLE_ADMIN';
            }
        }
        $user->setRoles(array_values(array_unique($roles)));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $action = $isNew ? 'created' : 'updated';
        $io->success(sprintf('User %s successfully %s with roles: %s', $email, $action, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
