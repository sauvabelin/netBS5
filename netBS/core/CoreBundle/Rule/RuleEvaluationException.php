<?php

namespace NetBS\CoreBundle\Rule;

/** A rule that couldn't be evaluated (broken, empty, or failing at run time); see RuleEvaluator::matchesStrict(). */
class RuleEvaluationException extends \RuntimeException
{
}
