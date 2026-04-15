<?php

declare(strict_types=1);

namespace App\Controller;

use App\AccountDeletion\AccountDeleter;
use App\AccountDeletion\AccountDeletionRequestRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

#[Route("/account/delete/{uuid}/{token}", "confirm_account_deletion", methods: ["GET"])]
class ConfirmAccountDeletionController
{
    public function __invoke(
        string $uuid,
        string $token,
        AccountDeletionRequestRepository $repository,
        AccountDeleter $accountDeleter,
        Environment $twig,
    ): Response {
        $deletionRequest = $repository->findByUuid($uuid);

        if (null === $deletionRequest || $deletionRequest->token !== $token) {
            return new Response(
                $twig->render('account_deletion/invalid.html.twig'),
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($deletionRequest->isExpired()) {
            $repository->remove($deletionRequest);

            return new Response(
                $twig->render('account_deletion/expired.html.twig'),
                Response::HTTP_GONE,
            );
        }

        $user = $deletionRequest->user;
        $repository->remove($deletionRequest);
        $accountDeleter->delete($user);

        return new Response($twig->render('account_deletion/success.html.twig'));
    }
}
