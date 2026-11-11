<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Event\UserPasswordChangeEvent;
use NetBS\SecureBundle\EventSubscriber\FirstLoginGuard;
use NetBS\SecureBundle\Form\FirstLoginType;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Model\ChangePassword;
use NetBS\SecureBundle\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The only page an account with a temporary password may open ({@see FirstLoginGuard}):
 * the user chooses their own password, optionally gives an email, then continues to where
 * they were heading — a service sign-in included.
 */
class FirstLoginController extends AbstractController
{
    #[Route('/first-login', name: FirstLoginGuard::ROUTE, methods: ['GET', 'POST'])]
    public function firstLoginAction(
        Request $request,
        UserManager $manager,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        EventDispatcherInterface $dispatcher,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof BaseUser || !$user->mustChangePassword()) {
            return $this->redirectToRoute('netbs.core.home.dashboard');
        }

        $form = $this->createForm(FirstLoginType::class, new ChangePassword(), ['current_email' => $user->getEmail()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = trim((string) $form->get('email')->getData()) ?: null;

            // Checked before touching the user: the logged-in user is saved back into the
            // session at the end of the request, even when nothing was flushed.
            if ($email !== null && $this->emailTakenByAnother($em, $user, $email)) {
                $form->get('email')->addError(new FormError('Cette adresse email est déjà utilisée.'));
            } else {
                $newPassword = $form->getData()->getNewPassword();
                $user->setEmail($email);
                $user->setPassword($hasher->hashPassword($user, $newPassword));
                $user->setPasswordChangedAt(new \DateTimeImmutable());
                $manager->updateUser($user);
                // Clears the temporary-password flag and the stored initial password.
                $dispatcher->dispatch(new UserPasswordChangeEvent($user, $newPassword), UserPasswordChangeEvent::NAME);

                return $this->redirect($this->target($request));
            }
        }

        return $this->render('@NetBSSecure/first_login/first_login.html.twig', [
            'form'     => $form->createView(),
            // The name typed on the login form, for password managers.
            'username' => (method_exists($user, 'getLoginUsername') ? $user->getLoginUsername() : null) ?? $user->getUserIdentifier(),
        ], new Response('', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function emailTakenByAnother(EntityManagerInterface $em, BaseUser $user, string $email): bool
    {
        $owner = $em->getRepository(get_class($user))->findOneBy(['email' => $email]);

        return $owner !== null && $owner->getId() !== $user->getId();
    }

    /** Where the user was heading when the guard stopped them; a local path only. */
    private function target(Request $request): string
    {
        $target = $request->getSession()->remove(FirstLoginGuard::TARGET_KEY);

        return is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//') && !str_contains($target, '\\')
            ? $target
            : $this->generateUrl('netbs.core.home.dashboard');
    }
}
