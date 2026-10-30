<?php

declare(strict_types=1);

namespace App\Tests\Controller\APMBS;

use App\Entity\APMBSReservation;
use App\Service\APMBSClientLinks;
use App\Tests\Support\APMBSFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The client's answer to a date change proposed by an intendant, reached
 * from the links in the modification email.
 */
class ClientAnswerTest extends WebTestCase
{
    use APMBSFixturesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        @unlink(static::getContainer()->getParameter('kernel.project_dir') . '/var/apmbs_calendar.test.json');
    }

    public function test_opening_the_link_only_asks_for_confirmation(): void
    {
        $reservation = $this->pendingModification();

        $this->client->request('GET', $this->links()->url($reservation, 'accept'));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[method="post"] button[type="submit"]');
        $this->assertSelectorTextContains('body', 'Accepter les nouvelles dates');
        $this->assertSame(APMBSReservation::MODIFICATION_PENDING, $this->reload($reservation)->getStatus());
        $this->assertEmailCount(0);
    }

    public function test_confirming_accept_applies_the_modification(): void
    {
        $reservation = $this->pendingModification();

        $url = $this->links()->url($reservation, 'accept');
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('form[method="post"]')->form());

        // Redirect, not a 200: Turbo drops a 200 answer to a form POST.
        $this->assertResponseRedirects($url, 303);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'confirmé');
        $reservation = $this->reload($reservation);
        $this->assertSame(APMBSReservation::MODIFICATION_ACCEPTED, $reservation->getStatus());
        $this->assertNotNull($reservation->getGCEventId(), 'event written to the calendar');
        $this->assertSame('modification_acceptation', $reservation->getLogs()->last()->getAction());
    }

    public function test_confirming_reject_cancels_and_notifies(): void
    {
        $reservation = $this->pendingModification();

        $crawler = $this->client->request('GET', $this->links()->url($reservation, 'reject'));
        $this->client->submit($crawler->filter('form[method="post"]')->form());

        $this->assertResponseStatusCodeSame(303);
        $this->assertEmailCount(2); // client + copy to the cabane
        $this->assertEmailAddressContains($this->getMailerMessage(0), 'To', 'jeanne@example.com');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'annulée');
        $this->assertSame(APMBSReservation::CANCELLED, $this->reload($reservation)->getStatus());
    }

    public function test_bad_token_is_refused_on_both_methods(): void
    {
        $reservation = $this->pendingModification();
        $url = "/api/v1/public/netBS/apmbs/client/accept/{$reservation->getId()}";

        $this->client->request('GET', $url . '?token=forged');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', $url, ['token' => $this->links()->token($reservation, 'reject')]);
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', $url);
        $this->assertResponseStatusCodeSame(403);

        $this->assertSame(APMBSReservation::MODIFICATION_PENDING, $this->reload($reservation)->getStatus());
    }

    public function test_answering_twice_changes_nothing(): void
    {
        $reservation = $this->pendingModification();
        $url = $this->links()->url($reservation, 'accept');

        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('form[method="post"]')->form());

        $this->client->request('GET', $this->links()->url($reservation, 'reject'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('form[method="post"]');
        $this->assertSelectorTextContains('body', 'déjà');

        $this->client->request('POST', "/api/v1/public/netBS/apmbs/client/reject/{$reservation->getId()}", [
            'token' => $this->links()->token($reservation, 'reject'),
        ]);
        $this->assertSame(APMBSReservation::MODIFICATION_ACCEPTED, $this->reload($reservation)->getStatus());
    }

    public function test_links_from_emails_sent_before_the_change_lead_to_the_confirmation_page(): void
    {
        $reservation = $this->pendingModification();

        $this->client->request('GET', "/api/v1/public/netBS/apmbs/client/accept/{$reservation->getId()}/jeanne@example.com");
        $this->assertResponseRedirects($this->links()->url($reservation, 'accept'));
        $this->assertSame(APMBSReservation::MODIFICATION_PENDING, $this->reload($reservation)->getStatus());

        $this->client->request('GET', "/api/v1/public/netBS/apmbs/client/reject/{$reservation->getId()}/someone@else.com");
        $this->assertResponseStatusCodeSame(403);
    }

    private function pendingModification(): APMBSReservation
    {
        return $this->persistReservation($this->em, APMBSReservation::MODIFICATION_PENDING);
    }

    private function reload(APMBSReservation $reservation): APMBSReservation
    {
        return $this->reloadReservation($this->em, $reservation);
    }

    private function links(): APMBSClientLinks
    {
        return static::getContainer()->get(APMBSClientLinks::class);
    }
}
