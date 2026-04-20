<?php

declare(strict_types=1)
;

namespace App\Controller;

use App\HttpFoundation\HtmlResponse;
use App\Template\TemplateRenderException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class PolicyController extends AbstractController
{
    #[Route("/policy/privacy", "policy_privacy", methods: ["GET"])]
    public function privacy(Environment $twig, LoggerInterface $logger): Response
    {
        return self::renderPolicy($twig, $logger, 'policies/privacy.html.twig');
    }

    #[Route("/policy/impress", "policy_impress", methods: ["GET"])]
    public function impress(Environment $twig, LoggerInterface $logger): Response
    {
        return self::renderPolicy($twig, $logger, 'policies/impress.html.twig');
    }

    #[Route("/policy/account_delete", "policy_delete_account", methods: ["GET"])]
    public function deleteAccount(Environment $twig, LoggerInterface $logger): Response
    {
        return self::renderPolicy($twig, $logger, 'policies/delete_account.html.twig');
    }

    private static function renderPolicy(Environment $twig, LoggerInterface $logger, string $template): Response
    {
        try {
            return HtmlResponse::ok(TemplateRenderException::render($twig, $template));
        } catch (TemplateRenderException $e) {
            $logger->error('Failed to render policy page', ['template' => $template, 'exception' => $e]);
            return HtmlResponse::serverError();
        }
    }
}
