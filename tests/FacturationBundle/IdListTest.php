<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use Ovesco\FacturationBundle\Model\MassCreances;
use Ovesco\FacturationBundle\Util\IdList;
use PHPUnit\Framework\TestCase;

/** Id lists round-trip through hidden form fields, so the posted value is untrusted. */
final class IdListTest extends TestCase
{
    public function test_round_trips_ids(): void
    {
        $this->assertSame([3, 7], IdList::decode(IdList::encode(['3', 7])));
    }

    public function test_a_posted_php_serialized_object_is_never_instantiated(): void
    {
        $this->assertSame([], IdList::decode('O:8:"stdClass":0:{}'));
    }

    public function test_non_numeric_entries_are_dropped(): void
    {
        $this->assertSame([5], IdList::decode('[5, "x", {"a":1}]'));
    }

    public function test_mass_creances_counts_the_encoded_ids(): void
    {
        $mass = new MassCreances();
        $mass->setSelectedIds(IdList::encode([1, 2, 3]));

        $this->assertSame(3, $mass->amount);
    }
}
