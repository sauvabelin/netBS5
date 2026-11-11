<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Controller;

use App\Tests\Support\ParametersSnapshotTrait;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Settings\SettingsRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ParameterControllerTest extends WebTestCase
{
    use UserFactoryTrait;
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
        foreach (['application', 'recherche', 'galerie', 'cotisations'] as $section) {
            $this->assertCount(1, $crawler->filter('#section-' . $section));
        }
        $this->assertCount(1, $crawler->filter('#settings-legacy'));
    }

    public function test_invalid_int_is_rejected_and_not_saved(): void
    {
        $this->params()->setValue('search', 'max_results', '1500');
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'recherche', ['search__max_results' => '0']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertCount(1, $this->client->getCrawler()->filter('#setting-search-max_results .is-invalid'));
        $this->assertSame('1500', $this->storedValue('search', 'max_results'));
    }

    public function test_valid_save_redirects_and_persists(): void
    {
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'recherche', ['search__max_results' => '2500']);

        $this->assertResponseRedirects($this->url());
        $this->assertSame('2500', $this->params()->getValue('search', 'max_results', false));
        $this->assertCount(1, $this->client->followRedirect()->filter('.toast.text-bg-success'));
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

    public function test_blank_secret_is_rejected(): void
    {
        $this->params()->setValue('galerie', 'parent_token', 'secret-token-1');
        $crawler = $this->openAsAdmin();

        $this->submitSection($crawler, 'galerie', ['galerie__parent_token' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertCount(1, $this->client->getCrawler()->filter('#setting-galerie-parent_token .is-invalid'));
        $this->assertSame('secret-token-1', $this->storedValue('galerie', 'parent_token'));
    }

    public function test_date_format_is_not_a_setting(): void
    {
        // date formats are code constants (Utils\DateFormat), no longer editable settings
        $registry = $this->client->getContainer()->get(SettingsRegistry::class);

        $this->assertArrayNotHasKey('formats', $registry->sections());
        $this->assertSame([], array_filter($registry->all(), fn ($d) => $d->namespace === 'format'));
    }

    public function test_missing_rule_setting_renders(): void
    {
        $crawler = $this->openAsAdmin();

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#setting-test-rule'));
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

    /** Stores the test section's other values, so the rendered form can be saved as is. */
    private function validTestSection(): void
    {
        $this->params()->setValue('test', 'plain_text', 'x');
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
