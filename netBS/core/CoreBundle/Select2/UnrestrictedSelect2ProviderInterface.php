<?php

namespace NetBS\CoreBundle\Select2;

/**
 * Marks a provider whose labels every logged-in user may see, without a READ check
 * per item (e.g. usernames, to share a dynamic list with another user).
 */
interface UnrestrictedSelect2ProviderInterface extends Select2ProviderInterface
{
}
