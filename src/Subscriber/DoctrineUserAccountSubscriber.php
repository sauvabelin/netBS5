<?php

namespace App\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use NetBS\CoreBundle\Utils\StrUtil;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\SecureBundle\Mapping\BaseUser;
use App\Entity\BSMembre;
use App\Entity\BSUser;
use App\Entity\LatestCreatedAccount;
use App\Message\NextcloudGroupNotification;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Class UserAccountSubscriber
 * @package App\Subscriber
 * Gère la création de comptes utilisateur basés sur la création d'attributions
 * Aime pas du tout qu'on lui file des services qui dépendent de l'entity manager
 */
class DoctrineUserAccountSubscriber implements EventSubscriber
{
    private $encoder;

    private $mailer;

    private $bus;

    private $fnWeight = null;

    private $roleUser = null;

    private $adabsId  = null;

    public function __construct(UserPasswordHasherInterface $encoder, MailerInterface $mailer, MessageBusInterface $bus)
    {
        $this->encoder  = $encoder;
        $this->mailer   = $mailer;
        $this->bus = $bus;
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {

        $attribution = $args->getEntity();

        if (!$attribution instanceof BaseAttribution)
            return;

        $this->handleCreation($attribution, $args->getEntityManager());
    }

    public function postUpdate(LifecycleEventArgs $args) {

        $attribution    = $args->getEntity();

        if(!$attribution instanceof BaseAttribution)
            return;

        $this->handleCreation($attribution, $args->getEntityManager());
    }

    public function postRemove(LifecycleEventArgs $args) {

        $attribution    = $args->getEntity();

        if(!$attribution instanceof BaseAttribution)
            return;
    }

    private function handleCreation(BaseAttribution $attribution, ObjectManager $manager) {

        /** @var BSMembre $membre */
        $membre     = $attribution->getMembre();

        if($membre->getStatut() !== BaseMembre::INSCRIT)
            return;

        $fonction   = $attribution->getFonction();

        if($this->adabsId === null)
            $this->adabsId = intval($manager->getRepository('NetBSCoreBundle:Parameter')
                ->findOneBy(array('namespace' => 'bs', 'paramKey'  => 'groupe.adabs_id'))->getValue());

        if($this->fnWeight === null)
            $this->fnWeight = $manager->getRepository('NetBSCoreBundle:Parameter')
                ->findOneBy(array('namespace' => 'bs', 'paramKey' => 'fonction.weight.user_account'))->getValue();

        //Fonction pas assez balèze pour créer un compte
        if($fonction->getPoids() < intval($this->fnWeight))
            return;

        foreach($membre->getActivesAttributions() as $attribution)
            if(intval($attribution->getGroupe()->getId()) === $this->adabsId)
                return;

        $user = $manager->getRepository('App:BSUser')->findOneBy(array('membre' => $membre));

        //Deja un compte
        if($user instanceof BaseUser)
            return;

        $this->createUser($membre, $manager);
    }

    private function createUser(BaseMembre $membre, ObjectManager $manager) {

        if($this->roleUser === null)
            $this->roleUser = $manager->getRepository('NetBSSecureBundle:Role')->findOneBy(array('role' => 'ROLE_USER'));

        $username   = StrUtil::slugify($membre->getPrenom()) . "." . StrUtil::slugify($membre->getFamille()->getNom());
        //$password   = StrUtil::randomString();
        $password   = $username . "-" . $membre->getNaissance()->format("d-m-Y");
        $user       = new BSUser();
        $i          = 1;

        /*
        while($manager->getRepository('SauvabelinBundle:BSUser')->findOneBy(array('username' => $username)))
            $username   = $username . $i++;
        */

        $user->setNewPasswordRequired(true);
        $user->setMembre($membre);
        $user->setUsername($username);
        $user->setPassword($this->encoder->hashPassword($user, $password));
        $user->addRole($this->roleUser);

        $latestAccount  = new LatestCreatedAccount();
        $latestAccount->setUser($user);
        $latestAccount->setPassword($password);
        $manager->persist($latestAccount);

        /*
        $subject    = $membre->getPrenom() . ", ton compte Sauvabelin a été créé!";
        $this->mailer->send('mailer/account_created.piece.twig', $subject, $user, [
            'username'  => $username,
            'password'  => $password
        ]);
        */

        $manager->persist($user);
        $manager->flush();

        // Notify nextcloud of new groups memberships
        foreach ($membre->getActivesAttributions() as $attr) {
            $this->bus->dispatch(new NextcloudGroupNotification($user, $attr->getGroupe(), 'join'));
        }
    }
}
