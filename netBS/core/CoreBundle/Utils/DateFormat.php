<?php

namespace NetBS\CoreBundle\Utils;

/**
 * The one date format used to show and read dates, in PHP date() syntax. Twig gets
 * them as the date_format / datetime_format globals (config/packages/twig.yaml).
 */
final class DateFormat
{
    const DATE        = 'd.m.Y';
    const DATETIME    = 'd.m.Y H\hi';
    const PLACEHOLDER = 'jj.mm.aaaa';
}
