<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Household\Entity\Household;
use Symfony\Component\HttpFoundation\Request;
use App\HttpFoundation\JsonErrorResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Json;
use Symfony\Component\Routing\Annotation\Route;
use App\Todo\TodoEvent;
use App\Todo\TodoEventProcessor;
use App\Todo\TodoPublisher;

use function Lambdish\Phunctional\map;

class TodoController extends UserAwareController
{
    #[Route(path: '/api/household/update-checklist/{id}', name: 'household_update_checklist', methods: ['POST'])]
    public function updateChecklist(
        Household $household,
        Request $request,
        TodoEventProcessor $todoEventProcessor,
        TodoPublisher $todoPublisher,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$household->getMembers()->contains($user)) {
            return JsonErrorResponse::create(['reason' => 'You cannot edit this checklist, you are not a member of this household!',]);
        }
        try {
            $rawEvents = Json::fromRequest($request)->jsonArray('events');
            $events = map(fn (Json $rawEvent) => TodoEvent::createFromJson($rawEvent), $rawEvents);
            $todoEventProcessor->process($events, $household);
            $todoPublisher->publish($user, $events, $household);
        } catch (\Exception $e) {
            return JsonErrorResponse::create(['reason' => 'Edits on the checklist were invalid, ' . $e->getMessage(), 'trace' => $e->getTrace()]);
        }

        return JsonSuccessResponse::create();
    }
}
