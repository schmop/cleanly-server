<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Auth\Entity\RefreshToken;
use App\Auth\RefreshTokenCreator;
use App\EventListener\AttachRefreshTokenOnSuccessListener;
use App\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AttachRefreshTokenOnSuccessListenerTest extends TestCase
{
    public function testAttachesRefreshTokenForUserEntity(): void
    {
        $user = new User('alice@test', 'Alice');
        $user->setPassword('irrelevant');

        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('getToken')->willReturn('the-refresh-token');

        $creator = $this->createMock(RefreshTokenCreator::class);
        $creator->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn($refreshToken);

        $event = new AuthenticationSuccessEvent(['token' => 'jwt'], $user, new \Symfony\Component\HttpFoundation\Response());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $listener = new AttachRefreshTokenOnSuccessListener($creator, $logger);
        $listener->attachRefreshToken($event);

        $data = $event->getData();
        $this->assertSame('jwt', $data['token']);
        $this->assertSame('the-refresh-token', $data['refresh_token']);
    }

    public function testWarnsAndSkipsForNonAppUser(): void
    {
        // Anything that isn't an `App\User\Entity\User` (e.g. an in-memory user
        // from another firewall) must be ignored — we have no DB row to bind a
        // refresh token to.
        $foreignUser = new InMemoryUser('not-our-user', 'irrelevant');

        $creator = $this->createMock(RefreshTokenCreator::class);
        $creator->expects($this->never())->method('create');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $event = new AuthenticationSuccessEvent(['token' => 'jwt'], $foreignUser, new \Symfony\Component\HttpFoundation\Response());

        $listener = new AttachRefreshTokenOnSuccessListener($creator, $logger);
        $listener->attachRefreshToken($event);

        $this->assertArrayNotHasKey('refresh_token', $event->getData());
    }
}
