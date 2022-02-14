<?php

namespace NetBS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use NetBS\CoreBundle\Entity\NewsChannel;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class NewsChannelRepository extends EntityRepository
{
    /**
     * @var ExpressionLanguage
     */
    private $engine;

    /**
     * @param BaseUser $user
     * @return NewsChannel[]
     */
    public function findWritableChannels(BaseUser $user) {

        return $this->findChannels($user, 'post');
    }

    /**
     * @param BaseUser $user
     * @return NewsChannel[]
     */
    public function findReadableChannels(BaseUser $user) {

        return $this->findChannels($user, 'read');
    }

    private function findChannels(BaseUser $user, $action) {

        if($this->engine === null)
            $this->engine = new ExpressionLanguage();

        $channels = $this->findAll();
        $result = [];

        /** @var NewsChannel $channel */
        foreach($channels as $channel) {

            $rule = $action === 'read' ? $channel->getReadRule() : $channel->getPostRule();
            if (empty($rule) || $this->engine->evaluate($rule, ['user' => $user]))
                $result[] = $channel;
        }

        return $result;
    }
}