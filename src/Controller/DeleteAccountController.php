<?php

declare(strict_types=1);

namespace App\Controller;

use App\AccountDeletion\AccountDeleter;
use App\AccountDeletion\AccountDeletionRequestRepository;
use App\AccountDeletion\Entity\AccountDeletionRequest;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Persistence\PersistenceException;
use App\User\UserFetcher;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/api/user/account/delete-request", "delete_account_request", methods: ["POST"])]
class DeleteAccountController
{
    public function __construct(private readonly bool $requireEmailConfirmation)
    {
    }

    public function __invoke(
        UserFetcher $userFetcher,
        AccountDeletionRequestRepository $repository,
        AccountDeleter $accountDeleter,
        MailerInterface $mailer,
        LoggerInterface $logger,
    ): Response {
        try {
            $user = $userFetcher->getUser();

            if (!$this->requireEmailConfirmation) {
                $accountDeleter->delete($user);

                return JsonSuccessResponse::create(['deleted' => true]);
            }

            $existing = $repository->findByUser($user);
            if (null !== $existing) {
                $repository->remove($existing);
            }

            $request = new AccountDeletionRequest(
                uuid: bin2hex(random_bytes(16)),
                token: bin2hex(random_bytes(32)),
                user: $user,
                expiresAt: new \DateTimeImmutable('+24 hours'),
            );
            $repository->save($request);

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@schmoppo.de', 'Cleanly Bot'))
                ->to($user->getMail())
                ->subject('Confirm your Cleanly account deletion')
                ->htmlTemplate('account_deletion/email.html.twig')
                ->context(['request' => $request]);

            $mailer->send($email);

            return JsonSuccessResponse::create(['deleted' => false]);
        } catch (PersistenceException | TransportExceptionInterface | \Random\RandomException | \RuntimeException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to process account deletion request');
        }
    }
}
