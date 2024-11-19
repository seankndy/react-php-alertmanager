<?php

namespace SeanKndy\AlertManager\Receivers\Traits;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Http\Browser;

trait MakesHttpRequests
{
    /**
     * Make async HTTP POST to $url with $payload as payload
     */
    private function asyncHttpPost(
        LoopInterface $loop,
        string $url,
        string $payload,
        array $headers = []
    ): PromiseInterface {
        $deferred = new \React\Promise\Deferred();

        $browser = new Browser(
            new \React\Socket\Connector([
                'timeout' => 10,
                'tls' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]),
            $loop
        );

        $headers = array_merge([
            'Content-Length' => strlen($payload)
        ], $headers);

        $browser
            ->request('POST', $url, $headers, $payload)
            ->then(function (\Psr\Http\Message\ResponseInterface $response) use ($url, $deferred) {
                if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                    $deferred->reject(
                        new \Exception("Non-2xx response code from $url: " . $response->getStatusCode())
                    );

                    return;
                }

                $respBody = $response->getBody()->getContents();

                $headers = \array_change_key_case($response->getHeaders(), CASE_LOWER);
                if (isset($headers['content-type']) && in_array('application/json', $headers['content-type'])) {
                    $respBody = \json_decode(\trim($respBody));
                }

                $deferred->resolve($respBody);
            }, function (\Exception $e) use ($deferred) {
                $deferred->reject($e);
            });

        return $deferred->promise();
    }
}