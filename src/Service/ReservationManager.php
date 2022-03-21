<?php

namespace App\Service;

use App\Entity\APMBSReservation;

class ReservationManager {

    public function updateReservation(APMBSReservation $reservation) {

        // If google calendar event, update, otherwise insert new event in calendar
        if ($reservation->getGCEventId()) {

        } else {
            // Insert
        }
    }
}