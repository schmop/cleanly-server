<?php

declare(strict_types = 1);

namespace App\Controller;

use App\HttpFoundation\HtmlResponse;
use App\Kernel;
use App\Template\TemplateRenderException;
use Parsedown;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class ChangelogController extends AbstractController
{
    #[Route("/changelog", "changelog", methods: ["GET"])]
    public function changelog(Environment $twig, Kernel $kernel, LoggerInterface $logger): Response
    {
        try {
            $parseDown = new Parsedown();
            $changelogMarkdown = file_get_contents($kernel->getProjectDir().'/templates/changelog/changelog.md');

            return HtmlResponse::ok(TemplateRenderException::render($twig, 'changelog/changelog.html.twig', [
                'content' => $parseDown->parse($changelogMarkdown),
            ]));
        } catch (TemplateRenderException $e) {
            $logger->error('Failed to render changelog', ['exception' => $e]);
            return HtmlResponse::serverError();
        }
    }
}