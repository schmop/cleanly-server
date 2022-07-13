<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Household;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\HttpFoundation\JsonErrorResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Todo\Entity\Todo;
use App\HttpFoundation\JsonSuccessResponse;
use Symfony\Component\Routing\Annotation\Route;

class TodoController extends AbstractController
{
    /**
     * @Route("/api/household/update-checklist/{id}", name="household_update_checklist", methods={"POST"})
     */
    public function updateChecklist(
        Household $household,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        // @TODO: Use a service for this logic
        $user = $this->getUser();
        if (!$household->getMembers()->contains($user)) {
            return JsonErrorResponse::create(['reason' => 'You cannot edit this checklist, you are not a member of this household!',]);
        }
        try {
            $todos = json_decode($request->request->get('todos', '[]'), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            return JsonErrorResponse::create(['reason' => 'Given checklist was invalid, ' . $e->getMessage(),]);
        }
        $weight = 0;
        $todos = array_map(function (array $rawTodo) use ($household, &$weight) {
            return Todo::createFromData($rawTodo, $weight++, $household);
        }, $todos);
        // Some were removed
        foreach ($household->getChecklist() as $todo) {
            foreach ($todos as $newTodo) {
                if ($todo->getUuid() === $newTodo->getUuid()) {
                    continue 2;
                }
            }
            $household->getChecklist()->removeElement($todo);
        }
        
        // some are new
        foreach ($todos as $newTodo) {
            foreach ($household->getChecklist() as $todo) {
                if ($newTodo->getUuid() === $todo->getUuid()) {
                    $todo->setContent($newTodo->getContent());
                    $todo->setWeight($newTodo->getWeight());
                    continue 2;
                }
            }
            $entityManager->persist($newTodo);
        }
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }
}
