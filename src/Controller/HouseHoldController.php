<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Household;
use App\Entity\HouseholdInvite;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Utils\Base64UrlInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
     * @Route("/api/household/{id}/color", name="household_set_color", methods={"POST"})
     */
    public function changeColor(
        Household $household,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $color = $request->request->get('color');
        if ($color === null) {
            throw new \InvalidArgumentException('"color" must be set!');
        }
        if (!preg_match(self::HEX_COLOR_FORMAT, $color)) {
            throw new \InvalidArgumentException('"color" must be in hex color format. Example: "#ff00ad"');
        }
        if ($household->getAdmin() !== $this->getUser()) {
            throw new \InvalidArgumentException('Insufficient privileges!');
        }
        $household->setColor($color);
        $entityManager->persist($household);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    /**
     * @Route("/api/household/{id}/invite", name="household_generate_invite", methods={"POST"})
     */
    public function invite(
        Household $household,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        Base64UrlInterface $base64Url
    ): JsonResponse {
        if ($household->getAdmin() !== $this->getUser()) {
            throw new \InvalidArgumentException('Insufficient privileges!');
        }
        try {
            $inviteToken = new HouseholdInvite($base64Url->encode(random_bytes(32)), $household);
        } catch (\Exception $e) {
            return JsonErrorResponse::create(['status' => 'error', 'reason' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        $entityManager->persist($inviteToken);
        $entityManager->flush();
        /**
         * @TODO: If this will be an App using JWT, links will not be as easy. Use Links or QR-Codes.
         */
        //$url = $urlGenerator->generate('household_join', ['token' => $inviteToken->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        return JsonSuccessResponse::create(['status' => 'success', 'token' => $inviteToken->getToken()]);
    }


    /**
     * @Route("/api/household/{token}/join", name="household_join")
     */
    public function join(
        HouseholdInvite $invite,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if ($invite->getValidUntil()->getTimestamp() <= (new \DateTime())->getTimestamp()) {
            return JsonErrorResponse::create(['status' => 'error', 'reason' => 'Outdated invite!']);
        }
        $alreadyMember = $invite->getHousehold()->getMembers()->exists(function(int $id, UserInterface $member) {
            return $member === $this->getUser();
        });
        if ($alreadyMember) {
            return JsonErrorResponse::create(['status' => 'error', 'reason' => 'Already member of this household!']);
        }
        $household = $invite->getHousehold();
        $household->addMember($this->getUser());
        $entityManager->persist($household);
        $entityManager->remove($invite);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    /**
     * @Route("/api/household/{id}", name="delete_household", methods={"DELETE"})
     */
    public function deleteHousehold(
        Household $household,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if ($household->getAdmin() !== $this->getUser()) {
            throw new \InvalidArgumentException('Insufficient privileges!');
        }
        $entityManager->remove($household);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success']);
    }
}