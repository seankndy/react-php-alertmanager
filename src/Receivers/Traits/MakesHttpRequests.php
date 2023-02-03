<?php

namespace SeanKndy\AlertManager\Receivers\Traits;

use React\EventLoop\LoopInterface;
use React\HttpClient\Client;
use React\HttpClient\Response;
use React\Promise\PromiseInterface;

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

        $client = new Client($loop);

        $headers = array_merge([
            'Content-Length' => strlen($payload)
        ], $headers);

        $request = $client->request('POST', $url, $headers);
        $request->on('response', function (Response $response) use ($url, $deferred) {
            if (substr($response->getCode(), 0, 1) != '2') {
                $deferred->reject(
                    new \Exception(
                        "Non-2xx response code from $url: " .
                        $response->getCode()
                    )
                );
                $response->close();
                return;
            }
            $respBody = '';
            $response->on('data', function ($chunk) use (&$respBody) {
                $respBody .= $chunk;
            });
            $response->on('end', function() use (&$respBody, $response, $deferred) {
                $headers = \array_change_key_case($response->getHeaders(), CASE_LOWER);
                if (isset($headers['content-type']) && strstr($headers['content-type'], 'application/json')) {
                    $respBody = \json_decode(\trim($respBody));
                }
                $deferred->resolve($respBody);
            });
        });
        $request->on('error', function (\Throwable $e) use ($deferred) {
            $deferred->reject($e);
        });
        $request->end($payload);

        return $deferred->promise();
    }
}