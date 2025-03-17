<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand('cleanly:test-send-mail')]
class TestSendMail extends Command
{
    public function __construct(
        private readonly MailerInterface     $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addArgument('mail', InputArgument::REQUIRED, 'Mail-address to send mail to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mail = $input->getArgument('mail');
        if (null === $mail) {
            $output->writeln('No mail address given!');
            return Command::FAILURE;
        }
        $email = (new Email())
            ->from(new Address('noreply@schmoppo.de', 'Cleanly Bot'))
            ->to($mail)
            ->subject('Your registration on cleanly')
            ->html(<<<TEXT
<p>Hello!</p>

<p>This is a test mail.</p>

<p>Greetings,</br>
Cleanly.</p>
TEXT
)
        ;

        $this->mailer->send($email);
        return Command::SUCCESS;
    }
}
