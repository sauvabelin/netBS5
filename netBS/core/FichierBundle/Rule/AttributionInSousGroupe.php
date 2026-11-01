<?php

namespace NetBS\FichierBundle\Rule;

use NetBS\FichierBundle\Mapping\BaseAttribution;

/** Subject of the attribution_sous_groupe context: an attribution found $profondeur levels below a directory groupe. */
final class AttributionInSousGroupe
{
    public function __construct(
        public readonly BaseAttribution $attribution,
        public readonly int $profondeur,
    ) {
    }
}
