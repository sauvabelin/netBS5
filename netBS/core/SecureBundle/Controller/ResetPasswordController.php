<?php

namespace NetBS\SecureBundle\Controller;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Event\PasswordResetCompletedEvent;
use NetBS\SecureBundle\Event\PasswordResetRequestedEvent;
use NetBS\SecureBundle\Form\ChangePasswordType;
use NetBS\SecureBundle\Form\ResetPasswordRequestFormType;
use NetBS\SecureBundle\Model\ChangePassword;
use NetBS\SecureBundle\Repository\ResetPasswordRequestRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly ResetPasswordRequestRepository $resetPasswordRequests,
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly MailerInterface $mailer,
        #[Autowire(service: 'limiter.password_reset_request')]
        private readonly RateLimiterFactory $ipLimiter,
        #[Autowire(service: 'limiter.password_reset_user')]
        private readonly RateLimiterFactory $userLimiter,
        #[Autowire(service: 'monolog.logger.security')]
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::MAILER_FROM)%')]
        private readonly ?string $fromAddress = null,
    ) {}

    #[Route('/forgot-password', name: 'netbs.secure.password_reset.request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->dispatchResetEmail($form->get('username')->getData(), $request);
            return $this->redirectToRoute('netbs.secure.password_reset.check_email');
        }

        return $this->render('@NetBSSecure/reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/forgot-password/check-email', name: 'netbs.secure.password_reset.check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('@NetBSSecure/reset_password/check_email.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'netbs.secure.password_reset.reset_form', methods: ['GET'], requirements: ['token' => '[A-Za-z0-9_\-]+'])]
    public function handleToken(string $token): Response
    {
        $this->storeTokenInSession($token);
        return $this->redirectToRoute('netbs.secure.password_reset.reset');
    }

    #[Route('/reset-password', name: 'netbs.secure.password_reset.reset', methods: ['GET', 'POST'])]
    public function reset(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $token = $this->getTokenFromSession();
        if (!$token) {
            $this->addFlash('error', 'Lien de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('netbs.secure.password_reset.request');
        }

        try {
            /** @var BSUser $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->logger->info('password_reset.token_rejected', [
                'reason' => $e::class,
                'detail' => $e->getReason(),
                'ip' => $request->getClientIp(),
            ]);
            $this->addFlash('error', 'Lien de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('netbs.secure.password_reset.request');
        }

        $form = $this->createForm(ChangePasswordType::class, new ChangePassword(), [
            'require_current' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);
            $plain = $form->getData()->getNewPassword();
            $user->setPassword($hasher->hashPassword($user, $plain));
            $user->setPasswordChangedAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->cleanSessionAfterReset();
            $this->dispatcher->dispatch(new PasswordResetCompletedEvent($user, $request->getClientIp()));
            $this->addFlash('success', 'Mot de passe mis à jour. Veuillez vous connecter.');
            return $this->redirectToRoute('netbs.secure.login.login');
        }

        return $this->render('@NetBSSecure/reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function dispatchResetEmail(string $username, Request $request): void
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $ipLimit = $this->ipLimiter->create($ip)->consume(1);
        if (!$ipLimit->isAccepted()) {
            $this->logger->info('password_reset.ip_rate_limited', [
                'ip' => $ip,
                'retry_after' => $ipLimit->getRetryAfter()->format(DATE_ATOM),
            ]);
            $this->jitter();
            return;
        }

        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $username]);
        if (!$user) {
            $this->jitter();
            return;
        }
        if (!$user->getEmail()) {
            $this->logger->info('password_reset.user_has_no_email', ['user_id' => $user->getId()]);
            $this->jitter();
            return;
        }
        if (!$user->getIsActive()) {
            $this->logger->info('password_reset.user_inactive', ['user_id' => $user->getId()]);
            $this->jitter();
            return;
        }

        $userLimit = $this->userLimiter->create((string) $user->getId())->consume(1);
        if (!$userLimit->isAccepted()) {
            $this->logger->info('password_reset.user_rate_limited', [
                'user_id' => $user->getId(),
                'retry_after' => $userLimit->getRetryAfter()->format(DATE_ATOM),
            ]);
            $this->jitter();
            return;
        }

        // GitHub-style: drop any pending tokens for this user before issuing a
        // fresh one, so a re-submit (e.g. user lost the first email) always
        // produces a new working link. Anti-abuse is the per-IP + per-user
        // limiters above.
        $this->resetPasswordRequests->removeRequests($user);

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (TooManyPasswordRequestsException $e) {
            $this->logger->info('password_reset.bundle_throttled', [
                'user_id' => $user->getId(),
                'available_at' => $e->getAvailableAt()->format(DATE_ATOM),
            ]);
            $this->jitter();
            return;
        } catch (ResetPasswordExceptionInterface $e) {
            $this->logger->error('password_reset.token_generation_failed', [
                'user_id' => $user->getId(),
                'reason' => $e::class,
                'detail' => $e->getReason(),
            ]);
            $this->jitter();
            return;
        }

        $email = (new Email())
            ->from(new Address($this->fromAddress ?? 'no-reply@netbs.localhost', 'netBS'))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe netBS')
            ->html($this->renderView('@NetBSSecure/reset_password/email.html.twig', [
                'resetToken' => $resetToken,
                'user' => $user,
            ]));

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Swallow + log so a transport outage doesn't turn into a 500 for
            // real users while unknown-username requests stay clean — that
            // status-code divergence would itself be a username-enumeration
            // signal.
            $this->logger->error('password_reset.mail_transport_failed', [
                'user_id' => $user->getId(),
                'exception' => $e->getMessage(),
            ]);
            // generateResetToken() already committed the token row; since no email
            // went out, drop it so we don't leave a live but unusable token behind
            // (and so a failed send is indistinguishable from the unknown-user path).
            $this->resetPasswordRequests->removeRequests($user);
            return;
        }

        $this->dispatcher->dispatch(new PasswordResetRequestedEvent($user, $request->getClientIp()));
    }

    private function jitter(): void
    {
        usleep(random_int(0, 80_000));
    }
}
