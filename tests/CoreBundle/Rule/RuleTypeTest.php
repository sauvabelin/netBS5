<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use App\Entity\BSUser;
use NetBS\CoreBundle\Form\Type\RuleType;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\SecureBundle\Entity\Role;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class RuleTypeTest extends KernelTestCase
{
    private const EXPERT_ERROR = RuleType::EXPERT_DENIED;

    protected function setUp(): void
    {
        self::bootKernel();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get('request_stack')->push($request);
    }

    private function loginAs(string $role): void
    {
        // netBS reads roles from BaseUser::getAllRoles(), so the token needs a real (unsaved) user
        $user = new BSUser();
        $user->setUsername('rule-type-test');
        $user->addRole(self::getContainer()->get('doctrine.orm.entity_manager')->getRepository(Role::class)->findOneBy(['role' => $role]));
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'netbs', [$role]));
    }

    private function form(?string $initial = null): FormInterface
    {
        return self::getContainer()->get('form.factory')
            ->createBuilder(FormType::class, ['rule' => $initial], ['csrf_protection' => false])
            ->add('rule', RuleType::class, ['context' => 'test'])
            ->getForm();
    }

    private function submit(string $json, ?string $initial = null): FormInterface
    {
        $form = $this->form($initial);
        $form->submit(['rule' => $json]);

        return $form;
    }

    private function errors(FormInterface $form): array
    {
        return array_map(fn ($e) => $e->getMessage(), iterator_to_array($form->get('rule')->getErrors()));
    }

    private function nRule(): string
    {
        return Rule::blocks(new RuleGroup('and', [new RuleCondition('test.n', ['op' => 'ge', 'value' => 3])]))->toJson();
    }

    public function test_valid_rule_is_saved(): void
    {
        $this->loginAs('ROLE_USER');
        $form = $this->submit($this->nRule());

        $this->assertTrue($form->isValid());
        $this->assertSame($this->nRule(), $form->getData()['rule']);
    }

    public function test_empty_rule_is_null(): void
    {
        $this->loginAs('ROLE_USER');
        $this->assertNull($this->submit(Rule::empty()->toJson())->getData()['rule']);
        $this->assertNull($this->submit('')->getData()['rule']);
    }

    public function test_invalid_rule_has_error(): void
    {
        $this->loginAs('ROLE_USER');
        $form = $this->submit(Rule::blocks(new RuleGroup('and', [new RuleCondition('nope', [])]))->toJson());

        $this->assertFalse($form->isValid());
        $this->assertSame(['Condition inconnue : nope.'], $this->errors($form));
    }

    public function test_non_admin_cannot_submit_expert(): void
    {
        $this->loginAs('ROLE_USER');
        $form = $this->submit(Rule::expert('n > 1')->toJson());

        $this->assertFalse($form->isValid());
        $this->assertSame([self::EXPERT_ERROR], $this->errors($form));
    }

    public function test_non_admin_cannot_change_expert(): void
    {
        $this->loginAs('ROLE_USER');
        $form = $this->submit(Rule::expert('n > 2')->toJson(), Rule::expert('n > 1')->toJson());

        $this->assertSame([self::EXPERT_ERROR], $this->errors($form));
    }

    public function test_non_admin_keeps_unchanged_expert(): void
    {
        $this->loginAs('ROLE_USER');
        $form = $this->submit(Rule::expert('n > 1')->toJson(), Rule::expert('n > 1')->toJson());

        $this->assertTrue($form->isValid());
    }

    public function test_non_admin_may_replace_expert_with_blocks(): void
    {
        $this->loginAs('ROLE_USER');
        $form = $this->submit($this->nRule(), Rule::expert('n > 1')->toJson());

        $this->assertTrue($form->isValid());
    }

    public function test_admin_can_submit_expert(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $form = $this->submit(Rule::expert('n > 1')->toJson());

        $this->assertSame([], $this->errors($form));
        $this->assertTrue($form->isValid());
        $this->assertSame(Rule::expert('n > 1')->toJson(), $form->getData()['rule']);
    }

    public function test_view_carries_editor_config(): void
    {
        $this->loginAs('ROLE_USER');
        $view   = $this->form($this->nRule())->createView()['rule'];
        $config = json_decode($view->vars['rule_config'], true);

        $this->assertFalse($config['canExpert']);
        $this->assertArrayHasKey('test.n', $config['blocks']);
        $this->assertSame('operator', $config['blocks']['test.n']['params']['op']['kind']);
        $this->assertSame('n ≥ 3', $view->vars['rule_sentence']);
        $this->assertNull($config['blocks']['test.n']['negatedLabel']);
        $this->assertSame("n'est pas marqué", $config['blocks']['test.flag']['negatedLabel']);
        $this->assertSame('n', $view->vars['rule_glossary'][0]['name']);
        $this->assertNotEmpty($view->vars['rule_empty_text']);
    }

    public function test_empty_text_option(): void
    {
        $this->loginAs('ROLE_USER');
        $view = self::getContainer()->get('form.factory')
            ->createBuilder(FormType::class, ['rule' => null], ['csrf_protection' => false])
            ->add('rule', RuleType::class, ['context' => 'test', 'empty_text' => 'Personne.'])
            ->getForm()->createView()['rule'];

        $this->assertSame('Personne.', $view->vars['rule_empty_text']);
    }
}
