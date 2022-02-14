<?php

namespace App\Model;

class Media
{
    private $path;

    private $config;

    public function __construct($path, GalerieConfig $config)
    {
        $this->path     = $path;
        $this->config   = $config;
    }

    public function getName() {

        $segments   = explode('/', $this->path);

        return end($segments);
    }

    public function getSize() {

        return filesize($this->path);
    }

    public function getTimestamp() {

        return filectime($this->path);
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getRelativePath() {

        return str_replace($this->config->getFullMappedDirectory(), '', $this->path);
    }
}
