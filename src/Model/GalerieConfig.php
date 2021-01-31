<?php

namespace App\Model;

class GalerieConfig
{
    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var string
     */
    private $prefixDirectory;

    /**
     * @var string
     */
    private $mappedDirectory;

    /**
     * @var string
     */
    private $cacheDirectory;

    /**
     * @var array
     */
    private $imageExtensions;

    /**
     * @var string
     */
    private $descriptionFilename;

    public function __construct($rootDir, $prefixDirectory, $mappedDirectory, $cacheDirectory, $extensions, $descriptionFilename)
    {
        $this->rootDir              = $rootDir;
        $this->prefixDirectory      = $prefixDirectory;
        $this->mappedDirectory      = $mappedDirectory;
        $this->cacheDirectory       = $cacheDirectory;
        $this->imageExtensions      = $extensions;
        $this->descriptionFilename  = $descriptionFilename;
    }

    /**
     * @return array
     */
    public function getImageExtensions()
    {
        return $this->imageExtensions;
    }

    /**
     * @return string
     */
    public function getDescriptionFilename()
    {
        return $this->descriptionFilename;
    }

    /**
     * @return string
     */
    public function getMappedDirectory()
    {
        return $this->mappedDirectory;
    }

    /**
     * @return string
     */
    public function getCacheDirectory()
    {
        return $this->cacheDirectory;
    }

    public function getFullMappedDirectory() {

        return $this->rootDir . $this->prefixDirectory . $this->mappedDirectory;
    }

    public function getFullCacheDirectory() {

        return $this->rootDir . $this->prefixDirectory . $this->cacheDirectory;
    }

    /**
     * @return string
     */
    public function getPrefixDirectory()
    {
        return $this->prefixDirectory;
    }
}
