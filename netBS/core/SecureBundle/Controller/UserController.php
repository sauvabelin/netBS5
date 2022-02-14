<?php

namespace NetBS\SecureBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Block\CardBlock;
use NetBS\CoreBundle\Block\LayoutManager;
use NetBS\CoreBundle\Service\History;
use NetBS\SecureBundle\Event\UserPasswordChangeEvent;
use NetBS\SecureBundle\Form\ChangePasswordType;
use NetBS\SecureBundle\Form\UserType;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Model\ChangePassword;
use NetBS\SecureBundle\Service\SecureConfig;
use NetBS\SecureBundle\Service\UserManager;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserController
 * @package NetBS\SecureBundle\Controller
 */
class UserController extends AbstractController
{
    /**
     * @Route("/user/list", name="netbs.secure.user.list_users")
     */
    public function listUsersAction(Request $request) {

        $username = empty($request->get('username')) ? null : $request->get('username');
        return $this->render('@NetBSSecure/user/list_users.html.twig', [
            'username' => $username
        ]);
    }

    /**
     * @Route("/user/edit/{id}", name="netbs.secure.user.edit_user")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function updateUserAction(Request $request, $id, UserManager $manager) {
        $user       = $manager->find($id);
        $form       = $this->createForm(UserType::class, $user, ['operation' => CRUD::UPDATE]);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {

            $user   = $form->getData();

            $manager->updateUser($user);
            $this->addFlash("success", "{$user->getUsername()} mis à jour");
            return $this->redirectToRoute('netbs.secure.user.list_users');
        }

        return $this->render('@NetBSCore/generic/form.generic.twig', array(
            'header'    => "Modifier {$user->getUsername()}",
            'form'      => $form->createView()
        ));
    }

    /**
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @Route("/user/delete/{id}", name="netbs.secure.user.delete_user")
     */
    public function deleteUserAction($id, SecureConfig $secureConfig, UserManager $manager, EntityManagerInterface $em, History $history) {

        $user           = $em->find($secureConfig->getUserClass(), $id);

        try {
            $manager->deleteUser($user);
        } catch (\ErrorException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $history->getPreviousRoute();
    }

    /**
     * @Route("/user/add", name="netbs.secure.user.add_user")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addUserAction(Request $request, SecureConfig $config, UserManager $manager) {
        $user       = $config->createUser();
        $form       = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $user       = $form->getData();
            $password   = $manager->encodePassword($user, $user->getPassword());

            $user->setPassword($password);
            $manager->createUser($user);

            $this->addFlash("success", "Utilisateur {$user->getUsername()} ajouté!");
            return $this->redirectToRoute("netbs.secure.user.list_users");
        }

        return $this->render('@NetBSCore/generic/form.generic.twig', array(
            'header'    => 'Nouvel utilisateur',
            'subHeader' => "Ajouter un utilisateur manuellement",
            'form'  => $form->createView()
        ));
    }

    /**
     * @Route("/user/my-account", name="netbs.secure.user.account_page")
     */
    public function accountPageAction(Request $request, UserManager $manager, LayoutManager $designer, EventDispatcherInterface $dispatcher, UserPasswordEncoderInterface $encoder) {
        /** @var BaseUser $user */
        $user               = $this->getUser();
        $userForm           = $this->createForm(UserType::class, $user);
        $changePassword     = new ChangePassword();
        $changePasswordForm = $this->createForm(ChangePasswordType::class, $changePassword);

        $changePasswordForm->handleRequest($request);

        if($changePasswordForm->isSubmitted() && $changePasswordForm->isValid()) {

            $newPassword    = $changePassword->getNewPassword();
            $password       = $encoder->encodePassword($user, $newPassword);

            $user->setPassword($password);
            $manager->updateUser($user);

            $dispatcher->dispatch(new UserPasswordChangeEvent($user, $newPassword), UserPasswordChangeEvent::NAME);

            $this->addFlash("success", "Mot de passe changé avec succès!");
            return $this->redirectToRoute('netbs.secure.user.account_page');
        }

        $config = $designer::configurator()
            ->addRow()
                ->pushColumn(4)
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(CardBlock::class, [
                                'template'  => "@NetBSSecure/user/user_presentation.block.twig",
                                'title'     => 'Informations de compte',
                                'subtitle'  => 'Compte lié à ' . ($user->getMembre() ? $user->getMembre() : ' aucun membre'),
                                'params'    => [
                                    'userForm'  => $userForm->createView(),
                                    'user'      => $user,
                                ]
                            ])
                        ->close()
                    ->close()
                ->close()
                ->pushColumn(8)
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(CardBlock::class, [
                                'template'  => '@NetBSSecure/user/user_change_password.block.twig',
                                'title'     => 'Changer de mot de passe',
                                'subtitle'  => 'Définir un nouveau mot de passe pour le compte',
                                'params'    => [
                                    'cpForm'    => $changePasswordForm->createView(),
                                ]
                            ])
                        ->close()
                    ->close()
                ->close()
            ->close();

        return $designer->renderResponse('netbs', $config, [
            'title' => 'Mon compte'
        ]);
    }
}
