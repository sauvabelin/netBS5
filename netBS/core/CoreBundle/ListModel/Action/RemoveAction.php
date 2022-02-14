<?php

namespace NetBS\CoreBundle\ListModel\Action;

use Doctrine\Common\Util\ClassUtils;
use NetBS\CoreBundle\ListModel\Column\LinkColumn;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RemoveAction extends IconAction
{
    protected $checker;

    public function __construct(AuthorizationCheckerInterface $checker, RouterInterface $router)
    {
        $this->checker  = $checker;

        parent::__construct($router);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('theme', 'danger')
            ->setDefault('route', null)
            ->setDefault('icon', 'fas fa-times');
    }

    public function render($item, $params = [])
    {
        if(!$this->checker->isGranted(CRUD::DELETE, $item))
            return "";

        $params[LinkColumn::ROUTE]  = $this->router->generate('netbs.core.action.remove_item', [
            'itemId'    => $item->getId(),
            'itemClass' => base64_encode(ClassUtils::getRealClass(get_class($item)))
        ]);

        $params[LinkAction::ATTRS]  = $params[LinkAction::ATTRS] . ' onclick="return confirm(\'Etes-vous sûr de vouloir supprimer cet élément?\');"';

        return parent::render($item, $params);
    }
}
