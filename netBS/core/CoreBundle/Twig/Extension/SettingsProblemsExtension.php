<?php

namespace NetBS\CoreBundle\Twig\Extension;

use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Settings\SettingProblem;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsProblemsExtension extends AbstractExtension
{
    public function __construct(private ParameterManager $parameters)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('settings_problems', [$this, 'importantProblems']),
        ];
    }

    /**
     * Important settings that are missing or broken, for the admin banner. Computed per call
     * (one query on netbs_core_parameters plus the reference checks).
     *
     * @return SettingProblem[]
     */
    public function importantProblems(): array
    {
        return array_values(array_filter($this->parameters->problems(), fn(SettingProblem $p) => $p->definition->important));
    }
}
