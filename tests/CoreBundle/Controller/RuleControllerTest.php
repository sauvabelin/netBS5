<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Controller;

use App\Tests\Support\UserFactoryTrait;
use NetBS\CoreBundle\Rule\RuleDescriber;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RuleControllerTest extends WebTestCase
{
    use UserFactoryTrait;

    private function post($client, array $body): void
    {
        $url = $client->getContainer()->get('router')->generate('netbs.core.rule.describe');
        $client->request('POST', $url, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    public function test_describe(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistUser($client, 'rule-describe-user', roles: ['ROLE_USER']), 'netbs');

        $rule = [
            'mode' => 'blocks', 'op' => 'and',
            'items' => [['block' => 'test.n', 'not' => false, 'params' => ['op' => 'ge', 'value' => 3]]],
        ];
        $this->post($client, ['context' => 'test', 'rule' => $rule]);

        $this->assertResponseIsSuccessful();
        $expected = static::getContainer()->get(RuleDescriber::class)->describe(json_encode($rule), 'test');
        $this->assertSame(['sentence' => $expected, 'errors' => []], json_decode($client->getResponse()->getContent(), true));
    }

    public function test_describe_reports_errors(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistUser($client, 'rule-describe-user', roles: ['ROLE_USER']), 'netbs');

        $this->post($client, ['context' => 'test', 'rule' => ['mode' => 'blocks', 'op' => 'and', 'items' => [['block' => 'nope', 'params' => []]]]]);

        $errors = json_decode($client->getResponse()->getContent(), true)['errors'];
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('nope', $errors[0]);
    }

    public function test_unknown_context_is_400(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistUser($client, 'rule-describe-user', roles: ['ROLE_USER']), 'netbs');

        $this->post($client, ['context' => 'nope', 'rule' => null]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_anonymous_is_redirected(): void
    {
        $client = static::createClient();
        $this->post($client, ['context' => 'test', 'rule' => null]);

        $this->assertResponseRedirects();
    }
}
