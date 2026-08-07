<?php

namespace App\Controller;

use App\HttpFoundation\HtmlResponse;
use App\Persistence\PersistenceException;
use App\User\Entity\User;
use App\PasswordReset\ChangePasswordFormType;
use App\PasswordReset\ResetPasswordRequestFormType;
use App\Template\TemplateRenderException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Exception\LogicException as FormLogicException;
use Symfony\Component\Form\Exception\OutOfBoundsException as FormOutOfBoundsException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Display & process form to request a password reset.
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer, LoggerInterface $logger): Response
    {
        try {
            $form = $this->createForm(ResetPasswordRequestFormType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $address = $form->get('mail')->getData();
                if (!is_string($address)) {
                    return HtmlResponse::withStatus('Invalid form data', Response::HTTP_BAD_REQUEST);
                }
                return $this->processSendingPasswordResetEmail(
                    $address,
                    $mailer
                );
            }

            return TemplateRenderException::wrap(fn() => $this->render('reset_password/request.html.twig', [
                'requestForm' => $form->createView(),
            ]));
        } catch (TemplateRenderException | TransportExceptionInterface | FormLogicException | FormOutOfBoundsException $e) {
            $logger->error('Reset password request failed', ['exception' => $e]);
            return HtmlResponse::serverError();
        }
    }

    /**
     * Confirmation page after a user has requested a password reset.
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(LoggerInterface $logger): Response
    {
        try {
            // Generate a fake token if the user does not exist or someone hit this page directly.
            // This prevents exposing whether a user was found with the given email address or not
            if (null === ($resetToken = $this->getTokenObjectFromSession())) {
                $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
            }

            return TemplateRenderException::wrap(fn() => $this->render('reset_password/check_email.html.twig', [
                'resetToken' => $resetToken,
            ]));
        } catch (TemplateRenderException $e) {
            $logger->error('Reset password check-email render failed', ['exception' => $e]);
            return HtmlResponse::serverError();
        }
    }

    /**
     * Confirmation page after a user has requested a password reset.
     */
    #[Route('/reset-success', name: 'app_reset_success')]
    public function resetSuccess(LoggerInterface $logger): Response
    {
        try {
            return TemplateRenderException::wrap(fn() => $this->render('reset_password/reset_success.html.twig'));
        } catch (TemplateRenderException $e) {
            $logger->error('Reset password success render failed', ['exception' => $e]);
            return HtmlResponse::serverError();
        }
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $userPasswordHasher, LoggerInterface $logger, ?string $token = null): Response
    {
        try {
            if ($token) {
                // We store the token in session and remove it from the URL, to avoid the URL being
                // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
                $this->storeTokenInSession($token);

                return $this->redirectToRoute('app_reset_password');
            }

            $token = $this->getTokenFromSession();
            if (null === $token) {
                return HtmlResponse::withStatus('No reset password token found in the URL or in the session.', Response::HTTP_NOT_FOUND);
            }

            try {
                $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
            } catch (ResetPasswordExceptionInterface $e) {
                $this->addFlash('reset_password_error', sprintf(
                    '%s - %s',
                    ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE,
                    $e->getReason()
                ));

                return $this->redirectToRoute('app_forgot_password_request');
            }

            // The token is valid; allow the user to change their password.
            $form = $this->createForm(ChangePasswordFormType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $plainPassword = $form->get('plainPassword')->getData();
                if (!is_string($plainPassword) || !($user instanceof User)) {
                    $this->addFlash('reset_password_error', sprintf(
                        '%s - %s',
                        ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE,
                        'The password is invalid or your user is invalid',
                    ));

                    return $this->redirectToRoute('app_forgot_password_request');
                }
                // A password reset token should be used only once, remove it.
                $this->resetPasswordHelper->removeResetRequest($token);

                // Encode(hash) the plain password, and set it.
                $encodedPassword = $userPasswordHasher->hashPassword(
                    $user,
                    $plainPassword,
                );

                $user->setPassword($encodedPassword);
                PersistenceException::flush($this->entityManager);

                // The session is cleaned up after the password has been changed.
                $this->cleanSessionAfterReset();

                return $this->redirectToRoute('app_reset_success');
            }

            return TemplateRenderException::wrap(fn() => $this->render('reset_password/reset.html.twig', [
                'resetForm' => $form->createView(),
            ]));
        } catch (TemplateRenderException | PersistenceException | NotFoundHttpException | FormLogicException | FormOutOfBoundsException | \LogicException $e) {
            $logger->error('Reset password failed', ['exception' => $e]);
            return HtmlResponse::serverError();
        }
    }

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'mail' => $emailFormData,
        ]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            // If you want to tell the user why a reset email was not sent, uncomment
            // the lines below and change the redirect to 'app_forgot_password_request'.
            // Caution: This may reveal if a user is registered or not.
            //
            // $this->addFlash('reset_password_error', sprintf(
            //     '%s - %s',
            //     ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE,
            //     $e->getReason()
            // ));

            return $this->redirectToRoute('app_check_email');
        }

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@schmoppo.de', 'Cleanly Bot'))
            ->to($user->getMail())
            ->subject('Your password reset request')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ])
        ;

        $mailer->send($email);

        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
