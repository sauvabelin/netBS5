<?php

namespace NetBS\CoreBundle\Rule;

/**
 * The variables of a context for the glossary. The usable methods are read from each variable's
 * class (public getters without required argument), so they can't drift from the code.
 */
final class RuleGlossary
{
    /** @return list<array{name:string, description:string, types:list<array{name:string, methods:list<string>}>}> */
    public function variables(RuleContext $context): array
    {
        $entries = [];
        foreach ($context->variables() as $name => $variable) {
            $entries[] = [
                'name'        => $name,
                'description' => $variable->description,
                'types'       => array_map(fn (string $type) => class_exists($type)
                    ? ['name' => self::shortName($type), 'methods' => self::getters($type)]
                    : ['name' => $type, 'methods' => []], $variable->types),
            ];
        }

        return $entries;
    }

    /** BaseMembre => Membre */
    private static function shortName(string $class): string
    {
        $short = (new \ReflectionClass($class))->getShortName();

        return preg_replace('/^Base(?=[A-Z])/', '', $short);
    }

    /** @return list<string> */
    private static function getters(string $class): array
    {
        $methods = [];
        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isStatic() && $method->getNumberOfRequiredParameters() === 0
                && preg_match('/^(get|is|has)[A-Z]/', $method->getName())) {
                $methods[] = $method->getName() . '()';
            }
        }
        sort($methods);

        return $methods;
    }
}
