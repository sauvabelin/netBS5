<?php

namespace App\ApiController;

use App\Model\Directory;
use App\Model\GalerieConfig;
use App\Model\LatestChanges;
use App\Model\Markdown;
use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Class GalerieAPIController
 * @package GalerieBundle\Controller
 */
class ApiGalerieController extends AbstractController
{
    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/api/v1/public/netBS/galerie/root-pictures', name: 'ovesco.galerie.public_api.root-pictures')]
    public function publicPicturesAction(GalerieConfig $config, SerializerInterface $serializer) {

        $realPath       = $config->getFullMappedDirectory() . '/';
        $directory      = new Directory($realPath, $config);
        $images         = array_map(function(Directory $directory) {
            return $directory->getThumbnail();
            }, $directory->getChildren());

        return new JsonResponse($serializer->serialize($images, 'json'), 200, [], true);
    }

    /**
     * The ten albums that most recently received photos, read from the originals on disk.
     * Cached briefly: it walks the whole galerie tree.
     */
    #[Route('/api/v1/public/netBS/galerie/latest-change', name: 'ovesco.galerie.public_api.latest-change')]
    public function latestChangeAction(GalerieConfig $config, CacheInterface $cache): JsonResponse
    {
        $changes = $cache->get('galerie.latest_changes', function (ItemInterface $item) use ($config) {
            $item->expiresAfter(300);

            return (new LatestChanges($config))->find(10);
        });

        return new JsonResponse($changes);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/api/v1/public/netBS/galerie/directory', name: 'ovesco.galerie.public_api.directory')]
    public function publicAccessAction(Request $request, ParameterManager $manager, GalerieConfig $config, SerializerInterface $serializer) {

        $token          = $request->headers->get('x-authorization');
        $token          = str_replace("Bearer ", "", $token);
        // not configured: nobody gets in (an empty list would let an empty token through)
        $actualToken    = trim((string) $manager->getValue('galerie', 'parent_token', false));
        if($actualToken === '' || $token === '' || !in_array($token, explode('|', $actualToken), true))
            return new JsonResponse("access denied", 401);

        return $this->generateDirectoryResponse($request, $config, $serializer);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/api/v1/netBS/galerie/directory', name: 'ovesco.galerie.api.directory')]
    public function getDirectoryAction(Request $request, GalerieConfig $config, SerializerInterface $serializer) {
        return $this->generateDirectoryResponse($request, $config, $serializer);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    private function generateDirectoryResponse(Request $request, GalerieConfig $config, SerializerInterface $serializer) {

        $realPath       = Directory::resolveHashPath($config->getFullMappedDirectory(), (string) $request->get('path'));

        if(!is_dir($realPath))
            throw $this->createNotFoundException("Directory with path $realPath not found");

        $directory      = new Directory($realPath, $config);
        $parser         = new Markdown($directory->getRelativePath());

        $data = [
            'name'          => $directory->getName(),
            'path'          => $directory->getRelativePath(),
            'hashPath'      => $directory->getHashPath(),
            'description'   => $parser->text($directory->getDescription()),
            'thumbnail'     => $directory->getThumbnail(),
            'children'      => $directory->getChildren(),
            'medias'        => $directory->getMedias()
        ];

        return new JsonResponse($serializer->serialize($data, 'json'), 200, [], true);
    }
}


