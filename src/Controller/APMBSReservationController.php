<?php

namespace App\Controller;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class APMBSReservationController extends AbstractController
{
    /**
     * @param Request $request
     * @Route("/apmbs/reservations/dashboard", name="sauvabelin.apmbs_reservations.dashboard")
     * @return Response
     */
    public function apmbsReservations(Request $request, EntityManagerInterface $em) {
        return $this->render('reservation/dashboad.html.twig', [
            'cabanes' => $em->getRepository('App:Cabane')->findAll(),
        ]);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/reservations/pending-and-refused/{id}", name="sauvabelin.apmbs_reservations.get_pending_and_refused")
     * @return Response
     */
    public function getPendingAndRefusedReservations(Request $request, Cabane $cabane, EntityManagerInterface $em) {
        $startDate = \DateTime::createFromFormat(\DateTime::ISO8601, $request->request->get('start'));
        $endDate = \DateTime::createFromFormat(\DateTime::ISO8601, $request->request->get('end'));

        $query = $em->getRepository('App:APMBSReservation')->createQueryBuilder('r');
        $reservations = $query->where('r.cabane = :c')
            ->setParameter('c', $cabane)
            ->andWhere($query->expr()->orX('r.status = :pending', 'r.status = :refused'))
            ->setParameter('pending', APMBSReservation::PENDING)
            ->setParameter('refused', APMBSReservation::REFUSED)
            ->andWhere($query->expr()->orX(
                'r.start <= :end',
                'r.end >= :start'
            ))
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        $results = $reservations->getQuery()->getResult();
        $response = [];
        /** @var APMBSReservation $event */
        foreach ($results as $event) {
            $response[] = [
                'id' => $event->getId(),
                'start' => $event->getStart()->format(\DateTime::ISO8601),
                'end' => $event->getEnd()->format(\DateTime::ISO8601),
                'title' => $event->getUnite() . " [" . $event->getStatus() . "]",
            ];
        }

        return new JsonResponse($response);
    }

    public function editReservationModal(Request $request, APMBSReservation $reservation) {

    }
}


