<?php

namespace App\ListModel;

use App\Entity\NewsChannelBot;
use NetBS\CoreBundle\Entity\NewsChannel;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class NewsChannelsBotList extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository('App:NewsChannelBot')->findAll();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return NewsChannelBot::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "app.news_channel_bots";
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Nom", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => "name",
                XEditableColumn::TYPE_CLASS => TextType::class
            ])
            ->addColumn("Description", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => "description",
                XEditableColumn::TYPE_CLASS => TextType::class
            ])
            ->addColumn("Channels", null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => AjaxSelect2DocumentType::class,
                XEditableColumn::PROPERTY   => 'channels',
                XEditableColumn::PARAMS     => [
                    'class'     => NewsChannel::class,
                    'multiple'  => true
                ]
            ])
        ;
    }
}