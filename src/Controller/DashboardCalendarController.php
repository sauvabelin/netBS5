<?php

namespace App\Controller;

use App\Service\GoogleCalendarManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DashboardCalendarController extends AbstractController
{
    private const CALENDARS = [
        ['id' => '2nfd0g1ej2s09gkd1ueve0arfk@group.calendar.google.com', 'color' => '#D81B60'],
        ['id' => 'tjbbst9e15hg28n4rcg8pl99nk@group.calendar.google.com', 'color' => '#33B679'],
        ['id' => '5760d9cb4dd28bef5f7addcc721ba1a621254bfd8b148f847ec39de37358005c@group.calendar.google.com', 'color' => '#3788d8'],
    ];

    private const CACHE_TTL_SECONDS = 3600;

    #[Route('/dashboard/calendar-events', name: 'sauvabelin.dashboard.calendar_events')]
    public function calendarEventsAction(Request $request, GoogleCalendarManager $gcm, CacheInterface $cache): JsonResponse
    {
        $start = new \DateTimeImmutable($request->get('start', 'first day of this month'));
        $end   = new \DateTimeImmutable($request->get('end', 'last day of next month'));

        $events = $cache->get(
            $this->cacheKeyFor($start, $end),
            function (ItemInterface $item) use ($gcm, $start, $end) {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);
                return $this->fetchAllCalendarEvents($gcm, $start, $end);
            }
        );

        return new JsonResponse($events);
    }

    private function cacheKeyFor(\DateTimeInterface $start, \DateTimeInterface $end): string
    {
        return 'dashboard_calendar_' . sha1($start->format('c') . '|' . $end->format('c'));
    }

    private function fetchAllCalendarEvents(GoogleCalendarManager $gcm, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $calendarIds = array_column(self::CALENDARS, 'id');
        $byCalendar  = $gcm->listCalendarEventsBatch($calendarIds, $start, $end);

        $merged = [];
        foreach (self::CALENDARS as $cal) {
            foreach ($byCalendar[$cal['id']] ?? [] as $event) {
                $event['backgroundColor'] = $cal['color'];
                $event['borderColor']     = $cal['color'];
                $merged[] = $event;
            }
        }
        return $merged;
    }
}
