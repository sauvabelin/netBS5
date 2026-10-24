<?php

declare(strict_types=1);

namespace App\Tests\Ldap;

use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Entity\GroupeType;
use PHPUnit\Framework\TestCase;

final class GroupeTypeSettingsTest extends TestCase
{
    public function testDefaults(): void
    {
        $type = new GroupeType();
        self::assertFalse($type->isCreeGroupe());
        self::assertSame('tous', $type->getSousGroupes());
        self::assertFalse((new Fonction())->isCompteDansParent());
    }

    public function testRejectsUnknownSousGroupes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new GroupeType())->setSousGroupes('enfants');
    }

    public function testChoicesCoverAllModes(): void
    {
        self::assertSame(['direct', 'fonctions', 'tous'], array_values(GroupeType::getSousGroupesChoices()));
    }
}
