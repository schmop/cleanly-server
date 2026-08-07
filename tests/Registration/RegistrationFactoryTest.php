<?php

declare(strict_types=1);

namespace App\Tests\Registration;

use App\Registration\Entity\Registration;
use App\Registration\RegistrationException;
use App\Registration\RegistrationFactory;
use App\Registration\RegistrationRepository;
use App\Tests\Utils\FakeClock;
use App\User\Entity\User;
use App\User\UserRepository;
use App\Utils\Random;
use App\Utils\UuidGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationFactoryTest extends TestCase
{
    private ValidatorInterface $validator;
    private UuidGenerator $uuid;
    private PasswordHasherFactoryInterface $hasherFactory;
    private RegistrationRepository $registrationRepository;
    private UserRepository $userRepository;
    private Random $random;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->validator = $this->createStub(ValidatorInterface::class);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([]));

        $this->uuid = $this->createStub(UuidGenerator::class);
        $this->uuid->method('v4')->willReturn('reg-uuid');

        $hasher = $this->createStub(PasswordHasherInterface::class);
        $hasher->method('hash')->willReturnCallback(fn(string $p) => 'hashed:' . $p);
        $this->hasherFactory = $this->createStub(PasswordHasherFactoryInterface::class);
        $this->hasherFactory->method('getPasswordHasher')->willReturn($hasher);

        $this->registrationRepository = $this->createMock(RegistrationRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->random = $this->createStub(Random::class);
        $this->random->method('getRandomString')->willReturnCallback(fn(int $n) => str_repeat('x', $n));

        $this->clock = new FakeClock('2024-06-15 12:00:00');
    }

    public function testCreatesUserDirectlyWhenEmailValidationDisabled(): void
    {
        $factory = $this->makeFactory(rejectLeakedPasswords: false, requireEmailValidation: false);

        $this->userRepository->expects($this->once())->method('save')
            ->with($this->callback(function (User $user) {
                return $user->getMail() === 'a@b.com'
                    && $user->getName() === 'Alice'
                    && str_starts_with($user->getPassword(), 'hashed:');
            }));
        $this->registrationRepository->expects($this->never())->method('save');

        $result = $factory->createRegistrationFromRequest($this->request([
            'name' => 'Alice', 'mail' => 'a@b.com', 'password' => 'hunter22',
        ]));

        $this->assertNull($result);
    }

    public function testCreatesPendingRegistrationWhenEmailValidationEnabled(): void
    {
        $factory = $this->makeFactory(rejectLeakedPasswords: false, requireEmailValidation: true);
        $this->registrationRepository->method('findByMail')->willReturn(null);

        $captured = null;
        $this->registrationRepository->expects($this->once())->method('save')
            ->willReturnCallback(function (Registration $r) use (&$captured) {
                $captured = $r;
            });
        $this->userRepository->expects($this->never())->method('save');

        $result = $factory->createRegistrationFromRequest($this->request([
            'name' => 'Alice', 'mail' => 'a@b.com', 'password' => 'hunter22',
        ]));

        $this->assertNotNull($result);
        $this->assertSame($captured, $result);
        $this->assertSame('a@b.com', $result->mail);
        $this->assertSame('Alice', $result->name);
        $this->assertSame(RegistrationFactory::TOKEN_LENGTH, strlen($result->token));
        $this->assertStringStartsWith('hashed:', $result->password);
    }

    public function testReusesExistingRegistrationOnResend(): void
    {
        $factory = $this->makeFactory(rejectLeakedPasswords: false, requireEmailValidation: true);
        $existing = new Registration(
            uuid: 'old-uuid', mail: 'a@b.com', name: 'Alice', token: 'old-token',
            password: 'old-hashed', registratedAt: new \DateTimeImmutable('2024-01-01'),
        );
        $this->registrationRepository->method('findByMail')->willReturn($existing);

        $this->registrationRepository->expects($this->once())->method('save')->with($existing);
        $this->userRepository->expects($this->never())->method('save');

        $result = $factory->createRegistrationFromRequest($this->request([
            'name' => 'Alice', 'mail' => 'a@b.com', 'password' => 'hunter22',
        ]));

        $this->assertSame($existing, $result);
        $this->assertSame('old-token', $result->token, 'token must not be regenerated for resend');
    }

    public function testInvalidEmailIsRejected(): void
    {
        $factory = $this->makeFactory(rejectLeakedPasswords: false, requireEmailValidation: false);
        $this->registrationRepository->expects($this->never())->method('save');
        $this->userRepository->expects($this->never())->method('save');

        $this->expectException(RegistrationException::class);
        $factory->createRegistrationFromRequest($this->request([
            'name' => 'Alice', 'mail' => 'definitely-not-an-email', 'password' => 'hunter22',
        ]));
    }

    public function testMailAlreadyTakenIsRejected(): void
    {
        $factory = $this->makeFactory(rejectLeakedPasswords: false, requireEmailValidation: false);
        $this->userRepository->method('findByMail')->willReturn($this->createStub(User::class));
        $this->registrationRepository->expects($this->never())->method('save');
        $this->userRepository->expects($this->never())->method('save');

        try {
            $factory->createRegistrationFromRequest($this->request([
                'name' => 'Alice', 'mail' => 'taken@b.com', 'password' => 'hunter22',
            ]));
            $this->fail('Expected RegistrationException');
        } catch (RegistrationException $e) {
            $this->assertContains('Mail already taken!', $e->errors);
        }
    }

    public function testLeakedPasswordCheckOnlyRunsWhenEnabled(): void
    {
        $validator = $this->createStub(ValidatorInterface::class);
        $callCount = 0;
        $validator->method('validate')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return new ConstraintViolationList([]);
        });
        // Email validation is off in both factories below, so each run saves a User directly.
        $this->userRepository->expects($this->exactly(2))->method('save');
        $this->registrationRepository->expects($this->never())->method('save');

        $factory = new RegistrationFactory(
            rejectLeakedPasswords: false,
            requireEmailValidation: false,
            validator: $validator,
            uuidGenerator: $this->uuid,
            passwordHasherFactory: $this->hasherFactory,
            registrationRepository: $this->registrationRepository,
            userRepository: $this->userRepository,
            random: $this->random,
            clock: $this->clock,
        );

        $factory->createRegistrationFromRequest($this->request([
            'name' => 'A', 'mail' => 'a@b.com', 'password' => 'p',
        ]));

        // Without leaked-password check there are exactly 2 validate() calls
        // (NotBlank for name, Email for mail).
        $this->assertSame(2, $callCount);

        // With leaked-password check there are 3.
        $callCount = 0;
        $factory2 = new RegistrationFactory(
            rejectLeakedPasswords: true,
            requireEmailValidation: false,
            validator: $validator,
            uuidGenerator: $this->uuid,
            passwordHasherFactory: $this->hasherFactory,
            registrationRepository: $this->registrationRepository,
            userRepository: $this->userRepository,
            random: $this->random,
            clock: $this->clock,
        );
        $factory2->createRegistrationFromRequest($this->request([
            'name' => 'A', 'mail' => 'a@b.com', 'password' => 'p',
        ]));
        $this->assertSame(3, $callCount);
    }

    public function testValidationErrorsAreCollected(): void
    {
        $validator = $this->createStub(ValidatorInterface::class);
        $blank = $this->createStub(ConstraintViolationInterface::class);
        $blank->method('getMessage')->willReturn('Name should not be blank.');
        $validator->method('validate')->willReturn(new ConstraintViolationList([$blank]));
        $this->registrationRepository->expects($this->never())->method('save');
        $this->userRepository->expects($this->never())->method('save');

        $factory = new RegistrationFactory(
            rejectLeakedPasswords: false,
            requireEmailValidation: false,
            validator: $validator,
            uuidGenerator: $this->uuid,
            passwordHasherFactory: $this->hasherFactory,
            registrationRepository: $this->registrationRepository,
            userRepository: $this->userRepository,
            random: $this->random,
            clock: $this->clock,
        );

        try {
            $factory->createRegistrationFromRequest($this->request([
                'name' => '', 'mail' => 'a@b.com', 'password' => 'p',
            ]));
            $this->fail('Expected RegistrationException');
        } catch (RegistrationException $e) {
            $this->assertNotEmpty($e->errors);
            $this->assertContains('Name should not be blank.', $e->errors);
        }
    }

    private function makeFactory(bool $rejectLeakedPasswords, bool $requireEmailValidation): RegistrationFactory
    {
        return new RegistrationFactory(
            rejectLeakedPasswords: $rejectLeakedPasswords,
            requireEmailValidation: $requireEmailValidation,
            validator: $this->validator,
            uuidGenerator: $this->uuid,
            passwordHasherFactory: $this->hasherFactory,
            registrationRepository: $this->registrationRepository,
            userRepository: $this->userRepository,
            random: $this->random,
            clock: $this->clock,
        );
    }

    /** @param array<string, string> $body */
    private function request(array $body): Request
    {
        return new Request(content: json_encode($body, JSON_THROW_ON_ERROR));
    }
}
