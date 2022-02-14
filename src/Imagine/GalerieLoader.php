<?php

namespace App\Imagine;

use Liip\ImagineBundle\Binary\Loader\FileSystemLoader;

class GalerieLoader extends FileSystemLoader
{
    public function find($path)
    {
        return parent::find(base64_decode($path));
    }
}
