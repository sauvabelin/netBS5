<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Model\PreviewerInterface;

class PreviewerManager
{
    /**
     * @var PreviewerInterface[]
     */
    protected $previewers   = [];

    public function registerPreviewer(PreviewerInterface $previewer) {

        $this->previewers[get_class($previewer)] = $previewer;
    }

    /**
     * @return PreviewerInterface[]
     */
    public function getPreviewers() {

        return $this->previewers;
    }

    /**
     * @param $class
     * @return PreviewerInterface
     * @throws \Exception
     */
    public function getPreviewer($class) {

        if(!isset($this->previewers[$class]))
            throw new \Exception("No previewer of class $class exists!");

        return $this->previewers[$class];
    }
}