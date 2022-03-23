<?php

namespace App\Message;

use App\Entity\BSGroupe;
use App\Entity\BSUser;

/**
 * Used to notify nextcloud that user-group mapping changed
 * for a given user - group
 */
class NextcloudGroupNotification
{
    private $user;

    private $groupe;

    private $operation;

    public function __construct(BSUser $user, BSGroupe $groupe, string $operation)
    {
        $this->userId = $user;
        $this->groupeId = $groupe;
        $this->operation = $operation;
    }

    public function getUser() {
        return $this->user;
    }

    public function getGroupe() {
        return $this->groupe;
    }

    public function getOperation() {
        return $this->operation;
    }
}