<?php

namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Receivers\Traits\MakesHttpRequests;
use SeanKndy\AlertManager\Support\Traits\ConfigTrait;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class Slack extends AbstractReceiver
{
    use ConfigTrait, MakesHttpRequests;

    protected LoopInterface $loop;

    /**
     * Slack member ID (i.e. W1234567890)
     */
    protected string $memberId;

    public function __construct($id, LoopInterface $loop,
        string $memberId, array $config)
    {
        parent::__construct($id);

        $this->loop = $loop;
        $this->memberId = $memberId;

        $this->config = \array_merge([
            'api_token' => ''
        ], $config);
    }

    public function receive(Alert $alert): PromiseInterface
    {
        if (!$this->memberId || !$this->config['api_token'] || !$this->alertTemplate) {
            return \React\Promise\resolve([]);
        }

        $msg = $this->alertTemplate->detail($alert);

        $params = [
            'token' => $this->config['api_token'],
            'users' => $this->memberId
        ];
        return $this->asyncHttpPost(
            $this->loop,
            'https://slack.com/api/conversations.open',
            http_build_query($params),
            ['Content-Type' => 'application/x-www-form-urlencoded']
        )->then(function ($result) use ($msg) {
            if (!isset($result->ok) || !$result->ok || !$result->channel->id) {
                throw new \Exception("Failed response from Slack's conversations.open: " .
                    \json_encode($result));
            }

            $params = [
                'token' => $this->config['api_token'],
                'text' => $msg,
                'channel' => $result->channel->id,
                'as_user' => 'true'
            ];

            return $this->asyncHttpPost(
                $this->loop,
                'https://slack.com/api/chat.postMessage',
                http_build_query($params),
                ['Content-Type' => 'application/x-www-form-urlencoded']
            )->then(function ($result) {
                if (!isset($result->ok) || !$result->ok) {
                    throw new \Exception("Failed response from Slack's chat.postMessage: " .
                        \json_encode($result));
                }
            });
        });
    }

    public function getMemberId(): string
    {
        return $this->memberId;
    }

    public function setMemberId(string $memberId): void
    {
        $this->memberId = $memberId;
    }

    public function __toString(): string
    {
        return parent::__toString() . '; ' .
            'slack-member-id=' . $this->memberId;
    }
}
