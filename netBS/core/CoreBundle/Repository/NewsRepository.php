<?php

namespace NetBS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use NetBS\CoreBundle\Entity\News;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class NewsRepository extends EntityRepository
{
    /**
     * @var ExpressionLanguage
     */
    private $engine;

    /**
     * @param BaseUser $user
     * @param int $max
     * @return News[]
     */
    public function findForUser(BaseUser $user, $max = 5)
    {
        if($this->engine === null)
            $this->engine = new ExpressionLanguage();

        $news = $this->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        /** @var News $item */
        foreach($news as $item) {
            if($max > 0 && count($result) > $max - 1)
                break;

            $rule = $item->getChannel()->getReadRule();
            if(empty($rule) || $this->engine->evaluate($rule, ['user' => $user]))
                $result[] = $item;
        }

        return $result;
    }
}
