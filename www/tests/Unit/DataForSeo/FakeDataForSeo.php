<?php

namespace Tests\Unit\DataForSeo;

use App\Services\DataForSeo\DataForSeoClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * DataForSEO servis testleri icin sahte Client ve zarf (envelope) yardimcilari.
 */
trait FakeDataForSeo
{
    /** @var list<\Psr\Http\Message\RequestInterface> */
    protected array $requests = [];

    /**
     * @param  list<Response>  $responses
     */
    protected function fakeClient(array $responses, ?string $login = 'login', ?string $password = 'password'): DataForSeoClient
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::tap(function ($request) {
            $this->requests[] = $request;
        }));

        return new DataForSeoClient(new Client(['handler' => $stack]), $login, $password);
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    protected function taskResponse(?array $result, int $taskStatus = 20000, string $taskMessage = 'Ok.'): Response
    {
        return new Response(200, [], json_encode([
            'status_code' => 20000,
            'status_message' => 'Ok.',
            'tasks' => [[
                'id' => 'task-1',
                'status_code' => $taskStatus,
                'status_message' => $taskMessage,
                'cost' => 0.01,
                'result' => $result === null ? null : [$result],
            ]],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestBody(int $index = 0): array
    {
        return json_decode((string) $this->requests[$index]->getBody(), true);
    }
}
