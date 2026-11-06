<?php

declare(strict_types=1);

namespace App\Tests\Settings;

use App\Entity\BSMembre;
use App\Listener\DoctrineMembreAdabsIdListener;
use App\Settings\BsReferences;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use NetBS\CoreBundle\Service\ParameterManager;
use PHPUnit\Framework\TestCase;

final class BsReferencesTest extends TestCase
{
    /** @dataProvider rawValues */
    public function test_id_parses_the_stored_value(?string $raw, ?int $expected): void
    {
        $this->assertSame($expected, $this->refs($raw)->id('groupe.adabs_id'));
    }

    public static function rawValues(): iterable
    {
        yield 'set'        => ['12', 12];
        yield 'padded'     => [' 12 ', 12];
        yield 'unset'      => [null, null];
        yield 'blank'      => ['', null];
        yield 'sentinel'   => ['-1', null];
        yield 'zero'       => ['0', null];
        yield 'not an int' => ['abc', null];
    }

    public function test_ids_keeps_only_the_set_ones(): void
    {
        $params = $this->createMock(ParameterManager::class);
        $params->method('getValue')->willReturnMap([
            ['bs', 'fonction.cp_id', true, '3'],
            ['bs', 'fonction.cl_id', true, null],
            ['bs', 'fonction.rouge_id', true, '7'],
        ]);

        $this->assertSame([3, 7], (new BsReferences($params))->ids('fonction.cp_id', 'fonction.cl_id', 'fonction.rouge_id'));
    }

    public function test_adabs_listener_passes_the_id_through(): void
    {
        $membre = $this->createMock(BSMembre::class);
        $membre->expects($this->once())->method('_setAdabsId')->with(3);

        (new DoctrineMembreAdabsIdListener($this->refs('3')))
            ->postLoad(new LifecycleEventArgs($membre, $this->createMock(EntityManager::class)));
    }

    public function test_adabs_listener_passes_null_when_unset(): void
    {
        $membre = $this->createMock(BSMembre::class);
        $membre->expects($this->once())->method('_setAdabsId')->with(null);

        (new DoctrineMembreAdabsIdListener($this->refs(null)))
            ->postLoad(new LifecycleEventArgs($membre, $this->createMock(EntityManager::class)));
    }

    private function refs(?string $raw): BsReferences
    {
        $params = $this->createMock(ParameterManager::class);
        $params->method('getValue')->willReturn($raw);

        return new BsReferences($params);
    }
}
