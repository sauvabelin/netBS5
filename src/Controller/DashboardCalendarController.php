<?php

namespace App\Controller;

use App\Service\GoogleCalendarManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DashboardCalendarController extends AbstractController
{
    private const CALENDARS = [
        ['id' => '2nfd0g1ej2s09gkd1ueve0arfk@group.calendar.google.com', 'color' => '#D81B60'],
        ['id' => 'tjbbst9e15hg28n4rcg8pl99nk@group.calendar.google.com', 'color' => '#33B679'],
        ['id' => '5760d9cb4dd28bef5f7addcc721ba1a621254bfd8b148f847ec39de37358005c@group.calendar.google.com', 'color' => '#3788d8'],
    ];

    #[Route('/dashboard/calendar-events', name: 'sauvabelin.dashboard.calendar_events')]
    public function calendarEventsAction(Request $request, GoogleCalendarManager $gcm): JsonResponse
    {
        $start = new \DateTimeImmutable($request->get('start', 'first day of this month'));
        $end = new \DateTimeImmutable($request->get('end', 'last day of next month'));

        $allEvents = [];
        foreach (self::CALENDARS as $cal) {
            try {
                $events = $gcm->listCalendarEvents($cal['id'], $start, $end);
                foreach ($events as &$event) {
                    $event['backgroundColor'] = $cal['color'];
                    $event['borderColor'] = $cal['color'];
                }
                $allEvents = array_merge($allEvents, $events);
            } catch (\Exception $e) {
                // Skip calendar if unavailable
            }
        }

        return new JsonResponse($allEvents);
    }
}
