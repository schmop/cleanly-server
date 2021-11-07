<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Household;
use App\HttpFoundation\JsonSuccessResponse;
use App\Repository\HouseholdRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class HouseHoldController extends AbstractController
{
    private const HEX_COLOR_FORMAT = '/^#[a-fA-F0-9]{6}$/';

    /**
     * @Route("/api/household/create", name="create_household", methods={"POST"})
     */
    public function createHouseHold(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $household = Household::createFromRequest($request, $this->getUser());
        $entityManager->persist($household);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    /**
     * @Route("/api/household/color", name="household_set_color", methods={"POST"})
     */
    public function changeColor(
        Request $request,
        HouseholdRepository $householdRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $id = $request->request->get('id');
        $color = $request->request->get('color');
        if ($id === null || $color === null) {
            throw new \InvalidArgumentException('"id" and "color" must be set!');
        }
        if (!preg_match(self::HEX_COLOR_FORMAT, $color)) {
            throw new \InvalidArgumentException('"color" must be in hex color format. Example: "#ff00ad"');
        }
        $household = $householdRepository->find($id);
        if (null == $household || $household->getAdmin() !== $this->getUser()) {
            throw new \InvalidArgumentException('Insufficient privileges!');
        }
        $household->setColor($color);
        $entityManager->persist($household);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    /**
     * @Route("/api/household", name="delete_household", methods={"DELETE"})
     */
    public function deleteHousehold(
        Request $request,
        HouseholdRepository $householdRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $id = $request->get('id');
        if ($id === null) {
            throw new \InvalidArgumentException('"id" must be set!');
        }
        $household = $householdRepository->find($id);
        if (null == $household || $household->getAdmin() !== $this->getUser()) {
            throw new \InvalidArgumentException('Insufficient privileges!');
        }
        $entityManager->remove($household);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success']);
    }
}