<?php

declare(strict_types = 1)
;

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Push\DeviceRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Push\Entity\Device;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/api/push", "push", methods={"POST"})
 */
class PushController extends AbstractController
{
    public function __invoke(Request $request, DeviceRepository $deviceRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        $pushId = $request->request->get('push_id');
        $deviceId = $request->request->get('device_id');
        if (null === $pushId || null === $deviceId) {
            return JsonErrorResponse::create(['error' => 'Push registration invalid!']);
        }
        $device = $deviceRepository->findByDeviceId($deviceId) ?? new Device($deviceId, $pushId, $user);
        $entityManager->persist($device);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }
}
