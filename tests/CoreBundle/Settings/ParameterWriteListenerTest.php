<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Settings;

use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\Parameter;
use NetBS\CoreBundle\Exceptions\UserConstraintException;
use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The Paramètres page is not the only way to write a parameter: the XEditable endpoint and
 * any code persisting a Parameter must go through the same validation.
 */
final class ParameterWriteListenerTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->deleteTestRows();
    }

    protected function tearDown(): void
    {
        $this->deleteTestRows();
        parent::tearDown();
    }

    public function test_xeditable_cannot_store_invalid_declared_value(): void
    {
        $parameter = $this->storeDefaultedInt('5');

        $this->editThroughXEditable($parameter, 'abc');

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Nombre entier attendu.', $this->client->getResponse()->getContent());
        $this->assertSame('5', $this->storedValue());
    }

    public function test_direct_persist_invalid_throws(): void
    {
        $this->expectException(UserConstraintException::class);

        $this->em->persist(new Parameter('test', 'defaulted_int', 'abc'));
        $this->em->flush();
    }

    public function test_xeditable_edit_refreshes_cache(): void
    {
        $parameter = $this->storeDefaultedInt('5');
        $params    = $this->client->getContainer()->get(ParameterManager::class);
        $this->assertSame('5', $params->getValue('test', 'defaulted_int'));

        $this->editThroughXEditable($parameter, '9');

        $this->assertResponseIsSuccessful();
        $params = $this->client->getContainer()->get(ParameterManager::class);
        $this->assertSame('9', $params->getValue('test', 'defaulted_int'));
    }

    private function storeDefaultedInt(string $value): Parameter
    {
        $parameter = new Parameter('test', 'defaulted_int', $value);
        $this->em->persist($parameter);
        $this->em->flush();

        return $parameter;
    }

    private function editThroughXEditable(Parameter $parameter, string $value): void
    {
        $admin = $this->persistUser($this->client, 'settings-admin', roles: ['ROLE_USER', 'ROLE_ADMIN']);
        $this->client->loginUser($admin, 'netbs');

        $this->client->request('POST', $this->client->getContainer()->get('router')->generate('netbs.core.xeditable.endpoint'), [
            'pk'    => (string) $parameter->getId(),
            'name'  => json_encode(['field' => 'value', 'type' => 'text', 'multiple' => false, 'itemClass' => base64_encode(Parameter::class)]),
            'value' => $value,
        ]);
    }

    private function storedValue(): ?string
    {
        return $this->em->getConnection()->fetchOne(
            "SELECT value FROM netbs_core_parameters WHERE namespace = 'test' AND paramKey = 'defaulted_int'");
    }

    private function deleteTestRows(): void
    {
        $this->em->getConnection()->executeStatement("DELETE FROM netbs_core_parameters WHERE namespace = 'test'");
    }
}
