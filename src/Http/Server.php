<?php

namespace SeanKndy\AlertManager\Http;

use Evenement\EventEmitter;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response as HttpResponse;
use React\Http\Server as ReactHttpServer;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;
use SeanKndy\AlertManager\Alerts\Processor;
use SeanKndy\AlertManager\Auth\AuthorizerInterface;
use SeanKndy\AlertManager\Http\Api;
use SeanKndy\AlertManager\Routing\Router;

class Server extends EventEmitter
{
    private LoopInterface $loop;

    private ReactHttpServer $http;

    private \FastRoute\Dispatcher $routeDispatcher;

    /**
     * Used to verify user access to API
     */
    private ?AuthorizerInterface $authorizer = null;

    public function __construct(
        LoopInterface $loop,
        string $listen,
        AuthorizerInterface $authorizer,
        Processor $alertProcessor
    ) {
        $this->loop = $loop;
        $this->authorizer = $authorizer;

        $apis = [
            new Api\V1\Alerts($alertProcessor)
        ];

        $this->http = new ReactHttpServer(fn(ServerRequestInterface $request) => $this->handleRequest($request));
        $this->http->on('error', fn($e) => $this->emit('error', [$e]));
        $socket = new SocketServer($listen, [], $this->loop);
        $this->http->listen($socket);

        $this->routeDispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) use ($apis) {
            $r->addGroup('/api/v1', function (\FastRoute\RouteCollector $r) use ($apis) {
                foreach ($apis as $api) {
                    $api->defineRoutes($r);
                }
            });
        });
    }

    /**
     * @return HttpResponse|PromiseInterface<HttpResponse>
     */
    private function handleRequest(ServerRequestInterface $request)
    {
        $routeInfo = $this->routeDispatcher->dispatch(
            $request->getMethod(), $request->getUri()->getPath()
        );

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                return new HttpResponse(
                    404,
                    ['Content-Type' => 'application/json'],
                    \json_encode(['status' => 'error'])
                );
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return new HttpResponse(
                    405,
                    ['Content-Type' => 'application/json'],
                    \json_encode(['status' => 'error'])
                );
            case \FastRoute\Dispatcher::FOUND:
                $authPromise = $this->authorizer
                    ? $this->authorizer->authorize($request)
                    : \React\Promise\resolve(true);

                return $authPromise->then(function (bool $authenticated) use ($request, $routeInfo) {
                    if (!$authenticated) {
                        return new HttpResponse(
                            401,
                            ['Content-Type' => 'application/json'],
                            \json_encode(['status' => 'error'])
                        );
                    }
                    $vars = \array_merge([$request], $routeInfo[2]);
                    $result = \call_user_func_array($routeInfo[1], $vars);

                    if ($result instanceof PromiseInterface) {
                        return $result->then(function($response) {
                            return $response;
                        }, function ($e) {
                            return new HttpResponse(
                                500,
                                ['Content-Type' => 'application/json'],
                                \json_encode(['status' => 'error'])
                            );
                        });
                    } else if ($result instanceof HttpResponse) {
                        return $result;
                    } else {
                        return new HttpResponse(
                            500,
                            ['Content-Type' => 'application/json'],
                            \json_encode(['status' => 'error'])
                        );
                    }
                }, function (\Exception $e) { // error during authorization
                    return new HttpResponse(
                        500,
                        ['Content-Type' => 'application/json'],
                        \json_encode(['status' => 'error'])
                    );
                });
        }
    }

    public function setAuthorizer(AuthorizerInterface $authorizer): self
    {
        $this->authorizer = $authorizer;

        return $this;
    }
}