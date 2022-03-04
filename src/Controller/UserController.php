<?php

namespace App\Controller;

use NetBS\CoreBundle\Utils\Modal;
use App\Entity\BSUser;
use App\Form\AdminChangePasswordType;
use App\Model\AdminChangePassword;
use NetBS\SecureBundle\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserController
 * @package App\Controller
 * @Route("/user")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/latest-accounts", name="sauvabelin.user.latest_created")
     */
    public function latestCreatedAction() {

        return $this->render('user/last_created_accounts.html.twig');
    }

    /**
     * @Route("/user/admin-change-password/{id}", name="sauvabelin.user.admin_change_password_modal")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function modalAdminChangePasswordAction(Request $request, $id, UserManager $manager) {
        /** @var BSUser $user */
        $user       = $manager->find($id);
        $form       = $this->createForm(AdminChangePasswordType::class, new AdminChangePassword());

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            /** @var AdminChangePassword $data */
            $data   = $form->getData();

            if($data->isForceChange())
                $user->setNewPasswordRequired(true);

            $user->setPassword($manager->encodePassword($user, $data->getPassword()));
            $manager->updateUser($user);

            return Modal::ack("Mot de passe modifié");
        }

        return $this->render('user/change_password.modal.twig', [
            'title' => 'Changer un mot de passe',
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}
