<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Controller;

use App\Entity\BSMembre;
use App\Tests\Support\UserFactoryTrait;
use NetBS\CoreBundle\Entity\DynamicList;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Only the owner of a dynamic list may manage who it is shared with. */
final class DynamicListShareTest extends WebTestCase
{
    use UserFactoryTrait;

    protected function tearDown(): void
    {
        $this->deleteTestLists();
        parent::tearDown();
    }

    private function deleteTestLists(): void
    {
        static::getContainer()->get('doctrine.orm.entity_manager')->getConnection()
            ->executeStatement("DELETE FROM netbs_core_dynamic_lists WHERE name = 'dynlist-share-test'");
    }

    public function test_someone_else_cannot_open_the_share_modal_of_a_list(): void
    {
        $client = static::createClient();
        $em     = $client->getContainer()->get('doctrine.orm.entity_manager');
        $this->deleteTestLists();
        $owner  = $this->persistUser($client, 'dynlist-owner', roles: ['ROLE_USER']);
        $other  = $this->persistUser($client, 'dynlist-other', roles: ['ROLE_USER']);

        $list = (new DynamicList())->setName('dynlist-share-test')->setItemsClass(BSMembre::class);
        $list->setOwner($owner);
        $em->persist($list);
        $em->flush();

        $client->loginUser($other, 'netbs');
        $client->request('GET', '/netBS/dynamic-list/modal/add-share-to-list/' . $list->getId());

        $this->assertResponseStatusCodeSame(403);
    }
}
