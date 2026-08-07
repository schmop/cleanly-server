<?php

declare(strict_types=1);

namespace App\Controller;

use App\AccountDeletion\AccountDeleter;
use App\AccountDeletion\AccountDeletionRequestRepository;
use App\HttpFoundation\HtmlResponse;
use App\Persistence\PersistenceException;
use App\Template\TemplateRenderException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
        LoggerInterface $logger,
    ): Response {
        try {
            $deletionRequest = $repository->findByUuid($uuid);

            if (null === $deletionRequest || $deletionRequest->token !== $token) {
                return HtmlResponse::withStatus(
                    TemplateRenderException::render($twig, 'account_deletion/invalid.html.twig'),
                    Response::HTTP_NOT_FOUND,
                );
            }

            if ($deletionRequest->isExpired()) {
                $repository->remove($deletionRequest);

                return HtmlResponse::withStatus(
                    TemplateRenderException::render($twig, 'account_deletion/expired.html.twig'),
                    Response::HTTP_GONE,
                );
            }

            $user = $deletionRequest->user;
            $repository->remove($deletionRequest);
            $accountDeleter->delete($user);

            return HtmlResponse::ok(TemplateRenderException::render($twig, 'account_deletion/success.html.twig'));
        } catch (TemplateRenderException | PersistenceException $e) {
            $logger->error('Account deletion confirmation failed', ['exception' => $e]);
            return HtmlResponse::serverError();
        }
    }
}
