<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Household;
use App\HttpFoundation\JsonSuccessResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class HouseHoldController extends AbstractController
{
    /**
     * @Route("/api/household/create", "create_household")
     */
    public function createHouseHold(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $household = Household::createFromRequest($request, $this->getUser());
        $entityManager->persist($household);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success']);
    }
}