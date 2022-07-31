<?php

namespace App\Registration;

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
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use App\Entity\User;

class RegistrationFactory
{
    public const TOKEN_LENGTH = 64;

    private EmailValidator $emailValidator;

    public function __construct(
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

    public function createRegistrationFromRequest(Request $request): Registration
    {
        $name = $request->request->get('_name');
        $mail = $request->request->get('_mail');
        $password = $request->request->get('_password');
        // generated possible errors
        $errors = array_map(fn(ConstraintViolationInterface $violation) => $violation->getMessage(), [
            ...$this->validator->validate($password, new NotCompromisedPassword()),
            ...$this->validator->validate($name, new NotBlank()),
            ...$this->validator->validate($mail, new Email()),
        ]);
        if (!$this->emailValidator->isValid($mail, new RFCValidation())) {
            $errors[] = $this->emailValidator->getError();
        }
        if (null !== $this->userRepository->findByMail($mail) || null !== $this->registrationRepository->findByMail($mail)) {
            $errors[] = 'Mail already taken!';
        }
        if (count($errors) > 0) {
            throw new RegistrationException($errors);
        }
        /**
         * There is no way to dependency inject a user-agnostic password hasher in symfony >= 5.
         */
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher(new User('dummy', 'user'));
        $registration = new Registration(
            $this->uuidGenerator->v4(),
            $mail,
            $name,
            $this->random->getRandomString(self::TOKEN_LENGTH),
            $passwordHasher->hash($password),
            $this->clock->now(),
        );
        $this->registrationRepository->save($registration);

        return $registration;
    }
}
