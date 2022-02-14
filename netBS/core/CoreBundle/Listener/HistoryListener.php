<?php

namespace NetBS\CoreBundle\Listener;

use NetBS\CoreBundle\Service\History;

class HistoryListener
{
    protected $history;

    public function __construct(History $history)
    {
        $this->history  = $history;
    }

    public function onKernelRequest()
    {
        $this->history->update();
    }
}