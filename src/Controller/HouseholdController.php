<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\User\Entity\User;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Invite\InvitePublisher;
use App\Push\Pusher;
use App\Household\HouseholdInviteRepository;
use App\User\UserRepository;
use App\User\UserFetcher;
use App\Utils\Base64UrlInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Todo\Entity\Todo;

class HouseholdController extends AbstractController
{
    private const HEX_COLOR_FORMAT = '/^#[a-fA-F0-9]{6}$/';

    /**
     * @Route("/api/household/create", name="create_household", methods={"POST"})
     */
    public function createHouseHold(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $household = Household::createFromRequest($request, $user);
        $entityManager->persist($household);
        $entityManager->flush();

        return JsonSuccessResponse::create();
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

        return JsonSuccessResponse::create();
    }

    /**
     * @Route("/api/household/invite/{id}", name="household_invite", methods={"POST"})
     */
    public function invite(
        Household $household,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Base64UrlInterface $base64Url,
        Request $request,
        InvitePublisher $invitePublisher,
        Pusher $pusher,
    ): JsonResponse {
        if ($household->getAdmin() !== $this->getUser()) {
            return JsonErrorResponse::create(['reason' => 'Insufficient privileges!'], Response::HTTP_FORBIDDEN);
        }
        $ids = json_decode($request->request->get('ids'), true, flags: JSON_THROW_ON_ERROR);
        $invitees = $userRepository->findBy(['id' => $ids]);
        foreach ($invitees as $invitee) {
            try {
                $inviteToken = new HouseholdInvite($base64Url->encode(random_bytes(32)), $household, $invitee);
            } catch (\Exception $e) {
                return JsonErrorResponse::create([ 'reason' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            $entityManager->persist($inviteToken);
            $invitePublisher->publish($inviteToken);
        }
        $entityManager->flush();
        $pusher->publishToUsers(
            $invitees,
            "Einladung in Haushalt",
            sprintf("Einladung von %s in den Haushalt %s erhalten", $this->getUser()->getName(), $household->getName())
        );

        return JsonSuccessResponse::create();
    }

    /**
     * @Route("/api/household/join-by-token/{token}", name="household_join")
     */
    public function join(
        HouseholdInvite $invite,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if ($invite->getValidUntil()->getTimestamp() <= (new \DateTime())->getTimestamp()) {
            return JsonErrorResponse::create(['reason' => 'Outdated invite!']);
        }
        $alreadyMember = $invite->getHousehold()->getMembers()->exists(function (int $id, User $member) {
            return $member === $this->getUser();
        });
        if ($alreadyMember) {
            return JsonErrorResponse::create(['reason' => 'Already member of this household!']);
        }
        $household = $invite->getHousehold();
        $household->addMember($this->getUser());
        $entityManager->persist($household);
        $entityManager->remove($invite);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }


    /**
     * @Route("/api/household/accept-invite/{id}", name="household_accept_invite")
     */
    public function acceptInvite(
        Household $household,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if ($household->getMembers()->contains($user)) {
            return JsonErrorResponse::create(['reason' => 'You are already a member of this household!']);
        }
        $invites = $household->getInvites();
        foreach ($invites as $invite) {
            if ($invite->getInvitee() === $user) {
                $household->addMember($user);
                $entityManager->remove($invite);
                $entityManager->persist($household);
                $entityManager->flush();

                return JsonSuccessResponse::create([
                    'household' => $household->jsonSerialize()
                ]);
            }
        }

        return JsonErrorResponse::create(['reason' => 'You did not receive an invite to this household!']);
    }

    /**
     * @Route("/api/household/decline-invite/{id}", name="household_decline_invite")
     */
    public function declineInvite(
        Household $household,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        $invites = $household->getInvites();
        foreach ($invites as $invite) {
            if ($invite->getInvitee() === $user) {
                $entityManager->remove($invite);
                $entityManager->flush();

                return JsonSuccessResponse::create();
            }
        }

        return JsonErrorResponse::create(['reason' => 'You did not receive an invite to this household!']);
    }

    /**
     * @Route("/api/household/leave/{id}", name="household_leave", methods={"POST"})
     */
    public function leaveHousehold(
        Household $household,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$household->getMembers()->contains($user)) {
            return JsonErrorResponse::create(['reason' => 'Cannot leave, you are not a member.',]);
        }
        if (!$household->getAdmin() === $user) {
            return JsonErrorResponse::create(['reason' => 'You cannot leave this household as an admin.',]);
        }
        $household->removeMember($user);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }

    /**
     * @Route("/api/household/kick/{id}/{user_id}", name="household_kick", methods={"POST"})
     * @Entity("kicked", expr="repository.find(user_id)")
     */
    public function kickMember(
        Household $household,
        User $kicked,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$household->getAdmin() === $user) {
            return JsonErrorResponse::create(['reason' => 'You do not have sufficient privileges to kick members.',]);
        }
        if ($user === $kicked) {
            return JsonErrorResponse::create(['reason' => 'You cannot kick yourself.',]);
        }
        $household->removeMember($kicked);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }

    /**
     * @Route("/api/household/transfer-ownership/{id}/{user_id}", name="household_transfer_ownership", methods={"POST"})
     * @Entity("newOwner", expr="repository.find(user_id)")
     */
    public function transferOwnership(
        Household $household,
        User $newOwner,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$household->getAdmin() === $user) {
            return JsonErrorResponse::create(['reason' => 'You do not have sufficient privileges to transfer ownership.',]);
        }
        if ($user === $newOwner) {
            return JsonErrorResponse::create(['reason' => 'You are already the owner.',]);
        }
        if (!$household->getMembers()->contains($newOwner)) {
            return JsonErrorResponse::create(['reason' => 'The new owner must be a member of the household.',]);
        }
        $household->setAdmin($newOwner);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }

    /**
     * @Route("/api/household/{id}", name="delete_household", methods={"DELETE"})
     */
    public function deleteHousehold(
        Household $household,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if ($household->getAdmin() !== $this->getUser()) {
            return JsonErrorResponse::create(['reason' => 'You do not have sufficient privileges to remove this task!']);
        }
        $entityManager->remove($household);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }
}
