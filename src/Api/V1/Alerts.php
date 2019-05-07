<?php
namespace SeanKndy\AlertManager\Api\V1;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\Queue;
use Psr\Http\Message\ServerRequestInterface;
use Evenement\EventEmitter;
use React\Http\Response as HttpResponse;

class Alerts
{
    /**
     * @var Queue
     */
    protected $queue;
    /**
     * @var EventEmitter
     */
    protected $eventEmitter = null;
    /**
     * @var int
     */
    private $defaultExpiryDuration = 600; // 10min


    public function __construct(Queue $queue, EventEmitter $eventEmitter = null)
    {
        $this->queue = $queue;
        $this->eventEmitter = $eventEmitter;
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
        $receiverId = $request->getAttribute('receiverId');

        $alertArray = [];
        foreach ($this->queue as $alert) {
            if ($receiverId !== null) {
                foreach ($alert->getDispatchLog() as $receiver) {
                    if ($receiver->getId() == $receiverId) {
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
     * @return HttpResponse
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
        foreach ($alerts as $alert) {
            if ($this->eventEmitter)
                $this->eventEmitter->emit('alert', [$alert]);
            $this->queue->enqueue($alert);
        }

        // return positivity
        return new HttpResponse(
            201,
            ['Content-Type' => 'application/json'],
            \json_encode(['status' => 'success'])
        );
    }
}
