<?php

namespace App\Command;

use App\User\Entity\User;
use App\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsCommand('cleanly:create-account')]
class CreateAccountCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addArgument('name', InputArgument::REQUIRED, 'Username');
        $this->addArgument('email', InputArgument::REQUIRED, 'Email');
        $this->addArgument('password', InputArgument::REQUIRED, 'Password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        if (null === $name || null === $email || null === $password) {
            $output->writeln('Missing arguments!');
            return Command::FAILURE;
        }
        $user = new User($email, $name);
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $user->setPassword($passwordHasher->hash($password));
        $this->userRepository->save($user);
        $output->writeln("User '{$name}'' with mail '{$email}'' created!");
        return Command::SUCCESS;
    }
}
