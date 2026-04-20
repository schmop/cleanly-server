<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\Household\Entity\HouseholdPrivilege;
use App\Household\HouseholdVoter;
use App\Household\NotInHouseholdException;
use App\Household\ReassignmentStrategy;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Invite\InvitePublisher;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Persistence\PersistenceException;
use App\Push\Pusher;
use App\User\Entity\User;
use App\User\UserRepository;
use App\Utils\Base64UrlInterface;
use App\Webhook\WebhookSecretGenerator;
use App\Webhook\WebhookValidator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class HouseholdController extends UserAwareController
{
    #[Route(path: '/api/household/create', name: 'create_household', methods: ['POST'])]
    public function createHouseHold(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger): JsonResponse
    {
        try {
            $user = $this->getUser();
            $household = Household::createFromRequest($request, $user);
            PersistenceException::persistAndFlush($entityManager, $household);

            return JsonSuccessResponse::create();
        } catch (PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to create household');
        }
    }

    #[Route(path: '/api/household/invite/{id}', name: 'household_invite', methods: ['POST'])]
    public function invite(
        Household              $household,
        EntityManagerInterface $entityManager,
        UserRepository         $userRepository,
        Base64UrlInterface     $base64Url,
        Request                $request,
        InvitePublisher        $invitePublisher,
        Pusher                 $pusher,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
            $user = $this->getUser();
            $data = Json::fromRequest($request);
            $ids = $data->intArray('ids');
            $invitees = $userRepository->findBy(['id' => $ids]);
            foreach ($invitees as $invitee) {
                try {
                    $inviteToken = new HouseholdInvite($base64Url->encode(random_bytes(32)), $household, $invitee, $user);
                } catch (\Exception $e) {
                    return JsonErrorResponse::create(['reason' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $entityManager->persist($inviteToken);
                $invitePublisher->publish($inviteToken);
            }
            PersistenceException::flush($entityManager);
            $pusher->publishInvites($user, $invitees, $household);

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | UnexpectedJsonException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to send household invite');
        }
    }

    #[Route(path: '/api/household/join-by-token/{token}', name: 'household_join')]
    public function join(
        HouseholdInvite        $invite,
        EntityManagerInterface $entityManager,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
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
            PersistenceException::flush($entityManager);

            return JsonSuccessResponse::create();
        } catch (PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to join household via token');
        }
    }


    #[Route(path: '/api/household/accept-invite/{id}', name: 'household_accept_invite')]
    public function acceptInvite(
        Household              $household,
        EntityManagerInterface $entityManager,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
            $user = $this->getUser();
            $invites = $household->getInvites();
            foreach ($invites as $invite) {
                if ($invite->getInvitee() === $user) {
                    $household->addMember($user);
                    $entityManager->remove($invite);
                    $entityManager->persist($household);
                    PersistenceException::flush($entityManager);

                    return JsonSuccessResponse::create([
                        'household' => $household->jsonSerialize()
                    ]);
                }
            }

            return JsonErrorResponse::create(['reason' => 'You did not receive an invite to this household!']);
        } catch (PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to accept household invite');
        }
    }

    #[Route(path: '/api/household/decline-invite/{id}', name: 'household_decline_invite')]
    public function declineInvite(
        Household              $household,
        EntityManagerInterface $entityManager,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
            $user = $this->getUser();
            $invites = $household->getInvites();
            foreach ($invites as $invite) {
                if ($invite->getInvitee() === $user) {
                    $entityManager->remove($invite);
                    PersistenceException::flush($entityManager);

                    return JsonSuccessResponse::create();
                }
            }

            return JsonErrorResponse::create(['reason' => 'You did not receive an invite to this household!']);
        } catch (PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to decline household invite');
        }
    }

    #[Route(path: '/api/household/leave/{id}', name: 'household_leave', methods: ['POST'])]
    public function leaveHousehold(
        Household              $household,
        EntityManagerInterface $entityManager,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
            $user = $this->getUser();
            if (!$household->getMembers()->contains($user)) {
                return JsonErrorResponse::create(['reason' => 'Cannot leave, you are not a member.',]);
            }

            if ($household->getUserPrivilege($user) === HouseholdPrivilege::PRIVILEGE_ADMIN) {
                $numberAdmins = $household->getPrivileges()->filter(
                    static fn(HouseholdPrivilege $householdPrivilege) => $householdPrivilege->level === HouseholdPrivilege::PRIVILEGE_ADMIN
                )->count();
                if ($numberAdmins === 1) {
                    return JsonErrorResponse::create(['reason' => 'You cannot abandon this household as the last admin.',]);
                }
            }
            $household->removeMember($user);
            PersistenceException::flush($entityManager);

            return JsonSuccessResponse::create();
        } catch (NotInHouseholdException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to leave household');
        }
    }

    #[Route(path: '/api/household/kick/{id}/{user_id}', name: 'household_kick', methods: ['POST'])]
    public function kickMember(
        Household                                           $household,
        #[MapEntity(expr: "repository.find(user_id)")] User $kicked,
        EntityManagerInterface                              $entityManager,
        LoggerInterface                                     $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
            $user = $this->getUser();
            if ($user === $kicked) {
                return JsonErrorResponse::create(['reason' => 'You cannot kick yourself.',]);
            }
            if ($household->getUserPrivilege($kicked) === HouseholdPrivilege::PRIVILEGE_ADMIN) {
                return JsonErrorResponse::create(['reason' => 'You cannot kick admins.',]);
            }
            $household->removeMember($kicked);
            PersistenceException::flush($entityManager);

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | NotInHouseholdException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to kick household member');
        }
    }

    #[Route(path: '/api/household/privilege/{id}/{user_id}/{privilege}', name: 'household_member_privilege', methods: ['POST'])]
    public function privilege(
        Household                                           $household,
        #[MapEntity(expr: "repository.find(user_id)")] User $targetUser,
        int                                                 $privilege,
        EntityManagerInterface                              $entityManager,
        LoggerInterface                                     $logger,
    ): JsonResponse {
        try {
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
            PersistenceException::flush($entityManager);

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | NotInHouseholdException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to change member privilege');
        }
    }

    #[Route(path: '/api/household/{id}', name: 'delete_household', methods: ['DELETE'])]
    public function deleteHousehold(
        Household              $household,
        EntityManagerInterface $entityManager,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
            $entityManager->remove($household);
            PersistenceException::flush($entityManager);

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | PersistenceException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to delete household');
        }
    }

    #[Route(path: '/api/household/webhook/{id}', name: 'household_webhook', methods: ['POST'])]
    public function setWebhook(
        Household              $household,
        EntityManagerInterface $entityManager,
        WebhookValidator       $webhookValidator,
        WebhookSecretGenerator $webhookSecretGenerator,
        Request                $request,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
            $data = Json::fromRequest($request);
            $url = $data->string('webhook_url');
            if (!$webhookValidator->isWebhookUrlValid($url)) {
                return JsonErrorResponse::create(['reason' => 'Invalid domain given for webhook. Domain needs to match this format: "https://<domain-with-subdomains>"',]);
            }
            $household->setWebhookSecret($webhookSecretGenerator->generate());
            $household->setWebhookUrl($url);
            PersistenceException::flush($entityManager);

            return JsonSuccessResponse::create([
                'secret' => $household->getWebhookSecret(),
            ]);
        } catch (AccessDeniedException | UnexpectedJsonException | PersistenceException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to set webhook');
        }
    }

    #[Route(path: '/api/household/reassignment-strategy/{id}', name: 'household_reassignment_strategy', methods: ['POST'])]
    public function setReassignmentStrategy(
        Household              $household,
        EntityManagerInterface $entityManager,
        Request                $request,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_HOUSEHOLD, $household);
            try {
                $data = Json::fromRequest($request);
                $strategy = ReassignmentStrategy::from($data->string('reassignmentStrategy'));
            } catch (UnexpectedJsonException|\ValueError $e) {
                return JsonErrorResponse::create([
                    'reason' => 'Invalid data given, "reassign_strategy" is required!',
                ]);
            }
            $household->setReassignmentStrategy($strategy);
            PersistenceException::flush($entityManager);

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | PersistenceException | \TypeError $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to set reassignment strategy');
        }
    }
}
