<?php

namespace NetBS\CoreBundle\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class UtilExtension extends AbstractExtension
{
    protected $increment    = 0;

    public function getName()
    {
        return 'util';
    }

    public function getFunctions() {

        return [

            new TwigFunction('random_number', [$this, 'randomNumber']),
            new TwigFunction('increment', [$this, 'increment']),
            new TwigFunction('uniqid', [$this, 'uniqid']),
        ];
    }

    public function getFilters() {

        return [

            new TwigFilter('toBase64', [$this, 'base64encodeFilter']),
            new TwigFilter('fromBase64', [$this, 'base64decodeFilter'])
        ];
    }

    public function randomNumber($min = 0, $max = 99999) {

        return mt_rand($min, $max);
    }

    public function uniqid() {

        return uniqid();
    }

    public function increment() {

        return $this->increment++;
    }

    public function base64encodeFilter($value) {

        return base64_encode($value);
    }

    public function base64decodeFilter($value) {

        return base64_decode($value);
    }
}
