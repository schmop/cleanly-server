<?php

namespace App\Registration;

use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Registration\Entity\Registration;
use App\Utils\Clock;
use App\Utils\UuidGenerator;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use App\Utils\Random;
use App\User\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use App\User\Entity\User;

use function Lambdish\Phunctional\map;

class RegistrationFactory
{
    public const TOKEN_LENGTH = 64;

    private EmailValidator $emailValidator;

    public function __construct(
        private readonly bool $rejectLeakedPasswords,
        private readonly bool $requireEmailValidation,
        private readonly ValidatorInterface $validator,
        private readonly UuidGenerator $uuidGenerator,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly RegistrationRepository $registrationRepository,
        private readonly UserRepository $userRepository,
        private readonly Random $random,
        private readonly Clock $clock,
    ) {
        $this->emailValidator = new EmailValidator();
    }

    /**
     * @throws RegistrationException
     * @throws UnexpectedJsonException
     *
     * @return Registration|null Returns null if no email validation is required. Therefore, no registration entity is created and the user is created directly.
     * @throws \RuntimeException
     * @throws \Symfony\Component\PasswordHasher\Exception\InvalidPasswordException
     * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @throws \Symfony\Component\Validator\Exception\InvalidOptionsException
     * @throws \Symfony\Component\Validator\Exception\MissingOptionsException
     */
    public function createRegistrationFromRequest(Request $request): ?Registration
    {
        $json = Json::fromRequest($request);
        $name = $json->string('name');
        $mail = $json->string('mail');
        $password = $json->string('password');
        $violations = [
            ...$this->validator->validate($name, new NotBlank()),
            ...$this->validator->validate($mail, new Email()),
        ];
        if ($this->rejectLeakedPasswords) {
            $violations = [
                ...$this->validator->validate($password, new NotCompromisedPassword()),
                ...$violations,
            ];
        }
        // generated possible errors
        $errors = map(fn (ConstraintViolationInterface $violation) => $violation->getMessage(), $violations);
        if (!$this->emailValidator->isValid($mail, new RFCValidation())) {
            $error = $this->emailValidator->getError()?->reason()?->description();
            if (is_string($error)) {
                $errors[] = $error;
            }
        }
        if (null !== $this->userRepository->findByMail($mail)) {
            $errors[] = 'Mail already taken!';
        }
        if (count($errors) > 0) {
            throw new RegistrationException($errors);
        }

        /**
         * There is no way to dependency inject a user-agnostic password hasher in symfony >= 5.
         */
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher(new User('dummy', 'user'));

        if (!$this->requireEmailValidation) {
            $user = new User($mail, $name);
            $user->setPassword($passwordHasher->hash($password));
            $this->userRepository->save($user);

            return null;
        }

        $registration = $this->registrationRepository->findByMail($mail) ?? new Registration(
            $this->uuidGenerator->v4(),
            $mail,
            $name,
            $this->random->getRandomString(self::TOKEN_LENGTH),
            $passwordHasher->hash($password),
            $this->clock->now(),
        ); // allow resend of registration mails
        $this->registrationRepository->save($registration);

        return $registration;
    }
}
