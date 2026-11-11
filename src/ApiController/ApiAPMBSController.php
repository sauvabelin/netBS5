<?php

namespace App\ApiController;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Entity\CabaneTimePeriod;
use App\Entity\ReservationLog;
use App\Service\APMBSClientLinks;
use App\Service\GoogleCalendarManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/v1/public/netBS/apmbs')]
class ApiAPMBSController extends AbstractController
{
    /**
     * Required string fields of the public reservation payload, mapped to the entity property they fill.
     */
    private const RESERVATION_FIELDS = [
        'firstname' => 'prenom',
        'lastname'  => 'nom',
        'email'     => 'email',
        'phone'     => 'phone',
        'unit'      => 'unite',
        'street'    => 'rue',
        'npa'       => 'npa',
        'city'      => 'localite',
    ];

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/cabane-metadata/{id}', name: 'apmbs.api.cabane_metadata')]
    public function cabaneMetadataAction(Request $request, Cabane $cabane) {
        return $this->json([
            'nom'                => $cabane->getNom(),
            'availabilityRules'  => $cabane->getAvailabilityRule(),
            'prices'             => $cabane->getPrices(),
            'disabledDates'      => $cabane->getDisabledDates(),
            'conditions'         => $cabane->getConditionsList(),
            'timePeriods'        => array_map(function(CabaneTimePeriod $timePeriod) {
                return [
                    'name'       => $timePeriod->getNom(),
                    'value'      => $timePeriod->getId(),
                    'hourStart'  => $timePeriod->getTimeStart()->format('H'),
                    'hourEnd'    => $timePeriod->getTimeEnd()->format('H'),
                    'minuteStart'=> $timePeriod->getTimeStart()->format('i'),
                    'minuteEnd'  => $timePeriod->getTimeEnd()->format('i'),
                ];
            }, $cabane->getTimePeriods())
        ]);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/cabane-reservation/{id}', name: 'apmbs.api.cabane_reservation', methods: ['POST'])]
    public function reservationAction(Request $request, EntityManagerInterface $em, GoogleCalendarManager $gcm, ValidatorInterface $validator, LoggerInterface $logger, Cabane $cabane) {
        $parameters = json_decode($request->getContent(), true);
        if (!is_array($parameters)) {
            return $this->badRequest('Invalid JSON body');
        }

        foreach (array_merge(['start', 'end'], array_keys(self::RESERVATION_FIELDS)) as $field) {
            if (!isset($parameters[$field]) || !is_scalar($parameters[$field])) {
                return $this->badRequest("Missing or invalid field '$field'");
            }
        }

        $description = $parameters['description'] ?? null;
        if ($description !== null && !is_scalar($description)) {
            return $this->badRequest("Invalid field 'description'");
        }

        try {
            $start = new \DateTime((string) $parameters['start']);
            $end = new \DateTime((string) $parameters['end']);
        } catch (\Exception $e) {
            return $this->badRequest('Invalid start or end date');
        }

        if ($start >= $end) {
            return $this->badRequest('Start date must be before end date');
        }

        $reservation = new APMBSReservation();
        $reservation->setCabane($cabane);
        $reservation->setStatus(APMBSReservation::PENDING);
        $reservation->setStart($start);
        $reservation->setEnd($end);
        $reservation->setPrenom(trim((string) $parameters['firstname']));
        $reservation->setNom(trim((string) $parameters['lastname']));
        $reservation->setEmail(trim((string) $parameters['email']));
        $reservation->setPhone(trim((string) $parameters['phone']));
        $reservation->setUnite(trim((string) $parameters['unit']));
        $reservation->setRue(trim((string) $parameters['street']));
        $reservation->setNpa(trim((string) $parameters['npa']));
        $reservation->setLocalite(trim((string) $parameters['city']));
        $reservation->setDescription($description !== null ? (string) $description : null);

        $errors = $validator->validate($reservation);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $error) {
                $path = $error->getPropertyPath();
                $violations[] = [
                    'field'   => array_search($path, self::RESERVATION_FIELDS, true) ?: $path,
                    'message' => $error->getMessage(),
                ];
            }

            return $this->badRequest('Invalid reservation', $violations);
        }

        $em->persist($reservation);
        $em->flush();

        // Update google calendar
        // We currently do NOT write pending reservations to GC
        // $gcm->updateReservation($reservation);

        // The reservation is saved at this point: a mailer failure must not turn into an error
        // response, otherwise the client retries and creates duplicate reservations.
        try {
            $gcm->sendEmailToClient($reservation, 'Votre demande de réservation');
        } catch (\Throwable $e) {
            $logger->error('APMBS: failed to send pending reservation email', [
                'reservation' => $reservation->getId(),
                'exception'   => $e,
            ]);
        }

        return new Response('ok');
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/cabane-monthly-events/{id}', name: 'apmbs.api.cabane_calendar')]
    public function monthlyEventsAction(Request $request, Cabane $cabane, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        $rawStart = $request->get('start');
        $rawEnd = $request->get('end');
        if (!is_string($rawStart) || !is_string($rawEnd) || $rawStart === '' || $rawEnd === '') {
            return $this->badRequest("Parameters 'start' and 'end' are required");
        }

        try {
            $start = new \DateTimeImmutable($rawStart);
            $end = new \DateTimeImmutable($rawEnd);
        } catch (\Exception $e) {
            return $this->badRequest('Invalid start or end date');
        }

        if ($start >= $end) {
            return $this->badRequest('Start date must be before end date');
        }

        // Reservations not in Google Calendar yet. A reservation whose new dates await the client's answer
        // was removed from Google Calendar, but its dates must stay blocked until the client answers.
        $pendingReservations = $em->createQueryBuilder()->select('r')
            ->from(APMBSReservation::class, 'r')
            ->where('r.cabane = :cabane')
            ->andWhere('r.start <= :end')
            ->andWhere('r.end >= :start')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('cabane', $cabane)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('statuses', [APMBSReservation::PENDING, APMBSReservation::MODIFICATION_PENDING])
            ->getQuery()
            ->execute();

        $gcmReservations = $gcm->listReservations($cabane, $start, $end);

        return $this->json(array_merge(
            $gcm->googleEventToJSON($gcmReservations),
            array_map(function(APMBSReservation $reservation) {
                // The public calendar only distinguishes pending from accepted: keep its status contract
                return array_merge($reservation->toJSON(), ['status' => APMBSReservation::PENDING]);
            }, $pendingReservations)
        ));
    }

    #[Route('/client/accept/{id}/{token}', name: 'apmbs.api.client.accept', methods: ['GET', 'POST'])]
    public function clientAcceptReservation(Request $request, APMBSReservation $reservation, string $token, EntityManagerInterface $em, GoogleCalendarManager $gcm, APMBSClientLinks $links, CacheInterface $cache, LoggerInterface $logger) {
        if (!$links->isValid($reservation, $token)) {
            return $this->handleInvalidClientLink($reservation, $token, $em, $gcm, $cache, $logger);
        }

        // Link opened again after accepting: show the same confirmation instead of an error
        if ($reservation->getStatus() === APMBSReservation::MODIFICATION_ACCEPTED) {
            return $this->render('reservation/client_validate.html.twig', [
                'reservation' => $reservation,
            ]);
        }

        if ($response = $this->clientConfirmationStep($request, $reservation, $token, 'accept')) {
            return $response;
        }

        // Update reservation status
        $reservation->setStatus(APMBSReservation::MODIFICATION_ACCEPTED);
        $log = new ReservationLog();
        $log->setUsername($reservation->getEmail());
        $log->setReservation($reservation);
        $log->setPayload(['Modification acceptee par le demandeur']);
        $log->setAction(ReservationLog::MODIFICATION_ACCEPTED);
        $gcm->updateReservation($reservation);
        $em->persist($log);
        $em->flush();

        return $this->render('reservation/client_validate.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/client/reject/{id}/{token}', name: 'apmbs.api.client.reject', methods: ['GET', 'POST'])]
    public function clientRejectReservation(Request $request, APMBSReservation $reservation, string $token, EntityManagerInterface $em, GoogleCalendarManager $gcm, APMBSClientLinks $links, CacheInterface $cache, LoggerInterface $logger) {
        if (!$links->isValid($reservation, $token)) {
            return $this->handleInvalidClientLink($reservation, $token, $em, $gcm, $cache, $logger);
        }

        if ($response = $this->clientConfirmationStep($request, $reservation, $token, 'reject')) {
            return $response;
        }

        // Update reservation status
        $reservation->setStatus(APMBSReservation::CANCELLED);
        $log = new ReservationLog();
        $log->setUsername($reservation->getEmail());
        $log->setReservation($reservation);
        $log->setPayload(['Modification refusee, reservation annulee par le demandeur']);
        $log->setAction(ReservationLog::CANCELLED);
        $gcm->deleteReservation($reservation);
        $em->persist($log);
        $em->flush();

        // Cancellation is already saved, don't show an error page if only the email fails
        try {
            $gcm->sendEmailToClient($reservation, 'Vous avez annulé votre demande de réservation');
        } catch (\Throwable $e) {
            $logger->error('APMBS: failed to send client cancellation email', [
                'reservation' => $reservation->getId(),
                'exception'   => $e,
            ]);
        }

        return $this->render('reservation/client_reject.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * Shared step of the client accept/reject links. Returns the page to show, or null when the client
     * confirmed (POST) a reservation that still awaits their answer.
     */
    private function clientConfirmationStep(Request $request, APMBSReservation $reservation, string $token, string $action): ?Response {
        if ($reservation->getStatus() !== APMBSReservation::MODIFICATION_PENDING) {
            return $this->clientMessage(
                'Demande déjà traitée',
                "Cette réservation n'est plus en attente de votre réponse.",
                $reservation
            );
        }

        // Email security scanners (e.g. Outlook Safe Links) open links on their own, so a GET only shows
        // this confirmation page and the change needs the button's POST. No CSRF token is needed: the
        // signed URL is itself the secret.
        if (!$request->isMethod('POST')) {
            return $this->render('reservation/client_confirm.html.twig', [
                'reservation' => $reservation,
                'action'      => $action,
                'token'       => $token,
            ]);
        }

        return null;
    }

    /**
     * Links sent before signed links existed carried the client's email instead of a token. While such a
     * reservation still awaits the client's answer, opening the old link emails them fresh signed links
     * (at most once per hour). This branch can be removed once no reservation modified before signed links
     * were introduced is still MODIFICATION_PENDING.
     */
    private function handleInvalidClientLink(APMBSReservation $reservation, string $token, EntityManagerInterface $em, GoogleCalendarManager $gcm, CacheInterface $cache, LoggerInterface $logger): Response {
        if ($token !== $reservation->getEmail()) {
            return $this->clientMessage(
                'Lien invalide',
                "Ce lien n'est pas ou plus valide. Si vous avez reçu plusieurs e-mails pour cette réservation, utilisez le lien du plus récent.",
                null,
                Response::HTTP_FORBIDDEN
            );
        }

        if ($reservation->getStatus() !== APMBSReservation::MODIFICATION_PENDING) {
            return $this->clientMessage(
                'Demande déjà traitée',
                "Cette réservation n'est plus en attente de votre réponse.",
                $reservation
            );
        }

        try {
            $cache->get('apmbs_client_links_resent_' . $reservation->getId(), function (ItemInterface $item) use ($reservation, $em, $gcm) {
                $item->expiresAfter(3600);
                $this->resendModificationEmail($reservation, $em, $gcm);
                return true;
            });
        } catch (\Throwable $e) {
            $logger->error('APMBS: failed to resend modification email with signed links', [
                'reservation' => $reservation->getId(),
                'exception'   => $e,
            ]);

            return $this->clientMessage(
                'Lien expiré',
                "Ce lien n'est plus valide et nous n'avons pas pu vous envoyer de nouveau lien. Merci de contacter l'APMBS.",
                $reservation,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->clientMessage(
            'Nouveau lien envoyé',
            "Pour des raisons de sécurité, ce lien n'est plus valide. Nous vous avons envoyé un nouvel e-mail contenant des liens à jour pour accepter ou refuser la modification.",
            $reservation
        );
    }

    private function resendModificationEmail(APMBSReservation $reservation, EntityManagerInterface $em, GoogleCalendarManager $gcm): void {
        // Rebuild the original email from the log written when the intendant proposed the modification
        $log = $em->getRepository(ReservationLog::class)->findOneBy(
            ['reservation' => $reservation, 'action' => ReservationLog::MODIFY],
            ['id' => 'DESC']
        );
        $payload = $log ? json_decode((string) $log->getPayload(), true) : null;
        $payload = is_array($payload) ? $payload : [];

        $parseDate = function ($value, \DateTime $fallback): \DateTime {
            $date = is_string($value) ? \DateTime::createFromFormat('d.m.Y H:i', $value) : false;
            return $date ?: $fallback;
        };

        $gcm->sendEmailToClient($reservation, 'Demande de réservation en attente', $payload['message'] ?? null, 'modification', [
            'oldStart' => $parseDate($payload['oldStart'] ?? null, $reservation->getStart()),
            'oldEnd'   => $parseDate($payload['oldEnd'] ?? null, $reservation->getEnd()),
        ]);
    }

    private function clientMessage(string $title, string $text, ?APMBSReservation $reservation = null, int $status = Response::HTTP_OK): Response {
        return $this->render('reservation/client_message.html.twig', [
            'title'       => $title,
            'text'        => $text,
            'reservation' => $reservation,
        ], new Response('', $status));
    }

    private function badRequest(string $message, array $violations = []): JsonResponse {
        $payload = ['error' => $message];
        if ($violations) {
            $payload['violations'] = $violations;
        }

        return $this->json($payload, Response::HTTP_BAD_REQUEST);
    }
}
