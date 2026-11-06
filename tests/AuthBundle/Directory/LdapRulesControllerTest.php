<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use App\Tests\Support\UserFactoryTrait;
use NetBS\AuthBundle\Directory\LldapClientInterface;
use NetBS\AuthBundle\Message\SyncDirectory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Publication rules and computed groups on the LDAP page, against
 * netbs5_test (inside a rolled-back transaction) and an in-memory lldap.
 */
final class LdapRulesControllerTest extends WebTestCase
{
    use DirectoryFixturesTrait;
    use UserFactoryTrait;

    private const URL = '/netBS/secure/administration/authentification/ldap';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->db = $this->client->getContainer()->get('doctrine.dbal.default_connection');
        $this->db->beginTransaction();
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_publication_rules');
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_computed_groups');
        static::getContainer()->set(LldapClientInterface::class, new FakeLldap());
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    public function testRequiresAdmin(): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'ldap-rules-user', null, null, ['ROLE_USER']), 'netbs');

        $this->client->request('POST', self::URL . '/rules/add');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAddRuleThroughModal(): void
    {
        $ids = $this->seedGroupeTree();
        $this->loginAdmin();

        $this->submitRule(self::URL . '/rules/add', 'Unités', self::blockRule('groupe.types', ['types' => [$ids['typeA']]]), 'direct');

        self::assertResponseStatusCodeSame(201);
        $row = $this->db->fetchAssociative('SELECT * FROM netbs_auth_directory_publication_rules');
        self::assertSame('Unités', $row['label']);
        self::assertSame(1, (int) $row['position']);
        self::assertSame('direct', $row['sous_groupes']);

        $this->submitRule(self::URL . '/rules/add', 'Deuxième', self::blockRule('groupe.parmi', ['groupes' => [$ids['gc']]]));
        self::assertSame([1, 2], array_map('intval', $this->db->fetchFirstColumn('SELECT position FROM netbs_auth_directory_publication_rules ORDER BY position')));
    }

    public function testEditRuleThroughModal(): void
    {
        $ids = $this->seedGroupeTree();
        $id = $this->publicationRule('Unités', self::blockRule('groupe.parmi', ['groupes' => [$ids['ga']]]));
        $this->loginAdmin();

        $this->submitRule(self::URL . "/rules/$id/edit", 'Unités renommée', self::blockRule('groupe.parmi', ['groupes' => [$ids['gc']]]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Unités renommée', $this->db->fetchOne('SELECT label FROM netbs_auth_directory_publication_rules WHERE id = ?', [$id]));
    }

    public function testEmptySelectionRejected(): void
    {
        $this->loginAdmin();

        $this->submitRule(self::URL . '/rules/add', 'Rien', '{"mode":"blocks","op":"and","items":[]}');

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM netbs_auth_directory_publication_rules'));
    }

    public function testMoveSwapsPositions(): void
    {
        [$first, $second] = $this->twoRules();
        $this->loginAdmin();

        $this->post("/rules/$second/move/up");

        self::assertResponseRedirects(self::URL);
        self::assertSame([$second, $first], $this->ruleOrder());
    }

    public function testMoveAtEdgesIsNoop(): void
    {
        [$first, $second] = $this->twoRules();
        $this->loginAdmin();

        $this->post("/rules/$first/move/up");
        $this->post("/rules/$second/move/down");

        self::assertSame([$first, $second], $this->ruleOrder());
    }

    public function testDeleteRequiresCsrf(): void
    {
        [$first] = $this->twoRules();
        $this->loginAdmin();

        $this->client->request('POST', self::URL . "/rules/$first/delete", ['_token' => 'nope']);

        self::assertResponseStatusCodeSame(403);
        self::assertContains($first, $this->ruleOrder());
    }

    public function testDeleteRemovesRuleAndStoredRules(): void
    {
        [$first, $second] = $this->twoRules();
        $rules = (int) $this->db->fetchOne('SELECT COUNT(*) FROM netbs_core_rules');
        $this->loginAdmin();

        $this->post("/rules/$first/delete");

        self::assertResponseRedirects(self::URL);
        self::assertSame([$second], $this->ruleOrder());
        self::assertSame($rules - 1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM netbs_core_rules'));

        // a new rule after a delete still goes last, with a unique position
        $gc = (int) $this->db->fetchOne("SELECT id FROM sauvabelin_netbs_groupes WHERE nom = 'dirproj-GC'");
        $this->submitRule(self::URL . '/rules/add', 'Après', self::blockRule('groupe.parmi', ['groupes' => [$gc]]));
        $positions = array_map('intval', $this->db->fetchFirstColumn('SELECT position FROM netbs_auth_directory_publication_rules ORDER BY position'));
        self::assertSame($positions, array_values(array_unique($positions)));
        self::assertSame('Après', $this->db->fetchOne('SELECT label FROM netbs_auth_directory_publication_rules ORDER BY position DESC LIMIT 1'));
    }

    public function testAddComputedGroupThroughModal(): void
    {
        $ids = $this->seedGroupeTree();
        $this->loginAdmin();

        $this->submitComputed(self::URL . '/computed/add', 'chefs', self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('chefs', $this->db->fetchOne('SELECT name FROM netbs_auth_directory_computed_groups'));
    }

    public function testComputedNameClashesAreRejected(): void
    {
        $ids = $this->seedGroupeTree();
        $this->computedGroup('chefs', self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));
        $this->loginAdmin();

        foreach (['Nextcloud-Admins', 'Chefs', 'chefs ', '***', 'netbs_users', 'LLDAP_admin'] as $name) {
            $this->submitComputed(self::URL . '/computed/add', $name, self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));
            self::assertResponseStatusCodeSame(403, $name);
        }
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM netbs_auth_directory_computed_groups'));
    }

    public function testComputedGroupDelete(): void
    {
        $ids = $this->seedGroupeTree();
        $id = $this->computedGroup('chefs', self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));
        $this->loginAdmin();

        $this->post("/computed/$id/delete");

        self::assertResponseRedirects(self::URL);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM netbs_auth_directory_computed_groups'));
    }

    public function testSavingQueuesDirectorySync(): void
    {
        $ids = $this->seedGroupeTree();
        $this->loginAdmin();
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $transport->reset();

        $this->submitRule(self::URL . '/rules/add', 'Unités', self::blockRule('groupe.parmi', ['groupes' => [$ids['ga']]]));

        $sent = $transport->getSent();
        self::assertNotEmpty($sent);
        self::assertInstanceOf(SyncDirectory::class, $sent[0]->getMessage());
    }

    /** @return array{int, int} two rules, in position order */
    private function twoRules(): array
    {
        $ids = $this->seedGroupeTree();
        return [
            $this->publicationRule('Premier', self::blockRule('groupe.parmi', ['groupes' => [$ids['ga']]])),
            $this->publicationRule('Second', self::blockRule('groupe.parmi', ['groupes' => [$ids['gc']]])),
        ];
    }

    /** @return int[] rule ids by position */
    private function ruleOrder(): array
    {
        return array_map('intval', $this->db->fetchFirstColumn('SELECT id FROM netbs_auth_directory_publication_rules ORDER BY position, id'));
    }

    /** Submits the page's own form for $path, so the CSRF token is the real one. */
    private function post(string $path): void
    {
        $crawler = $this->client->request('GET', self::URL);
        $this->client->submit($crawler->filter(sprintf('form[action="%s%s"]', self::URL, $path))->form());
    }

    private function submitRule(string $url, string $label, string $selection, string $mode = 'tous'): void
    {
        $form = $this->client->request('POST', $url)->filter('form')->form();
        $form['directory_publication_rule[label]'] = $label;
        $form['directory_publication_rule[selectionJson]'] = $selection;
        $form['directory_publication_rule[sousGroupes]'] = $mode;
        $this->client->submit($form);
    }

    private function submitComputed(string $url, string $name, string $membres): void
    {
        $form = $this->client->request('POST', $url)->filter('form')->form();
        $form['directory_computed_group[name]'] = $name;
        $form['directory_computed_group[membresJson]'] = $membres;
        $this->client->submit($form);
    }

    private function loginAdmin(): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'ldap-rules-admin', null, null, ['ROLE_USER', 'ROLE_ADMIN']), 'netbs');
    }
}
