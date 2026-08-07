<?php

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\HouseholdVoter;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Persistence\PersistenceException;
use App\RankSort\ItemSorter;
use App\Todo\ChecklistFactory;
use App\Todo\ChecklistRepository;
use App\Todo\ChecklistUpdateNotifier;
use App\Todo\Entity\Checklist;
use App\Todo\InconsistentChecklistEventException;
use App\Todo\TodoEvent;
use App\Todo\TodoEventProcessor;
use App\Todo\TodoPublisher;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use function Lambdish\Phunctional\map;

class ChecklistController extends UserAwareController
{
    #[Route(path: '/api/household/checklist/{uuid}/update', name: 'household_checklist_update', methods: ['POST'])]
    public function updateChecklist(
        #[MapEntity(id: 'uuid')]
        Checklist               $checklist,
        Request                 $request,
        TodoEventProcessor      $todoEventProcessor,
        ChecklistUpdateNotifier $checklistUpdateNotifier,
        TodoPublisher           $todoPublisher,
        LoggerInterface         $logger,
    ): JsonResponse {
        try {
            $household = $checklist->getHousehold();
            $this->denyAccessUnlessGranted(HouseholdVoter::EDIT_CHECKLISTS, $household);
            try {
                $rawEvents = Json::fromRequest($request)->jsonArray('events');
                $events = map(fn(Json $rawEvent) => TodoEvent::createFromJson($rawEvent), $rawEvents);
                $todoEventProcessor->process($events, $checklist);
                $checklistUpdateNotifier->notify($this->getUser(), $checklist);
                $todoPublisher->publish($this->getUser(), $events, $checklist);
            } catch (UnexpectedJsonException | InconsistentChecklistEventException | PersistenceException | \DateMalformedStringException $e) {
                return JsonErrorResponse::create(['reason' => 'Edits on the checklist were invalid, ' . $e->getMessage(), 'trace' => $e->getTrace()]);
            }

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to update checklist');
        }
    }

    #[Route(path: '/api/household/checklist/{uuid}/rename', name: 'household_checklist_rename', methods: ['POST'])]
    public function renameChecklist(
        #[MapEntity(id: 'uuid')]
        Checklist           $checklist,
        Request             $request,
        ChecklistRepository $checklistRepository,
        LoggerInterface     $logger,
    ): JsonResponse {
        try {
            $household = $checklist->getHousehold();
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_CHECKLISTS, $household);
            try {
                $checklist->setName(Json::fromRequest($request)->string('name'));
                $checklistRepository->save($checklist);
            } catch (UnexpectedJsonException $e) {
                return JsonErrorResponse::create(['reason' => 'No name given!', 'error' => $e->getTrace()]);
            }

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | PersistenceException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to rename checklist');
        }
    }

    #[Route(path: '/api/household/checklist/{uuid}/move', name: 'household_checklist_move', methods: ['POST'])]
    public function moveChecklist(
        #[MapEntity(id: 'uuid')]
        Checklist           $checklist,
        Request             $request,
        ChecklistRepository $checklistRepository,
        LoggerInterface     $logger,
    ): JsonResponse {
        try {
            $household = $checklist->getHousehold();
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_CHECKLISTS, $household);
            try {
                $moveAfterUuid = Json::fromRequest($request)->tryString('moveAfterUuid');
                $checklistSorter = new ItemSorter($checklistRepository);
                $checklistSorter->moveAfter($checklist, $moveAfterUuid);
            } catch (UnexpectedJsonException $e) {
                $logger->error('Invalid movement given!', ['exception' => $e]);
                return JsonErrorResponse::create(['reason' => 'Invalid movement given!', 'error' => $e->getTrace()]);
            }

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to move checklist');
        }
    }

    #[Route(path: '/api/household/checklist/{uuid}', name: 'household_checklist_remove', methods: ['DELETE'])]
    public function removeChecklist(
        #[MapEntity(id: 'uuid')]
        Checklist           $checklist,
        ChecklistRepository $checklistRepository,
        LoggerInterface     $logger,
    ): JsonResponse {
        try {
            $household = $checklist->getHousehold();
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_CHECKLISTS, $household);
            $household->getChecklists()->removeElement($checklist);
            $checklistRepository->remove($checklist);

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | PersistenceException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to remove checklist');
        }
    }

    #[Route(path: '/api/household/{id}/checklist/add', name: 'household_checklist_add', methods: ['PUT'])]
    public function addChecklist(
        Household           $household,
        ChecklistRepository $checklistRepository,
        ChecklistFactory    $checklistFactory,
        LoggerInterface     $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_CHECKLISTS, $household);
            $checklist = $checklistFactory->create($household);
            $household->getChecklists()->add($checklist);
            $checklistRepository->save($checklist);

            return JsonSuccessResponse::create(['uuid' => $checklist->getUuid()]);
        } catch (AccessDeniedException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to add checklist');
        }
    }

    #[Route(path: '/api/household/checklist/{uuid}/subscribe', name: 'household_checklist_subscribe', methods: ['POST'])]
    public function subscribeToChecklistUpdates(
        #[MapEntity(id: 'uuid')]
        Checklist           $checklist,
        ChecklistRepository $checklistRepository,
        LoggerInterface     $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::EDIT_CHECKLISTS, $checklist->getHousehold());
            $checklist->getSubscribers()->add($this->getUser());
            $checklistRepository->save($checklist);

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to subscribe to checklist');
        }
    }

    #[Route(path: '/api/household/checklist/{uuid}/unsubscribe', name: 'household_checklist_unsubscribe', methods: ['POST'])]
    public function unsubscribeToChecklistUpdates(
        #[MapEntity(id: 'uuid')]
        Checklist           $checklist,
        ChecklistRepository $checklistRepository,
        LoggerInterface     $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::EDIT_CHECKLISTS, $checklist->getHousehold());
            $checklist->getSubscribers()->removeElement($this->getUser());
            $checklistRepository->save($checklist);

            return JsonSuccessResponse::create();
        } catch (AccessDeniedException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to unsubscribe from checklist');
        }
    }
}
