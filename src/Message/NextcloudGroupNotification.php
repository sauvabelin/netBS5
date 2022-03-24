<?php

namespace App\Message;

/**
 * Used to notify nextcloud that user-group mapping changed
 * for a given user - group
 */
class NextcloudGroupNotification
{
    private $userId;
    private $groupeId;
    private $fonctionId;
    private $operation;

    public function __construct(int $userId, $groupeId, $fonctionId, string $operation)
    {
        $this->userId = $userId;
        $this->groupeId = $groupeId;
        $this->fonctionId = $fonctionId;
        $this->operation = $operation;
    }

    public function getUserId() {
        return $this->userId;
    }

    public function getGroupeId() {
        return $this->groupeId;
    }

    public function getOperation() {
        return $this->operation;
    }

    public function getFonctionId() {
        return $this->fonctionId;
    }
}