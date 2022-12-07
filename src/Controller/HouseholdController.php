<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\Household\Entity\HouseholdPrivilege;
use App\Household\HouseholdVoter;
use App\User\Entity\User;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Invite\InvitePublisher;
use App\Json\Json;
use App\Push\Pusher;
use App\User\UserRepository;
use App\Utils\Base64UrlInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;

class HouseholdController extends UserAwareController
{
    #[Route(path: '/api/household/create', name: 'create_household', methods: ['POST'])]
    public function createHouseHold(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $household = Household::createFromRequest($request, $user);
        $entityManager->persist($household);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/invite/{id}', name: 'household_invite', methods: ['POST'])]
    public function invite(
        Household $household,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Base64UrlInterface $base64Url,
        Request $request,
        InvitePublisher $invitePublisher,
        Pusher $pusher,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
        $user = $this->getUser();
        $data = Json::fromRequest($request);
        $ids = $data->intArray('ids');
        $invitees = $userRepository->findBy(['id' => $ids]);
        foreach ($invitees as $invitee) {
            try {
                $inviteToken = new HouseholdInvite($base64Url->encode(random_bytes(32)), $household, $invitee, $user);
            } catch (\Exception $e) {
                return JsonErrorResponse::create([ 'reason' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            $entityManager->persist($inviteToken);
            $invitePublisher->publish($inviteToken);
        }
        $entityManager->flush();
        $pusher->publishInvites(
            $invitees,
            "Einladung in Haushalt",
            sprintf("Einladung von %s in den Haushalt %s erhalten", $user->getName(), $household->getName())
        );

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/join-by-token/{token}', name: 'household_join')]
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


    #[Route(path: '/api/household/accept-invite/{id}', name: 'household_accept_invite')]
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

    #[Route(path: '/api/household/decline-invite/{id}', name: 'household_decline_invite')]
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

    #[Route(path: '/api/household/leave/{id}', name: 'household_leave', methods: ['POST'])]
    public function leaveHousehold(
        Household $household,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$household->getMembers()->contains($user)) {
            return JsonErrorResponse::create(['reason' => 'Cannot leave, you are not a member.',]);
        }

        if ($household->getUserPrivilege($user) === HouseholdPrivilege::PRIVILEGE_ADMIN) {
            $numberAdmins = $household->getPrivileges()->filter(
                static fn (HouseholdPrivilege $householdPrivilege) => $householdPrivilege->level === HouseholdPrivilege::PRIVILEGE_ADMIN
            )->count();
            if ($numberAdmins === 1) {
                return JsonErrorResponse::create(['reason' => 'You cannot abandon this household as the last admin.',]);
            }
        }
        $household->removeMember($user);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/kick/{id}/{user_id}', name: 'household_kick', methods: ['POST'])]
    public function kickMember(
        Household $household,
        #[MapEntity(expr: "repository.find(user_id)")] User $kicked,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
        $user = $this->getUser();
        if ($user === $kicked) {
            return JsonErrorResponse::create(['reason' => 'You cannot kick yourself.',]);
        }
        if ($household->getUserPrivilege($kicked) === HouseholdPrivilege::PRIVILEGE_ADMIN) {
            return JsonErrorResponse::create(['reason' => 'You cannot kick admins.',]);
        }
        $household->removeMember($kicked);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/privilege/{id}/{user_id}/{privilege}', name: 'household_member_privilege', methods: ['POST'])]
    public function privilege(
        Household $household,
        #[MapEntity(expr: "repository.find(user_id)")] User $targetUser,
        int $privilege,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
        $user = $this->getUser();
        if ($user === $targetUser) {
            return JsonErrorResponse::create(['reason' => 'You cannot change your own privileges!',]);
        }
        if (!$household->getMembers()->contains($targetUser)) {
            return JsonErrorResponse::create(['reason' => 'You cannot change privileges of users that aren\'t members of this household!',]);
        }
        if (!in_array($privilege, HouseholdPrivilege::PRIVILEGES)) {
            return JsonErrorResponse::create(['reason' => 'Invalid privilege given!']);
        }
        if ($household->getUserPrivilege($targetUser) === HouseholdPrivilege::PRIVILEGE_ADMIN) {
            return JsonErrorResponse::create(['reason' => 'You cannot overthrow another admin!']);
        }
        $household->setUserPrivilege($targetUser, $privilege);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/{id}', name: 'delete_household', methods: ['DELETE'])]
    public function deleteHousehold(
        Household $household,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
        $entityManager->remove($household);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }
}
