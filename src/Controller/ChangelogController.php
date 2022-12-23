<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Kernel;
use Parsedown;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class ChangelogController extends AbstractController
{
    #[Route("/changelog", "changelog", methods: ["GET"])]
    public function changelog(Environment $twig, Kernel $kernel): Response
    {
        $parseDown = new Parsedown();
        $changelogMarkdown = file_get_contents($kernel->getProjectDir().'/templates/changelog/changelog.md');

        return new Response($twig->render('changelog/changelog.html.twig', [
            'content' => $parseDown->parse($changelogMarkdown),
        ]));
    }
}