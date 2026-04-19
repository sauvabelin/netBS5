<?php

namespace NetBS\FichierBundle\Listener;

use NetBS\CoreBundle\Block\CardBlock;
use NetBS\CoreBundle\Event\PreRenderLayoutEvent;
use NetBS\FichierBundle\Service\MesUnitesResolver;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PreRenderLayoutListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly MesUnitesResolver $resolver,
    ) {}

    public function preRender(PreRenderLayoutEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || $request->get('_route') !== 'netbs.core.home.dashboard') {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof BaseUser) {
            return;
        }

        $roots = $this->resolver->resolveFor($user);

        $event->getConfigurator()
            ->getRow(0)
            ->addColumn(0, 4, 5, 12)->addRow()
            ->addColumn(0, 12)->setBlock(CardBlock::class, [
                'title'    => 'Mes unités',
                'subtitle' => 'Vos unités actives et leurs effectifs',
                'template' => '@NetBSFichier/dashboard/mes_unites.block.twig',
                'params'   => ['roots' => $roots],
            ]);
    }
}
