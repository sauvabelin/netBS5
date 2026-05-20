<?php

declare(strict_types=1);

namespace NetBS\CoreBundle\Form;

final class FormResponseAttributes
{
    /**
     * Request attribute flag set when a root form submit failed validation.
     * The 422-bump kernel.response listener reads it; the form type extension
     * and HandlesFormPersistenceTrait both write it.
     */
    public const ROOT_INVALID = '_form_root_invalid';

    private function __construct() {}
}
