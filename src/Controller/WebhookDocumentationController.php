<?php

declare(strict_types = 1)
;

namespace App\Controller;

use App\HttpFoundation\HtmlResponse;
use App\Template\TemplateRenderException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class WebhookDocumentationController extends AbstractController
{
    #[Route("/webhook/doc", "webhook_documentation", methods: ["GET"])]
    public function webhookDocumentation(Environment $twig, LoggerInterface $logger): Response
    {
        try {
            return HtmlResponse::ok(TemplateRenderException::render($twig, 'webhook/documentation.html.twig'));
        } catch (TemplateRenderException $e) {
            $logger->error('Failed to render webhook documentation', ['exception' => $e]);
            return HtmlResponse::serverError();
        }
    }
}