<?php

namespace App\Command;

use App\Entity\BSGroupe;
use App\Message\NextcloudGroupNotification;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: "netbs:attributions:check-talk",
)]
class TalkCheckAttributionsCommand extends Command
{
    private $em;
    private $fc;
    private $sc;
    private $cache;
    private $params;
    private $bus;

    private $previousRun = null;

    public function __construct(
        EntityManagerInterface $em,
        AdapterInterface $adapter,
        FichierConfig $fichierConfig,
        SecureConfig $sc,
        ParameterManager $params,
        MessageBusInterface $bus)
    {
        $this->em = $em;
        $this->cache = $adapter;
        $this->fc = $fichierConfig;
        $this->sc = $sc;
        $this->params = $params;
        $this->bus = $bus;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkJoins();
        $this->checkLeaves();
        $this->checkUpdated();

        return Command::SUCCESS;
    }

    private function checkUpdated() {

        /** @var EntityRepository $repo */
        $repo = $this->em->getRepository($this->fc->getAttributionClass());
        $query = $repo->createQueryBuilder('a');
        $updated = $query
            ->where($query->expr()->gt('a.updatedAt', ':p'))
            ->setParameter('p', $this->getPreviousRun())
            ->getQuery()
            ->execute();

        /** @var BaseAttribution $attribution */
        foreach($updated as $attribution) {
            $user = $this->getUser($attribution);
            if (!$user) continue;

            if ($attribution->isActive()) {
                $this->bus->dispatch(new NextcloudGroupNotification(
                    $user->getId(),
                    $this->isGroupMapped($attribution->getGroupe()) ? $attribution->getGroupeId() : null,
                    $attribution->getFonctionId(),
                    'join'
                ));
            } else {
                $this->bus->dispatch(new NextcloudGroupNotification(
                    $user->getId(),
                    $this->isGroupMapped($attribution->getGroupe()) ? $attribution->getGroupeId() : null,
                    $attribution->getFonctionId(),
                    'leave'
                ));
            }
        }
    }

    private function checkJoins() {

        /** @var EntityRepository $repo */
        $repo = $this->em->getRepository($this->fc->getAttributionClass());
        $query = $repo->createQueryBuilder('a');
        $attributionsJoin = $query
            ->where($query->expr()->gt('a.dateDebut', ':p'))
            ->andWhere($query->expr()->lt('a.dateDebut', ':n'))
            ->andWhere($query->expr()->orX(
                $query->expr()->isNull('a.dateFin'),
                $query->expr()->gt('a.dateFin', ':n')
            ))
            ->setParameter('p', $this->getPreviousRun())
            ->setParameter('n', new \DateTime())
            ->getQuery()
            ->execute();

        /** @var BaseAttribution $attribution */
        foreach($attributionsJoin as $attribution) {
            $user = $this->getUser($attribution);
            if (!$user) continue;

            $this->bus->dispatch(new NextcloudGroupNotification(
                $user->getId(),
                $this->isGroupMapped($attribution->getGroupe()) ? $attribution->getGroupeId() : null,
                $attribution->getFonctionId(),
                'join'
            ));
        }
    }

    private function checkLeaves() {

        /** @var EntityRepository $repo */
        $repo = $this->em->getRepository($this->fc->getAttributionClass());
        $query = $repo->createQueryBuilder('a');
        $attributionsLeave = $query
            ->where($query->expr()->andX(
                $query->expr()->isNotNull('a.dateFin'),
                $query->expr()->gt('a.dateFin', ':p'),
                $query->expr()->lt('a.dateFin', ':n')
            ))
            ->setParameter('p', $this->getPreviousRun())
            ->setParameter('n', new \DateTime())
            ->getQuery()
            ->execute();

        /** @var BaseAttribution $attribution */
        foreach($attributionsLeave as $attribution) {
            $user = $this->getUser($attribution);
            if (!$user) continue;

            $this->bus->dispatch(new NextcloudGroupNotification(
                $user->getId(),
                $this->isGroupMapped($attribution->getGroupe()) ? $attribution->getGroupeId() : null,
                $attribution->getFonctionId(),
                'leave'
            ));
        }
    }

    private function getPreviousRun() {

        if ($this->previousRun) return $this->previousRun;

        $format = 'd/m/Y:H:i:s';
        $previousRun = new \DateTime();
        $previousRunCacheItem = $this->cache->getItem('bs.nc_talk_check_attrs');

        if ($previousRunCacheItem->isHit()) {
            $previousRun = \DateTime::createFromFormat($format, $previousRunCacheItem->get());
        }

        $this->previousRun = $previousRun;
        return $this->previousRun;
    }

    private function isGroupMapped(BaseGroupe $groupe) {
        $typeParams = [
            'groupe_type.troupe_id',
            'groupe_type.meute_id',
            'groupe_type.clan_id',
            'groupe_type.association_id',
            'groupe_type.edc_id',
            'groupe_type.equipe_interne_id',
            'groupe_type.branche_id',
            'groupe_type.equipe_id'
        ];

        foreach ($typeParams as $key) {
            $id = $this->params->getValue('bs', $key);
            if ($groupe->getGroupeType()->getId() === $id) {
                return true;
            }
        }

        return false;
    }

    private function getUser(BaseAttribution $attribution): BaseUser | null {
        return $this->em->getRepository($this->sc->getUserClass())->findOneBy(['membre' => $attribution->getMembre()]);
    }
}