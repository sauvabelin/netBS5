<?php

namespace App\ApiController;

use App\Entity\APMBSReservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiReservationController extends AbstractController
{
    /**
     * @param Request $request
     * @return Response
     * @Route("/api/v1/public/netBS/bs/apmbs/reservation", name="sauvabelin.apmbs.reservation_endpoint")
     */
    public function publicAccessAction(Request $request, EntityManagerInterface $em) {

        $data = json_decode($request->getContent(), true);
        $prenom = $data['prenom'];
        $nom = $data['nom'];
        $email = $data['email'];
        $telephone = $data['telephone'];
        $groupe = $data['groupe'];
        $activite = $data['activite'];
        $start = \DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['start']);
        $end = \DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['end']);
        $cabaneId = $data['cabane'];

        $cabane = $em->getRepository('App:Cabane')->find($cabaneId);
        if (!$cabane) {
            throw new \Exception("Unknown cabane");
        }

        $reservation = new APMBSReservation();
        $reservation->setStatus(APMBSReservation::PENDING);
        $reservation->setUnite($groupe);
        $reservation->setPhone($telephone);
        $reservation->setNom($nom);
        $reservation->setPrenom($prenom);
        $reservation->setStart($start);
        $reservation->setEnd($end);
        $reservation->setEmail($email);
        $reservation->setDescription($activite);

        $em->persist($reservation);
        $em->flush();

        return new Response();
    }
}


