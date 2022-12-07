<?php

declare(strict_types = 1)
;

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Push\DeviceRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Push\Entity\Device;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route(path: '/api/push', name: 'push', methods: ['POST'])]
class PushController extends UserAwareController
{
    public function __invoke(Request $request, DeviceRepository $deviceRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        $pushId = $request->request->get('push_id');
        $deviceId = $request->request->get('device_id');
        if (!is_string($pushId) || !is_string($deviceId)) {
            return JsonErrorResponse::create(['error' => 'Push registration invalid!']);
        }
        $device = $deviceRepository->findByDeviceId($deviceId) ?? new Device($deviceId, $pushId, $user);
        $device->setPushId($pushId);
        $entityManager->persist($device);
        $entityManager->flush();

        return JsonSuccessResponse::create();
    }
}
