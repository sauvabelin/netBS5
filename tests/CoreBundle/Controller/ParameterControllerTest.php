<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Controller;

use App\Tests\Support\FichierRowsTrait;
use App\Tests\Support\ParametersSnapshotTrait;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ParameterControllerTest extends WebTestCase
{
    use UserFactoryTrait;
    use FichierRowsTrait;
    use ParametersSnapshotTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->emptyParameters($this->em);
    }

    protected function tearDown(): void
    {
        $this->removeFichierRows($this->em);
        $this->restoreParameters($this->em);
        $this->em->getConnection()->executeStatement("DELETE FROM netbs_core_rules WHERE code LIKE 'test.%'");
        parent::tearDown();
    }

    public function test_non_admin_gets_403(): void
    {
        $user = $this->persistUser($this->client, 'settings-member', roles: ['ROLE_USER']);
        $this->client->loginUser($user, 'netbs');

        $this->client->request('GET', $this->url());

        $this->assertResponseStatusCodeSame(403);
    }

    public function test_page_renders_with_no_settings_configured(): void
    {
        $crawler = $this->openAsAdmin();

        $this->assertResponseIsSuccessful();
        $titles = implode(' | ', $crawler->filter('.card-title')->each(fn(Crawler $c) => $c->text()));
        foreach (['Application', 'Recherche', 'Galerie', 'Brigade', 'Legacy'] as $section) {
            $this->assertStringContainsString($section, $titles);
        }
        $this->assertStringContainsString('Non configuré', $crawler->filter('#setting-galerie-parent_token')->text());
        $this->assertStringNotContainsString('bousillez', $this->client->getResponse()->getContent());
    }

    public function test_invalid_int_is_rejected_and_not_saved(): void
    {
        $this->params()->setValue('search', 'max_results', '1500');
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'recherche', ['search__max_results' => '0']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Doit être entre 1 et 10000.', $this->client->getResponse()->getContent());
        $this->assertSame('1500', $this->storedValue('search', 'max_results'));
    }

    public function test_valid_save_redirects_and_persists(): void
    {
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'recherche', ['search__max_results' => '2500']);

        $this->assertResponseRedirects($this->url());
        $this->assertSame('2500', $this->params()->getValue('search', 'max_results', false));
        $this->client->followRedirect();
        $this->assertStringContainsString('Paramètres « Recherche » enregistrés.', $this->client->getResponse()->getContent());
    }

    public function test_saving_one_section_leaves_others_untouched(): void
    {
        $this->params()->setValue('app', 'name', 'X');
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'recherche', ['search__max_results' => '2500']);

        $this->assertResponseRedirects();
        $this->assertSame('X', $this->storedValue('app', 'name'));
        $this->assertNull($this->storedValue('app', 'organization'), 'an untouched section must not be written');
    }

    public function test_secret_is_shown_in_plain_text(): void
    {
        $this->params()->setValue('galerie', 'parent_token', 'secret-token-1');
        $crawler = $this->openAsAdmin();

        $input = $crawler->filter('#setting-galerie-parent_token input[name="settings_galerie[galerie__parent_token]"]');
        $this->assertSame('text', $input->attr('type'));
        $this->assertSame('secret-token-1', $input->attr('value'));
        $this->assertCount(0, $crawler->filter('#setting-galerie-parent_token [data-controller="password-toggle"]'));
    }

    public function test_blank_secret_is_rejected(): void
    {
        $this->params()->setValue('galerie', 'parent_token', 'secret-token-1');
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'galerie', ['galerie__parent_token' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Valeur requise.', $this->client->getResponse()->getContent());
        $this->assertSame('secret-token-1', $this->storedValue('galerie', 'parent_token'));
    }

    public function test_broken_reference_is_flagged(): void
    {
        $groupe = $this->createGroupe($this->em);
        $id     = $groupe->getId();
        $this->params()->setValue('bs', 'groupe.adabs_id', (string) $id);
        $this->em->remove($groupe);
        $this->em->flush();

        $crawler = $this->openAsAdmin();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString("Référence supprimée (id $id).", $crawler->filter('#setting-bs-groupe-adabs_id')->text());
    }

    public function test_configured_reference_shows_its_current_name(): void
    {
        $groupe = $this->createGroupe($this->em, 'ADABS renommé');
        $this->params()->setValue('bs', 'groupe.adabs_id', (string) $groupe->getId());

        $crawler = $this->openAsAdmin();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('ADABS renommé', $crawler->filter('#setting-bs-groupe-adabs_id')->html());
    }

    public function test_date_format_is_not_a_setting(): void
    {
        $crawler = $this->openAsAdmin();

        $this->assertCount(0, $crawler->filter('#section-formats'));
    }

    public function test_legacy_card_lists_only_undeclared_keys(): void
    {
        $this->params()->setValue('bs', 'usernames_cordelettes', 'a,b');
        $this->params()->setValue('app', 'organization', 'Org X');

        $crawler = $this->openAsAdmin();

        $legacy = $crawler->filter('#settings-legacy')->text();
        $this->assertStringContainsString('usernames_cordelettes', $legacy);
        $this->assertStringNotContainsString('organization', $legacy);
    }

    public function test_missing_rule_setting_shows_fallback_help(): void
    {
        $crawler = $this->openAsAdmin();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString("Règle non définie, l'ancienne logique est utilisée.", $crawler->filter('#setting-test-rule')->text());
    }

    public function test_rule_setting_saves_from_page(): void
    {
        $this->validTestSection();
        $crawler = $this->openAsAdmin();
        $json    = Rule::blocks(new RuleGroup('and', [new RuleCondition('test.n', ['op' => 'ge', 'value' => 3])]))->toJson();

        $this->submitSection($crawler, 'test', ['test__rule' => $json]);

        $this->assertResponseRedirects($this->url());
        $this->assertSame($json, $this->ruleRow());
    }

    public function test_invalid_rule_is_422(): void
    {
        $this->validTestSection();
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'test', ['test__rule' => Rule::blocks(new RuleGroup('and', [new RuleCondition('nope', [])]))->toJson()]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Condition inconnue : nope.', $this->client->getResponse()->getContent());
        $this->assertNull($this->ruleRow());
    }

    public function test_emptying_rule_deletes_row(): void
    {
        $this->params()->setValue('test', 'rule', Rule::expert('n > 1')->toJson());
        $this->validTestSection();
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'test', ['test__rule' => Rule::empty()->toJson()]);

        $this->assertResponseRedirects($this->url());
        $this->assertNull($this->ruleRow());
    }

    /** Stores the test section's other (important) values, so the rendered form can be saved as is. */
    private function validTestSection(): array
    {
        $this->params()->setValue('test', 'important_text', 'x');
        $this->params()->setValue('test', 'ref', (string) $this->createGroupe($this->em)->getId());

        return [];
    }

    private function ruleRow(): ?string
    {
        $value = $this->em->getConnection()->fetchOne("SELECT definition FROM netbs_core_rules WHERE code = 'test.rule'");

        return $value === false ? null : $value;
    }

    private function openAsAdmin(): Crawler
    {
        $admin = $this->persistUser($this->client, 'settings-admin', roles: ['ROLE_USER', 'ROLE_ADMIN']);
        $this->client->loginUser($admin, 'netbs');

        return $this->client->request('GET', $this->url());
    }

    /** @param array<string,string> $fields field name inside the section form => value */
    private function submitSection(Crawler $crawler, string $section, array $fields): void
    {
        $form   = $crawler->filter("form[name=\"settings_$section\"]")->form();
        $values = [];
        foreach ($fields as $name => $value) {
            $values["settings_{$section}[$name]"] = $value;
        }

        $this->client->submit($form, $values);
    }

    private function storedValue(string $ns, string $key): ?string
    {
        $value = $this->em->getConnection()->fetchOne(
            'SELECT value FROM netbs_core_parameters WHERE namespace = ? AND paramKey = ?', [$ns, $key]);

        return $value === false ? null : $value;
    }

    private function params(): ParameterManager
    {
        return $this->client->getContainer()->get(ParameterManager::class);
    }

    private function url(): string
    {
        return $this->client->getContainer()->get('router')->generate('netbs.core.parameters.list');
    }
}
