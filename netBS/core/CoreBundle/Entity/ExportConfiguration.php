<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\SecureBundle\Mapping\BaseUser;

/**
 * MailPreference
 *
 * @ORM\Table(name="netbs_core_export_configuration")
 * @ORM\Entity()
 */
class ExportConfiguration
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=255)
     */
    protected $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="exporter_alias", type="string", length=255)
     */
    protected $exporterAlias;

    /**
     * @var object
     *
     * @ORM\Column(name="configuration", type="text")
     */
    protected $configuration;

    /**
     * @var BaseUser
     */
    protected $user;

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
     * Set configuration
     *
     * @param string $configuration
     *
     * @return ExportConfiguration
     */
    public function setConfiguration($configuration)
    {
        $this->configuration = serialize($configuration);
        return $this;
    }

    /**
     * Get configuration
     *
     * @return string
     */
    public function getConfiguration()
    {
        return unserialize($this->configuration);
    }

    /**
     * Set user
     *
     * @param BaseUser $user
     *
     * @return ExportConfiguration
     */
    public function setUser(BaseUser $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return BaseUser
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getExporterAlias()
    {
        return $this->exporterAlias;
    }

    /**
     * @param string $exporterAlias
     * @return $this
     */
    public function setExporterAlias($exporterAlias)
    {
        $this->exporterAlias = $exporterAlias;
        return $this;
    }

    /**
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * @param string $nom
     * @return $this
     */
    public function setNom($nom)
    {
        $this->nom = $nom;
        return $this;
    }
}
