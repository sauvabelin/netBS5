<?php

namespace NetBS\CoreBundle\Twig\Extension;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MailerExtension extends AbstractExtension
{
    private const STYLESHEET = 'styles/email.scss';

    public function __construct(private readonly AssetMapperInterface $assetMapper)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('email_stylesheet', [$this, 'getEmailStylesheet']),
        ];
    }

    /**
     * Compiled CSS of assets/styles/email.scss, for inline_css. Comes from the
     * same sass:build output as the app's main.scss.
     */
    public function getEmailStylesheet(): string
    {
        $css = $this->assetMapper->getAsset(self::STYLESHEET)?->content;

        return $css ?? throw new \LogicException(sprintf('Email stylesheet "%s" is not a compiled asset; run sass:build.', self::STYLESHEET));
    }
}
