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

    /**
     * Icon image to use in Adaptive Card messages.
     */
    protected ?string $messageImageUrl;

    /**
     * Title text to use in Adaptive Card messages.
     */
    protected ?string $messageTitle;

    public function __construct(
        $id,
        LoopInterface $loop,
        string $webhookUrl,
        ?string $messageImageUrl = null,
        ?string $messageTitle = null
    ) {
        parent::__construct($id);

        $this->loop = $loop;
        $this->webhookUrl = $webhookUrl;
        $this->messageImageUrl = $messageImageUrl;
        $this->messageTitle = $messageTitle;
    }

    public function receive(Alert $alert): PromiseInterface
    {
        if (!$this->alertTemplate) {
            return \React\Promise\resolve([]);
        }

        return $this->asyncHttpPost(
            $this->loop,
            $this->webhookUrl,
            $this->makeMessagePayload($alert),
            ['Content-Type' => 'application/json']
        );
    }

    protected function makeMessagePayload(Alert $alert): string
    {
        return $this->webhookUrlIsDeprecatedVersion()
            ? $this->makeMessageCardPayload($alert)
            : $this->makeAdaptiveCardPayload($alert);
    }

    protected function makeMessageCardPayload(Alert $alert): string
    {
        return json_encode([
            '@type' => "Message Card",
            '@context' => "http://schema.org/extensions",
            'summary' => $this->alertTemplate->brief($alert),
            'themeColor' => 'fbac18',
            'title' => '',
            'text' => $this->alertTemplate->detail($alert),
        ]);
    }

    protected function makeAdaptiveCardPayload(Alert $alert): string
    {
        $bodyItems = [];

        if ($this->messageImageUrl || $this->messageTitle) {
            $columns = [];

            if ($this->messageImageUrl) {
                $columns[] = [
                    'type' => 'Column',
                    'width' => 'auto',
                    'items' => [
                        [
                            'type' => 'Image',
                            'url' => $this->messageImageUrl,
                            'altText' => $this->messageTitle,
                            'size' => 'small',
                            'style' => 'person',
                        ],
                    ]
                ];
            }
            if ($this->messageTitle) {
                $columns[] = [
                    'type' => 'Column',
                    'width' => 'stretch',
                    'verticalContentAlignment' => 'center',
                    'items' => [
                        [
                            'type' => 'TextBlock',
                            'text' => $this->messageTitle,
                            'weight' => 'Bolder',
                            'size' => 'Medium',
                        ],
                    ]
                ];
            }

            $bodyItems[] = [
                [
                    'type' => 'ColumnSet',
                    'columns' => $columns,
                ],
            ];
        }

        $bodyItems[] = [
            'type' => 'ColumnSet',
            'columns' => [
                [
                    'type' => 'Column',
                    'width' => 'stretch',
                    'items' => [
                        [
                            'type' => 'TextBlock',
                            'text' => $this->alertTemplate->detail($alert),
                            'weight' => 'Default',
                            'wrap' => true,
                        ],
                        [
                            'type' => 'TextBlock',
                            'spacing' => 'None',
                            'text' => 'Created ' . date(DATE_ATOM, $alert->getCreatedAt()),
                            'isSubtle' => 'true',
                            'wrap' => true,
                        ],
                    ]
                ]
            ]
        ];

        return json_encode([
            'type' => 'message',
            'summary' => $this->alertTemplate->brief($alert),
            'attachments' => [
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'contentUrl' => null,
                'content' => [
                    '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                    'type' => 'AdaptiveCard',
                    'version' => '1.4',
                    'body' => [
                        [
                            'type' => 'Container',
                            'items' => $bodyItems,
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function webhookUrlIsDeprecatedVersion(): bool
    {
        $parsedUrl = parse_url($this->webhookUrl);

        return str_ends_with(strtolower($parsedUrl['host']), 'webhook.office.com');
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
            'ms-teams-webhook-url-' . $this->webhookUrl . '; ' .
            'message-image-url=' . $this->messageImageUrl . '; ' .
            'message-title=' . $this->messageTitle;
    }
}