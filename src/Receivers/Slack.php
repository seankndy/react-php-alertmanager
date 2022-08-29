<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Support\Traits\ConfigTrait;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\HttpClient\Client;
use React\HttpClient\Response;

class Slack extends AbstractReceiver
{
    use ConfigTrait;

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
            'https://slack.com/api/conversations.open', $params
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
                'https://slack.com/api/chat.postMessage', $params
            )->then(function ($result) {
                if (!isset($result->ok) || !$result->ok) {
                    throw new \Exception("Failed response from Slack's chat.postMessage: " .
                        \json_encode($result));
                }
            });
        });
    }

    /**
     * Make async HTTP POST to $url with $params as payload
     *
     * @param string $url
     * @param array $params Payload
     */
    private function asyncHttpPost(string $url, array $params): PromiseInterface
    {
        $deferred = new \React\Promise\Deferred();

        $client = new Client($this->loop);
        $payload = \http_build_query($params);
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Content-Length' => strlen($payload)
        ];
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
                if (isset($headers['content-type']) &&
                    strstr($headers['content-type'], 'application/json')) {
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
