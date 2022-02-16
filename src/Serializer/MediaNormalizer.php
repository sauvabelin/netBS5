<?php

namespace App\Serializer;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use App\Model\GalerieConfig;
use App\Model\Media;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MediaNormalizer implements NormalizerInterface
{
    private $webPath;

    private $cacheManager;

    private $assets;

    private $config;

    public function __construct(string $webPath, CacheManager $cacheManager, AssetExtension $extension, GalerieConfig $config)
    {
        $this->webPath      = $webPath;
        $this->cacheManager = $cacheManager;
        $this->assets       = $extension;
        $this->config       = $config;
    }

    /**
     * @param Media $media
     * @param null $format
     * @param array $context
     * @return array|bool|float|int|string
     */
    public function normalize($media, string $format = null, array $context = array())
    {
        return [
            'filename'  => $media->getName(),
            'size'      => $media->getSize(),
            'timestamp' => $media->getTimestamp(),
            'thumbnail' => $this->assets->getAssetUrl($this->cacheManager->getBrowserPath(base64_encode($media->getRelativePath()), 'thumbnail')),
            'bignail'   => $this->webPath . $this->assets->getAssetUrl($this->config->getMappedDirectory() . $media->getRelativePath())
        ];
    }

    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof Media;
    }
}
