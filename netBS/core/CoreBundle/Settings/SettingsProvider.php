<?php

namespace NetBS\CoreBundle\Settings;

/**
 * Implemented by each bundle that owns settings shown on the Paramètres page. Implementations
 * are tagged netbs.settings_provider through autoconfiguration (see NetBSCoreExtension).
 */
interface SettingsProvider
{
    public function define(SettingsBuilder $s): void;
}
