<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Entity\BSUser;
use NetBS\SecureBundle\Entity\Role;
use Ovesco\FacturationBundle\Validator\FactureText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\ConstraintViolationInterface;
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

    /** @return list<ConstraintViolationInterface> */
    private function violations(string $text, ?string $previous = null): array
    {
        return iterator_to_array(self::getContainer()->get(ValidatorInterface::class)->validate($text, new FactureText(previous: $previous)));
    }

    /** Which constraint message each violation used (the template, not its wording). */
    private function templates(string $text, ?string $previous = null): array
    {
        return array_map(fn (ConstraintViolationInterface $v) => $v->getMessageTemplate(), $this->violations($text, $previous));
    }

    public function test_unknown_variable(): void
    {
        $this->loginAs('ROLE_USER');
        $violations = $this->violations('Bonjour {nom}');
        $this->assertCount(1, $violations);
        $this->assertSame((new FactureText())->unknownMessage, $violations[0]->getMessageTemplate());
        $this->assertSame('{nom}', $violations[0]->getParameters()['{{ name }}']);
        $this->assertSame([], $this->templates("Bonjour {debiteur}, {pluriel_creances:l'objet|les objets}"));
    }

    public function test_non_admin_cannot_add_expression(): void
    {
        $this->loginAs('ROLE_USER');
        $this->assertSame([(new FactureText())->expertMessage], $this->templates('n° [facture.getId()]', 'n°'));
    }

    public function test_non_admin_keeps_existing_expression(): void
    {
        $this->loginAs('ROLE_USER');
        $this->assertSame([], $this->templates('Total : [facture.getMontant()] CHF', 'Montant [facture.getMontant()]'));
    }

    public function test_admin_adds_expression(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $this->assertSame([], $this->templates('n° [facture.getId()]', 'n°'));
    }

    public function test_invalid_expression(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $this->assertSame([(new FactureText())->invalidMessage], $this->templates('[nope.getId()]'));
    }
}
