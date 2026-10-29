<?php

namespace App\Service;

use App\Entity\APMBSReservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class APMBSMailer {

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function sendEmailToClient(APMBSReservation $reservation, $title, $message = null, $state = null, array $data = [], $attachment = null, $toApmbs = false) {

        if (!$state) {
            $state = $reservation->getStatus();
        }

        $template = explode('_', $state);
        $tplName = count($template) > 1 ? $template[1] : $template[0];
        $to = $toApmbs ? $reservation->getCabane()->getFromEmail() : $reservation->getEmail();
        $subject = $toApmbs ? "suivi res. {$reservation->getId()}" : "[APMBS {$reservation->getId()}] - $title";

        $email = (new TemplatedEmail())
            ->from(new Address($reservation->getCabane()->getFromEmail(), "APMBS {$reservation->getCabane()->getNom()}"))
            ->to(new Address($to))
            ->subject($subject)
            ->htmlTemplate("emails/$tplName.html.twig")
            ->context(array_merge($data, [
                'reservation' => $reservation,
                'message' => $message,
                'toApmbs' => $toApmbs,
            ]));

        if ($attachment) {
            $email = $email->attach(
                $attachment['file'],
                $attachment['filename'],
                $attachment['mimetype']
            );
        }

        $this->mailer->send($email);

        if (!$toApmbs) {
            try {
                $this->sendEmailToClient($reservation, $title, $message, $state, $data, $attachment, true);
            } catch (\Exception $e) {
            }
        }
    }
}
