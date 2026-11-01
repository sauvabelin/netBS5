<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Entity\BSUser;
use NetBS\SecureBundle\Entity\Role;
use Ovesco\FacturationBundle\Validator\FactureText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FactureTextValidatorTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    private function loginAs(string $role): void
    {
        $user = new BSUser();
        $user->setUsername('facture-text-test');
        $user->addRole(self::getContainer()->get('doctrine.orm.entity_manager')->getRepository(Role::class)->findOneBy(['role' => $role]));
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'netbs', [$role]));
    }

    private function errors(string $text, ?string $previous = null): array
    {
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($text, new FactureText(previous: $previous));

        return array_map(fn ($v) => $v->getMessage(), iterator_to_array($violations));
    }

    public function test_unknown_variable(): void
    {
        $this->loginAs('ROLE_USER');
        $this->assertSame(['Variable inconnue : {nom}.'], $this->errors('Bonjour {nom}'));
        $this->assertSame([], $this->errors("Bonjour {debiteur}, {pluriel_creances:l'objet|les objets}"));
    }

    public function test_non_admin_cannot_add_expression(): void
    {
        $this->loginAs('ROLE_USER');
        $this->assertSame(['Seul un administrateur peut ajouter ou modifier une expression [...].'], $this->errors('n° [facture.getId()]', 'n°'));
    }

    public function test_non_admin_keeps_existing_expression(): void
    {
        $this->loginAs('ROLE_USER');
        $this->assertSame([], $this->errors('Total : [facture.getMontant()] CHF', 'Montant [facture.getMontant()]'));
    }

    public function test_admin_adds_expression(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $this->assertSame([], $this->errors('n° [facture.getId()]', 'n°'));
    }

    public function test_invalid_expression(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $errors = $this->errors('[nope.getId()]');
        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('Expression invalide : ', $errors[0]);
    }
}
