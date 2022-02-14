<?php

namespace NetBS\CoreBundle\Serializer;

use NetBS\CoreBundle\Entity\News;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class NewsNormalizer implements NormalizerInterface
{
    /**
     * @param News $news
     * @param null $format
     * @param array $context
     * @return array|bool|float|int|string
     */
    public function normalize($news, string $format = null, array $context = array())
    {
        return [
            'id'        => $news->getId(),
            'titre'     => $news->getTitre(),
            'contenu'   => $news->getContenu(),
            'user'      => $news->getUser()->__toString(),
            'pinned'    => $news->isPinned(),
            'channel'   => [
                'nom'   => $news->getChannel()->getNom(),
                'color' => $news->getChannel()->getColor()
            ],
            'date'      => $news->getCreatedAt()->format('c')
        ];
    }

    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof News;
    }
}
