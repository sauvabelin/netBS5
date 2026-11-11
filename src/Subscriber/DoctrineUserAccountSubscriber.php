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
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Service\ParameterManager;
use App\Settings\BsReferences;
use Psr\Log\LoggerInterface;
use NetBS\SecureBundle\Entity\Role;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
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

    private $roleUser = null;

    public function __construct(
        UserPasswordHasherInterface $encoder,
        MailerInterface $mailer,
        private readonly RequestStack $requestStack,
        private readonly ParameterManager $params,
        private readonly LoggerInterface $logger,
        private readonly RuleEvaluator $ruleEvaluator,
        private readonly BsReferences $refs,
    ) {
        $this->encoder  = $encoder;
        $this->mailer   = $mailer;
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

        if (!$this->shouldCreateAccount($attribution))
            return;

        /** @var BSMembre $membre */
        $membre     = $attribution->getMembre();

        // Check including soft-deleted users to avoid unique constraint violations
        $filters = $manager->getFilters();
        $filterWasEnabled = $filters->isEnabled('softdeleteable');
        if ($filterWasEnabled) $filters->disable('softdeleteable');
        try {
            $user = $manager->getRepository(BSUser::class)->findOneBy(array('membre' => $membre));
        } finally {
            if ($filterWasEnabled) $filters->enable('softdeleteable');
        }

        if($user instanceof BaseUser)
            return;

        $this->createUser($membre, $manager);
    }

    /**
     * Whether saving this attribution earns its (inscrit) membre an account: the « création
     * automatique de compte » rule on the membre, or without a rule the legacy logic (the saved
     * attribution's fonction has poids >= legacy key bs.fonction.weight.user_account, and the
     * membre is not in ADABS). Runs on every attribution save: a missing setting skips account
     * creation instead of failing the save.
     */
    public function shouldCreateAccount(BaseAttribution $attribution): bool {

        // Skip if the attribution is being soft-deleted
        if($attribution->getDeletedAt() !== null)
            return false;

        $membre     = $attribution->getMembre();

        if($membre->getStatut() !== BaseMembre::INSCRIT)
            return false;

        $rule = $this->params->getValue('bs', 'comptes.creation');
        if ($rule !== null) {
            // The attribution flows only call setMembre(); the rule must see the attribution being saved.
            if (!$membre->getAttributions()->contains($attribution))
                $membre->getAttributions()->add($attribution);

            return $this->ruleEvaluator->matches($rule, 'membre', $membre);
        }

        $adabsId    = $this->refs->id('groupe.adabs_id');
        $fnWeight   = $this->params->getValue('bs', 'fonction.weight.user_account');
        if ($fnWeight === null) {
            $this->logger->warning('Paramètre legacy bs.fonction.weight.user_account manquant : aucun compte créé automatiquement.');
            return false;
        }

        //Fonction pas assez balèze pour créer un compte
        if($attribution->getFonction()->getPoids() < intval($fnWeight))
            return false;

        foreach($membre->getActivesAttributions() as $active)
            if(intval($active->getGroupe()->getId()) === $adabsId)
                return false;

        return true;
    }

    private function createUser(BaseMembre $membre, ObjectManager $manager) {

        if($this->roleUser === null)
            $this->roleUser = $manager->getRepository(Role::class)->findOneBy(array('role' => 'ROLE_USER'));

        $username = StrUtil::slugify($membre->getPrenom()) . "." . StrUtil::slugify($membre->getFamille()->getNom());

        // The new user's username and login username both default to this
        // slug; it must not be held by anyone (soft-deleted included) in either column.
        $collision = $manager->getRepository(BSUser::class)->isHandleTaken([$username]);

        if ($collision) {
            $message = sprintf(
                "Le membre %s %s a été créé sans compte utilisateur : un compte « %s » existe déjà. " .
                "Vous pouvez attacher le compte existant à ce membre, ou créer manuellement un nouveau compte avec un identifiant différent.",
                $membre->getPrenom(),
                $membre->getFamille()->getNom(),
                $username
            );
            $this->logger->warning($message);
            // Attributions are also saved outside a request (commands, workers): no session there.
            $session = $this->requestStack->getCurrentRequest()?->hasSession()
                ? $this->requestStack->getSession()
                : null;
            $session?->getFlashBag()->add('warning', $message);
            return;
        }

        //$password   = StrUtil::randomString();
        $password   = $username . "-" . $membre->getNaissance()->format("d-m-Y");
        $user       = new BSUser();

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
    }
}
