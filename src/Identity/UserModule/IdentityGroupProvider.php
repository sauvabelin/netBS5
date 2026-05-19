<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use App\Identity\Contract\IdentityDTO;
use App\Identity\Contract\IdentityGroupProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class IdentityGroupProvider implements IdentityGroupProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function groupsFor(IdentityDTO $identity): array
    {
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $identity->sub]);
        if (!$user instanceof BSUser) {
            return [];
        }
        $membre = $user->getMembre();
        if ($membre === null) {
            return [];
        }

        $groupNames = [];
        foreach ($membre->getActivesAttributions() as $attribution) {
            $groupNames[$attribution->getGroupe()->getNom()] = true;
        }
        return array_keys($groupNames);
    }
}
