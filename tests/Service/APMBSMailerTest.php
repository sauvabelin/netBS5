<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Service\APMBSMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

class APMBSMailerTest extends TestCase
{
    public function test_sends_to_the_client_then_a_follow_up_copy_to_the_cabane(): void
    {
        $mailer = new RecordingMailer();

        (new APMBSMailer($mailer))->sendEmailToClient($this->reservation(), 'Réservation validée', 'Bon séjour');

        $this->assertCount(2, $mailer->sent);
        [$client, $copy] = $mailer->sent;

        $this->assertSame('jeanne@example.com', $client->getTo()[0]->getAddress());
        $this->assertSame('[APMBS 42] - Réservation validée', $client->getSubject());
        $this->assertSame('moillettaz@sauvabelin.ch', $client->getFrom()[0]->getAddress());
        $this->assertSame('APMBS La Moillettaz', $client->getFrom()[0]->getName());
        $this->assertSame('emails/accepted.html.twig', $client->getHtmlTemplate());
        $this->assertSame('Bon séjour', $client->getContext()['message']);
        $this->assertFalse($client->getContext()['toApmbs']);

        $this->assertSame('moillettaz@sauvabelin.ch', $copy->getTo()[0]->getAddress());
        $this->assertSame('suivi res. 42', $copy->getSubject());
        $this->assertTrue($copy->getContext()['toApmbs']);
    }

    public function test_explicit_state_picks_the_template(): void
    {
        $mailer = new RecordingMailer();

        (new APMBSMailer($mailer))->sendEmailToClient($this->reservation(), 'Facture', null, 'invoice', ['extra' => 1]);

        $this->assertSame('emails/invoice.html.twig', $mailer->sent[0]->getHtmlTemplate());
        $this->assertSame(1, $mailer->sent[0]->getContext()['extra']);
    }

    private function reservation(): APMBSReservation
    {
        $cabane = new Cabane();
        $cabane->setNom('La Moillettaz');
        $cabane->setFromEmail('moillettaz@sauvabelin.ch');

        $reservation = new APMBSReservation();
        (new \ReflectionProperty(APMBSReservation::class, 'id'))->setValue($reservation, 42);
        $reservation->setCabane($cabane);
        $reservation->setStatus(APMBSReservation::ACCEPTED);
        $reservation->setEmail('jeanne@example.com');

        return $reservation;
    }
}

class RecordingMailer implements MailerInterface
{
    /** @var TemplatedEmail[] */
    public array $sent = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->sent[] = $message;
    }
}
