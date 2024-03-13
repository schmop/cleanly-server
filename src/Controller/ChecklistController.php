<?php

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\HouseholdVoter;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Todo\ChecklistRepository;
use App\Todo\Entity\Checklist;
use App\Todo\TodoEvent;
use App\Todo\TodoEventProcessor;
use App\Todo\TodoPublisher;
use App\Utils\UuidGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use function Lambdish\Phunctional\map;

class ChecklistController extends UserAwareController
{
    #[Route(path: '/api/household/checklist/{uuid}/update', name: 'household_checklist_update', methods: ['POST'])]
    public function updateChecklist(
        Checklist          $checklist,
        Request            $request,
        TodoEventProcessor $todoEventProcessor,
        TodoPublisher      $todoPublisher,
    ): JsonResponse {
        $household = $checklist->getHousehold();
        $this->denyAccessUnlessGranted(HouseholdVoter::EDIT_CHECKLISTS, $household);
        try {
            $rawEvents = Json::fromRequest($request)->jsonArray('events');
            $events = map(fn (Json $rawEvent) => TodoEvent::createFromJson($rawEvent), $rawEvents);
            $todoEventProcessor->process($events, $checklist);
            $todoPublisher->publish($this->getUser(), $events, $checklist);
        } catch (\Exception $e) {
            return JsonErrorResponse::create(['reason' => 'Edits on the checklist were invalid, ' . $e->getMessage(), 'trace' => $e->getTrace()]);
        }

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/checklist/{uuid}/rename', name: 'household_checklist_rename', methods: ['POST'])]
    public function renameChecklist(
        Checklist           $checklist,
        Request             $request,
        ChecklistRepository $checklistRepository,
    ): JsonResponse {
        $household = $checklist->getHousehold();
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_CHECKLISTS, $household);
        try {
            $checklist->setName(Json::fromRequest($request)->string('name'));
            $checklistRepository->save($checklist);
        } catch (UnexpectedJsonException $e) {
            return JsonErrorResponse::create(['reason' => 'No name given!', 'error' => $e->getTrace()]);
        }

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/checklist/{uuid}', name: 'household_checklist_remove', methods: ['DELETE'])]
    public function removeChecklist(
        Checklist           $checklist,
        ChecklistRepository $checklistRepository,
    ): JsonResponse {
        $household = $checklist->getHousehold();
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_CHECKLISTS, $household);
        $household->getChecklists()->removeElement($checklist);
        $checklistRepository->remove($checklist);

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/{id}/checklist/add', name: 'household_checklist_add', methods: ['PUT'])]
    public function addChecklist(
        Household           $household,
        UuidGenerator       $uuidGenerator,
        ChecklistRepository $checklistRepository,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_CHECKLISTS, $household);
        $checklist = new Checklist($uuidGenerator->v4(), 'New Checklist', $household);
        $household->getChecklists()->add($checklist);
        $checklistRepository->save($checklist);

        return JsonSuccessResponse::create(['uuid' => $checklist->getUuid()]);
    }
}
