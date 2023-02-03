<?php

namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Receivers\Traits\MakesHttpRequests;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class MicrosoftTeams extends AbstractReceiver
{
    use MakesHttpRequests;

    protected LoopInterface $loop;

    /**
     * The MS Teams Channel Webhook URL
     */
    protected string $webhookUrl;

    public function __construct(
        $id,
        LoopInterface $loop,
        string $webhookUrl
    ) {
        parent::__construct($id);

        $this->loop = $loop;
        $this->webhookUrl = $webhookUrl;
    }

    public function receive(Alert $alert): PromiseInterface
    {
        if (!$this->alertTemplate) {
            return \React\Promise\resolve([]);
        }

        return $this->asyncHttpPost(
            $this->loop,
            $this->webhookUrl,
            json_encode([
                '@type' => "Message Card",
                '@context' => "http://schema.org/extensions",
                'summary' => $this->alertTemplate->brief($alert),
                'themeColor' => 'fbac18',
                'title' => '',
                'text' => $this->alertTemplate->detail($alert),
            ]),
            ['Content-Type' => 'application/json']
        );
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function __toString(): string
    {
        return parent::__toString() . '; ' .
            'ms-teams-webhook-url-' . $this->webhookUrl;
    }
}