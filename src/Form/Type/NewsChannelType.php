<?php

namespace App\Form\Type;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class NewsChannelType extends AbstractType
{
    private $token;

    public function __construct(TokenStorageInterface $storage)
    {
        $this->token    = $storage;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $choices    = $view->vars['choices'];
        $eengine    = new ExpressionLanguage();
        $user       = $this->token->getToken()->getUser();

        foreach($view->vars['choices'] as $i => $choice) {

            $channel = $choice->data;

            if(!empty($channel->getPostRule()) && !$eengine->evaluate($channel->getPostRule(), ['user' => $user]))
                unset($choices[$i]);
        }

        $view->vars['choices'] = $choices;
    }

    public function getParent()
    {
        return EntityType::class;
    }
}
