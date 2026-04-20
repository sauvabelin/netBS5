<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Validator\Constraints as Assert;
use NetBS\CoreBundle\Validator\Constraints as BSAssert;

/**
 * ObtentionDistinction
 */
#[ORM\MappedSuperclass]
#[BSAssert\User(rule: "user.hasRole('ROLE_SG')")]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
abstract class BaseObtentionDistinction
{
    use RemarqueTrait, TimestampableEntity, SoftDeleteableEntity;

    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'date', type: 'datetime')]
    #[Assert\NotBlank]
    #[Assert\Type("\DateTimeInterface")]
    protected $date;

    /**
     * @var BaseDistinction
     */
    #[Assert\NotBlank]
    protected $distinction;

    /**
     * @var BaseMembre
     */
    #[Assert\NotBlank]
    protected $membre;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    public function __toString(): string
    {
        try {
            $parts = [];
            if ($this->distinction) $parts[] = (string) $this->distinction;
            if ($this->membre) $parts[] = (string) $this->membre;
            return implode(' — ', $parts) ?: 'ObtentionDistinction #' . $this->id;
        } catch (\Throwable $e) {
            return 'ObtentionDistinction #' . $this->id;
        }
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set date
     *
     * @param \DateTime $date
     *
     * @return BaseObtentionDistinction
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set distinction
     *
     * @param BaseDistinction $distinction
     * @return self
     */
    public function setDistinction(BaseDistinction $distinction)
    {
        $this->distinction = $distinction;
        return $this;
    }

    /**
     * Get distinction
     *
     * @return BaseDistinction $distinction
     */
    public function getDistinction()
    {
        return $this->distinction;
    }

    /**
     * @return BaseMembre
     */
    public function getMembre() {

        return $this->membre;
    }

    /**
     * @param BaseMembre $membre
     * @return $this
     */
    public function setMembre(BaseMembre $membre) {

        $this->membre = $membre;
        return $this;
    }
}

