<?php

namespace Ovesco\FacturationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Ovesco\FacturationBundle\Util\DateImpressionTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Rappel
 *
 * @ORM\Table(name="ovesco_facturation_rappels")
 * @ORM\Entity
 */
class Rappel
{
    use RemarqueTrait, DateImpressionTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var Facture
     *
     * @ORM\ManyToOne(targetEntity="Ovesco\FacturationBundle\Entity\Facture", inversedBy="rappels")
     * @Groups({"rappel_with_facture"})
     */
    protected $facture;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", name="date")
     * @Groups({"default"})
     */
    protected $date;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set facture.
     *
     * @param \Ovesco\FacturationBundle\Entity\Facture|null $facture
     *
     * @return Rappel
     */
    public function setFacture(Facture $facture = null)
    {
        $this->facture = $facture;

        return $this;
    }

    /**
     * Get facture.
     *
     * @return \Ovesco\FacturationBundle\Entity\Facture|null
     */
    public function getFacture()
    {
        return $this->facture;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param \DateTime $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }
}
