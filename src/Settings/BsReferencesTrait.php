<?php

namespace App\Settings;

use Symfony\Contracts\Service\Attribute\Required;

/** Setter injection of {@see BsReferences}, for the automatics (built with setter traits). */
trait BsReferencesTrait
{
    protected BsReferences $refs;

    #[Required]
    public function setBsReferences(BsReferences $refs): void
    {
        $this->refs = $refs;
    }
}
