<?php

declare(strict_types=1);

namespace App\Tests\Controller\APMBS;

use App\Entity\APMBSReservation;
use App\Tests\Support\APMBSFixturesTrait;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Intendant proposes new dates; the client answers from the email.
 */
class ModifyReservationTest extends WebTestCase
{
    use APMBSFixturesTrait;
    use UserFactoryTrait;

    public function test_modification_email_links_let_the_client_accept(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $reservation = $this->persistReservation($em, APMBSReservation::PENDING);
        $client->loginUser($this->persistUser($client, 'apmbs-intendant', roles: ['ROLE_USER', 'ROLE_APMBS']), 'netbs');

        $crawler = $client->request('GET', "/netBS/bs/apmbs/reservation/{$reservation->getId()}/modify");
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form')->form();
        $form['modify_reservation[message]'] = 'Un jour plus tard ?';
        $client->submit($form);

        $this->assertSame(APMBSReservation::MODIFICATION_PENDING, $this->reloadReservation($em, $reservation)->getStatus());
        $this->assertEmailCount(2);
        $email = $this->sentTo('jeanne@example.com');
        $this->assertStringNotContainsString('jeanne@example.com/', $email->getHtmlBody(), 'links no longer carry the email');
        $this->assertStringNotContainsString('/client/accept/', $this->sentTo('cabane@example.com')->getHtmlBody(), 'no client links in the copy');

        $this->assertSame(1, preg_match('#https?://[^\s"]+/client/accept/\d+\?token=[\w-]+#', $email->getTextBody(), $m));
        $page = $client->request('GET', $m[0]);
        $client->submit($page->filter('form[method="post"]')->form());

        $this->assertSame(APMBSReservation::MODIFICATION_ACCEPTED, $this->reloadReservation($em, $reservation)->getStatus());
    }

    private function sentTo(string $address): \Symfony\Component\Mime\Email
    {
        foreach ($this->getMailerMessages() as $message) {
            if ($message->getTo()[0]->getAddress() === $address) {
                return $message;
            }
        }
        $this->fail("No email sent to $address");
    }

    public function test_requires_the_apmbs_role(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $reservation = $this->persistReservation($em, APMBSReservation::PENDING);
        $client->loginUser($this->persistUser($client, 'apmbs-outsider', roles: ['ROLE_USER']), 'netbs');

        $client->request('GET', "/netBS/bs/apmbs/reservation/{$reservation->getId()}/modify");

        $this->assertResponseStatusCodeSame(403);
    }
}
