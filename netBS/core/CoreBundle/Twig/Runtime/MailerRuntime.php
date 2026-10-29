<?php

namespace NetBS\CoreBundle\Twig\Runtime;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Lazy half of MailerExtension: AssetMapper is only built when an email is
 * rendered, not on every request that boots Twig.
 */
class MailerRuntime implements RuntimeExtensionInterface
{
    private const STYLESHEET = 'styles/email.scss';

    private ?string $css = null;

    public function __construct(private readonly AssetMapperInterface $assetMapper)
    {
    }

    /**
     * Compiled CSS of assets/styles/email.scss, for inline_css. Comes from the
     * same sass:build output as the app's main.scss.
     */
    public function getEmailStylesheet(): string
    {
        return $this->css ??= $this->assetMapper->getAsset(self::STYLESHEET)?->content
            ?? throw new \LogicException(sprintf('Email stylesheet "%s" is not a compiled asset; run sass:build.', self::STYLESHEET));
    }
}
