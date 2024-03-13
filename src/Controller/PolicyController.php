<?php

declare(strict_types=1)
;

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class PolicyController extends AbstractController
{
    #[Route("/policy/privacy", "policy_privacy", methods: ["GET"])]
    public function privacy(Environment $twig): Response
    {
        return new Response($twig->render('policies/privacy.html.twig'));
    }


    #[Route("/policy/impress", "policy_impress", methods: ["GET"])]
    public function impress(Environment $twig): Response
    {
        return new Response($twig->render('policies/impress.html.twig'));
    }


    #[Route("/policy/account_delete", "policy_delete_account", methods: ["GET"])]
    public function deleteAccount(Environment $twig): Response
    {
        return new Response($twig->render('policies/delete_account.html.twig'));
    }
}
