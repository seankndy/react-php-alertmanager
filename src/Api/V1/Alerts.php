<?php
namespace SeanKndy\AlertManager\Api\V1;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Server;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response as HttpResponse;

class Alerts
{
    /**
     * @var Server
     */
    protected $server;
    /**
     * @var int
     */
    private $defaultExpiryDuration = 600; // 10min


    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function setDefaultExpiryDuration(int $duration)
    {
        $this->defaultExpiryDuration = $duration;

        return $this;
    }

    /**
     * Get queued alerts
     *
     * @param ServerRequestInterface $request
     *
     * @return HttpResponse
     */
    public function get(ServerRequestInterface $request)
    {
        $queryParams = $request->getQueryParams();
        $receiverId = $queryParams['receiverId'] ?? null;
        $state = $queryParams['state'] ?? null;

        $alertArray = [];
        foreach ($this->server->getQueuedAlerts() as $alert) {
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
     * @param ServerRequestInterface $request
     *
     * @return HttpResponse|PromiseInterface<HttpResponse>
     */
    public function create(ServerRequestInterface $request)
    {
        // build Alerts from request body

        try {
            $alerts = Alert::fromJSON(
                (string)$request->getBody(),
                $this->defaultExpiryDuration
            );
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
            $promises[] = $this->server->queueAlert($alert);
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
     *
     * @param ServerRequestInterface $request
     *
     * @return HttpResponse
     */
    public function quiesce(ServerRequestInterface $request)
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

        if ($this->server->startQuiesce($duration)) {
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
