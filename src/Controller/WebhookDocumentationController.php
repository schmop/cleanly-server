<?php

declare(strict_types = 1)
;

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class WebhookDocumentationController extends AbstractController
{
    #[Route("/webhook/doc", "webhook_documentation", methods: ["GET"])]
    public function webhookDocumentation(Environment $twig): Response
    {
        return new Response($twig->render('webhook/documentation.html.twig'));
    }
}