<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use App\Tests\Support\FichierRowsTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\EntityTargets;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EntityTargetsTest extends KernelTestCase
{
    use FichierRowsTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->removeFichierRows($this->em);
        parent::tearDown();
    }

    public function test_labels_skip_deleted(): void
    {
        $live    = $this->createGroupe($this->em, 'rule-test vivant');
        $deleted = $this->createGroupe($this->em, 'rule-test supprimé');
        $this->em->remove($deleted);
        $this->em->flush();

        $targets = self::getContainer()->get(EntityTargets::class);
        $this->assertSame([$live->getId() => (string) $live], $targets->labels('groupe', [$live->getId(), $deleted->getId()]));
        $this->assertSame([], $targets->labels('groupe', []));

        $this->expectException(\LogicException::class);
        $targets->classFor('nope');
    }
}
