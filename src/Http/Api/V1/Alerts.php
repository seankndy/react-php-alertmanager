<?php

namespace SeanKndy\AlertManager\Http\Api\V1;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\Processor;
use SeanKndy\AlertManager\Http\Api\ApiInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response as HttpResponse;

class Alerts implements ApiInterface
{
    protected Processor $processor;

    public function __construct(Processor $processor)
    {
        $this->processor = $processor;
    }

    public function routes(\FastRoute\RouteCollector $routeCollector): void
    {
        $routeCollector->addRoute('GET', '/alerts', [$this, 'get']);
        $routeCollector->addRoute('POST', '/alerts', [$this, 'create']);
        $routeCollector->addRoute('POST', '/alerts/quiesce', [$this, 'quiesce']);
    }

    /**
     * Get queued alerts
     */
    public function get(ServerRequestInterface $request): HttpResponse
    {
        $queryParams = $request->getQueryParams();
        $receiverId = $queryParams['receiverId'] ?? null;
        $state = $queryParams['state'] ?? null;

        $alertArray = [];
        foreach ($this->processor as $alert) {
            // filter by state
            if ($state && $state != $alert->getState()) {
                continue;
            }
            if ($receiverId !== null) {
                // filter by whether receiver with id $receiverId has been
                // dispatched to.
                foreach ($alert->getDispatchLog() as $receiver) {
                    if ($receiver->receiverId() == $receiverId) {
                        $alertArray[] = $alert->toArray();
                        break;
                    }
                }
            } else {
                $alertArray[] = $alert->toArray();
            }
        }
        return new HttpResponse(
            200,
            ['Content-Type' => 'application/json'],
            \json_encode([
                'status' => 'success',
                'alerts' => $alertArray
            ])
        );
    }

    /**
     * Create and queue alert(s)
     *
     * @return HttpResponse|PromiseInterface<HttpResponse>
     */
    public function create(ServerRequestInterface $request)
    {
        // build Alerts from request body

        try {
            $alerts = Alert::fromJSON((string)$request->getBody());
        } catch (\Throwable $e) {
            return new HttpResponse(
                400,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'error'])
            );
        }

        // queue alerts
        $promises = [];
        foreach ($alerts as $alert) {
            $promises[] = $this->processor->add($alert);
        }

        return \React\Promise\all($promises)->then(function() {
            return new HttpResponse(
                201,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'success'])
            );
        }, function($e) {
            return new HttpResponse(
                500,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'error'])
            );
        });
    }

    /**
     * Quiet alert routing
     */
    public function quiesce(ServerRequestInterface $request): HttpResponse
    {
        $body = (string)$request->getBody();
        $parsedBody = \json_decode($body);
        $duration = $parsedBody->duration ?? null;
        if (!$duration) {
            return new HttpResponse(
                400,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'error'])
            );
        }

        if ($this->processor->quiesce($duration)) {
            return new HttpResponse(
                200,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'success'])
            );
        } else {
            return new HttpResponse(
                429,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'error'])
            );
        }
    }
}
