<?php

namespace Ovesco\FacturationBundle\Voter;

use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\NetBSVoter;
use Ovesco\FacturationBundle\Entity\Compte;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Entity\Paiement;
use Ovesco\FacturationBundle\Entity\Rappel;

class FacturationVoter extends NetBSVoter
{

    /**
     * Returns the class name(s) of the objects checked in this voter
     * @return string|array
     */
    protected function supportClass()
    {
        return [
            Creance::class,
            Facture::class,
            Paiement::class,
            Rappel::class,
            FactureModel::class,
            Compte::class
        ];
    }

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param \Object $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function accept($operation, $subject, BaseUser $user)
    {
        return $user->hasRole('ROLE_TRESORIER');
    }
}