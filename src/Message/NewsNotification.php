<?php

namespace App\Message;

class NewsNotification
{
    private $newsId;

    public function __construct(int $newsId)
    {
        $this->newsId = $newsId;
    }

    public function getNewsId(): int
    {
        return $this->newsId;
    }
}