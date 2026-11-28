<?php

namespace App\Imagine;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Binary\Loader\FileSystemLoader;

class GalerieLoader extends FileSystemLoader
{
    public function find($path): BinaryInterface|string
    {
        return parent::find(base64_decode($path));
    }
}
