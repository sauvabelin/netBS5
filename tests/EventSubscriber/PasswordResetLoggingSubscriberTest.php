<?php

namespace App\Tests\EventSubscriber;

use App\Entity\BSUser;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use NetBS\SecureBundle\Event\PasswordResetCompletedEvent;
use NetBS\SecureBundle\Event\PasswordResetRequestedEvent;
use NetBS\SecureBundle\EventSubscriber\PasswordResetLoggingSubscriber;
use PHPUnit\Framework\TestCase;

class PasswordResetLoggingSubscriberTest extends TestCase
{
    public function test_logs_requested_event_at_info_level(): void
    {
        $handler = new TestHandler();
        $subscriber = new PasswordResetLoggingSubscriber(new Logger('test', [$handler]));

        $subscriber->onRequested(new PasswordResetRequestedEvent($this->makeUser(42, 'alice'), '198.51.100.7'));

        $this->assertTrue($handler->hasInfoRecords());
        $this->assertSame(
            'password_reset.requested user_id=42 username=alice ip=198.51.100.7',
            $handler->getRecords()[0]['message']
        );
    }

    public function test_logs_completed_event_with_null_ip_as_dash(): void
    {
        $handler = new TestHandler();
        $subscriber = new PasswordResetLoggingSubscriber(new Logger('test', [$handler]));

        $subscriber->onCompleted(new PasswordResetCompletedEvent($this->makeUser(7, 'bob'), null));

        $this->assertSame(
            'password_reset.completed user_id=7 username=bob ip=-',
            $handler->getRecords()[0]['message']
        );
    }

    public function test_subscribes_to_both_events(): void
    {
        $events = PasswordResetLoggingSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(PasswordResetRequestedEvent::class, $events);
        $this->assertArrayHasKey(PasswordResetCompletedEvent::class, $events);
    }

    private function makeUser(int $id, string $username): BSUser
    {
        $user = new BSUser();
        $user->setUsername($username);
        $ref = new \ReflectionProperty($user, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);
        return $user;
    }
}
