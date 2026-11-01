<?php

namespace NetBS\CoreBundle\Settings;

final class SettingProblem
{
    public function __construct(
        public readonly SettingDefinition $definition,
        public readonly string $message,
    ) {
    }
}
