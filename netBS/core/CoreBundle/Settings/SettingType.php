<?php

namespace NetBS\CoreBundle\Settings;

enum SettingType: string
{
    case Text   = 'text';
    case Int    = 'int';
    case Secret = 'secret';
    case Choice = 'choice';
    case Entity = 'entity';
    case Rule   = 'rule';
}
