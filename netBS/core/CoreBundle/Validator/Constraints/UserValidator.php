<?php

namespace NetBS\CoreBundle\Validator\Constraints;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UserValidator extends ConstraintValidator
{
    const CACHE_KEY = "netbs.core.cache.user_constraint";

    private $storage;

    private $engine;

    private $constraints = [];

    private $cache;

    private $manager;

    public function __construct(TokenStorageInterface $storage, CacheItemPoolInterface $cache, EntityManagerInterface $manager)
    {
        $this->storage  = $storage;
        $this->cache    = $cache;
        $this->manager  = $manager;
    }

    public function validate($item, Constraint $constraint): void
    {
        $this->constraints[] = [
            'item'          => $item,
            'constraint'    => $constraint
        ];
    }

    public function getConstraints() {

        return $this->constraints;
    }

    /**
     * @param $item
     * @return User|null
     */
    public function getConstraint($item) {

        foreach($this->constraints as $constraint)
            if($constraint['item'] === $item)
                return $constraint['constraint'];

        return null;
    }

    /**
     * @param $object
     * @param $property
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function canUpdate($object, $property) {

        // if(!$this->cache->getItem(self::CACHE_KEY)->isHit())
            $this->generateCache();

        $cache  = $this->cache->getItem(self::CACHE_KEY)->get();
        $mapped = json_decode($cache, true);

        foreach($mapped as $item) {

            $class  = $item['class'];
            $rule   = $item['rule'];
            $rules  = $item['rules'];

            if(ClassUtils::getClass($object) === $class) {

                if($rule)
                    return $this->validateRule($rule);

                foreach($rules as $key => $rule)
                    if($key === $property)
                        return $this->validateRule($rule);
            }

        }

        return true;
    }

    public function validateRule($rule) {

        if(!$this->engine)
            $this->engine = new ExpressionLanguage();

        return $this->engine->evaluate($rule, [
            'user' => $this->storage->getToken()->getUser()
        ]);
    }

    private function generateCache() {

        $mapped = [];
        foreach($this->manager->getMetadataFactory()->getAllMetadata() as $metadata) {

            $rclass         = $metadata->getReflectionClass();
            $attributes     = [];
            $current        = $rclass;

            while($current) {

                foreach($current->getAttributes(User::class) as $attr) {
                    $attributes[] = $attr->newInstance();
                }
                $current = $current->getParentClass();
            }

            if(count($attributes) === 0)
                continue;

            $data = [
                'class' => $rclass->getName(),
                'rule'  => null,
                'rules' => []
            ];

            foreach($attributes as $constraint) {
                $data['rule'] = $constraint->rule;
                $data['rules'] = array_merge($data['rules'], $constraint->rules);
            }

            $mapped[] = $data;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        $item->set(json_encode($mapped));
        $this->cache->save($item);
    }
}
