<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FactureModelFormTest extends WebTestCase
{
    use UserFactoryTrait;

    private const LEGACY = 'facture.getRappels().count() == 1 && facture.getLatestImpression() !== null';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->cleanUp();
        $user = $this->persistUser($this->client, 'facture-model-tresorier', roles: ['ROLE_USER', 'ROLE_TRESORIER']);
        $this->client->loginUser($user, 'netbs');
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement("DELETE FROM ovesco_facturation_facture_models WHERE name LIKE 'rule-test%'");
        $connection->executeStatement("DELETE FROM netbs_core_rules WHERE context = 'facture' AND id NOT IN (SELECT rule_id FROM ovesco_facturation_facture_models WHERE rule_id IS NOT NULL)");
    }

    private function legacyModel(): FactureModel
    {
        $model = new FactureModel();
        foreach (['setName' => 'rule-test modèle', 'setTitre' => 'Facture', 'setTopDescription' => 'Haut', 'setBottomSalutations' => 'Bas',
                  'setSignataire' => 'S', 'setGroupName' => 'G', 'setRue' => 'R', 'setNpaVille' => 'N', 'setCityFrom' => 'C'] as $setter => $value) {
            $model->$setter($value);
        }
        $model->setPoids(5);
        $model->setApplicationRule(self::LEGACY);
        $model->setTextesFormat(FactureModel::TEXTES_ANCIEN);
        $model->setTopDescription("Madame,\\n\r\nNous [facture.getFactureId()]");
        $this->em->persist($model);
        $this->em->flush();

        return $model;
    }

    /** Opens the modal's form and submits it with the given rule JSON. */
    private function submit(string $route, array $routeParams, string $ruleJson): void
    {
        $url     = $this->client->getContainer()->get('router')->generate($route, $routeParams);
        $crawler = $this->client->request('GET', $url);
        $form    = $crawler->filter('form')->form();
        $form['facture_model[ruleJson]'] = $ruleJson;
        $this->client->submit($form);
    }

    private function reload(int $id): FactureModel
    {
        $this->em->clear();

        return $this->em->getRepository(FactureModel::class)->find($id);
    }

    private function blocksRule(): string
    {
        return Rule::blocks(new RuleGroup('and', [new RuleCondition('facture.nombre_rappels', ['op' => 'gt', 'nombre' => 1])]))->toJson();
    }

    public function test_tresorier_cannot_post_expert_rule(): void
    {
        $model = $this->legacyModel();

        $this->submit('ovesco.facturation.facture_model.edit_modal', ['id' => $model->getId()], Rule::expert('true')->toJson());

        $reloaded = $this->reload($model->getId());
        $this->assertSame(self::LEGACY, $reloaded->getApplicationRule());
        $this->assertNull($reloaded->getRule());
    }

    public function test_malformed_rule_json_is_a_form_error(): void
    {
        $model = $this->legacyModel();

        $this->submit('ovesco.facturation.facture_model.edit_modal', ['id' => $model->getId()], '{bad');

        $this->assertNotSame(500, $this->client->getResponse()->getStatusCode(), substr(strip_tags((string) $this->client->getResponse()->getContent()), 0, 300));
        $this->assertStringContainsString('Règle invalide.', $this->client->getResponse()->getContent());
        $reloaded = $this->reload($model->getId());
        $this->assertSame(self::LEGACY, $reloaded->getApplicationRule());
        $this->assertNull($reloaded->getRule());
    }

    public function test_untouched_legacy_expression_creates_no_rule(): void
    {
        $model = $this->legacyModel();

        $this->submit('ovesco.facturation.facture_model.edit_modal', ['id' => $model->getId()], Rule::expert(self::LEGACY)->toJson());

        $reloaded = $this->reload($model->getId());
        $this->assertSame(self::LEGACY, $reloaded->getApplicationRule());
        $this->assertNull($reloaded->getRule());
    }

    public function test_blocks_rule_replaces_legacy_expression(): void
    {
        $model = $this->legacyModel();

        $this->submit('ovesco.facturation.facture_model.edit_modal', ['id' => $model->getId()], $this->blocksRule());

        $reloaded = $this->reload($model->getId());
        $this->assertNull($reloaded->getApplicationRule());
        $this->assertSame($this->blocksRule(), $reloaded->getRule()->getDefinition());
        $this->assertSame('facture', $reloaded->getRule()->getContext());
    }

    public function test_duplicate_copies_rule_into_new_row(): void
    {
        $model = $this->legacyModel();
        $model->setRuleJson($this->blocksRule());
        $this->em->flush();

        $this->submit('ovesco.facturation.facture_model.duplicate', ['id' => $model->getId()], $this->blocksRule());

        $copy = $this->em->getRepository(FactureModel::class)->findOneBy(['name' => 'rule-test modèle (copie)']);
        $this->assertNotNull($copy);
        $this->assertNotSame($model->getRule()->getId(), $copy->getRule()->getId());
        $this->assertSame($this->blocksRule(), $copy->getRule()->getDefinition());
    }

    public function test_old_texts_are_shown_converted_and_saved_in_new_format(): void
    {
        $model = $this->legacyModel();
        $url   = $this->client->getContainer()->get('router')->generate('ovesco.facturation.facture_model.edit_modal', ['id' => $model->getId()]);

        $crawler = $this->client->request('GET', $url);
        $this->assertSame("Madame,\nNous {facture.numero}", $crawler->filter('textarea[name="facture_model[topDescription]"]')->text(null, false));

        $this->client->submit($crawler->filter('form')->form());

        $reloaded = $this->reload($model->getId());
        $this->assertSame(FactureModel::TEXTES_NOUVEAU, $reloaded->getTextesFormat());
        $this->assertSame("Madame,\nNous {facture.numero}", str_replace("\r\n", "\n", $reloaded->getTopDescription()));
    }

    public function test_apmbs_model_texts_are_left_alone(): void
    {
        $model = $this->legacyModel();
        $model->setName('APMBS');
        $this->em->flush();

        try {
            $url     = $this->client->getContainer()->get('router')->generate('ovesco.facturation.facture_model.edit_modal', ['id' => $model->getId()]);
            $crawler = $this->client->request('GET', $url);
            $this->assertSame("Madame,\\n\nNous [facture.getFactureId()]", $crawler->filter('textarea[name="facture_model[topDescription]"]')->text(null, false));
            $this->client->submit($crawler->filter('form')->form());

            $this->assertSame(FactureModel::TEXTES_ANCIEN, $this->reload($model->getId())->getTextesFormat());
        } finally {
            $this->em->getConnection()->executeStatement('DELETE FROM ovesco_facturation_facture_models WHERE id = ?', [$model->getId()]);
        }
    }
}
