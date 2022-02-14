<?php

namespace App\Imagine;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Exception\Imagine\Cache\Resolver\NotResolvableException;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use App\Model\GalerieConfig;
use Symfony\Component\Filesystem\Filesystem;

class GalerieCacheResolver implements ResolverInterface
{
    /**
     * @var string
     */
    private $webPath;

    /**
     * @var GalerieConfig
     */
    private $config;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct($webPath, GalerieConfig $config, Filesystem $filesystem)
    {
        $this->webPath      = $webPath;
        $this->config       = $config;
        $this->filesystem   = $filesystem;
    }

    /**
     * Checks whether the given path is stored within this Resolver.
     *
     * @param string $fid
     * @param string $filter
     * @return bool
     */
    public function isStored($fid, $filter)
    {
        return file_exists($this->getFSPath($fid, $filter));
    }

    /**
     * Resolves filtered path for rendering in the browser.
     *
     * @param string $fid The path where the original file is expected to be
     * @param string $filter The name of the imagine filter in effect
     *
     * @throws NotResolvableException
     *
     * @return string The absolute URL of the cached image
     */
    public function resolve($fid, $filter)
    {
        return $this->webPath . $this->config->getCacheDirectory() . "/" . $this->getCachePath($fid, $filter);
    }

    /**
     * Stores the content of the given binary.
     *
     * @param BinaryInterface $binary The image binary to store
     * @param string $fid The path where the original file is expected to be
     * @param string $filter The name of the imagine filter in effect
     */
    public function store(BinaryInterface $binary, $fid, $filter)
    {
        $this->filesystem->dumpFile($this->getFSPath($fid, $filter), $binary->getContent());
    }

    /**
     * @param string[] $fids The paths where the original files are expected to be
     * @param string[] $filters The imagine filters in effect
     */
    public function remove(array $fids, array $filters)
    {
        foreach($fids as $fid)
            foreach($filters as $filter)
                $this->filesystem->remove($this->getFSPath($fid, $filter));
    }

    private function getFSPath($fid, $filter) {

        return $this->config->getFullCacheDirectory() . "/" . $this->getCachePath($fid, $filter);
    }

    private function getCachePath($fid, $filter) {

        $fid    = base64_decode($fid);
        $hash   = md5(trim($fid, "/"));

        return $filter . "/". preg_replace('/[^0-9.]+/', '', $hash)
            . "/" . substr($hash, 0, 6)
            . "/" . $hash;
    }
}
