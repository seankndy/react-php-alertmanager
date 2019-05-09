<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\ThrottledReceiverAlert;
use SeanKndy\AlertManager\Support\Traits\ConfigTrait;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\HttpClient\Client;
use React\HttpClient\Response;

class Slack extends AbstractReceiver
{
    use ConfigTrait;

    /**
     * @var LoopInterface
     */
    protected $loop;
    /**
     * Slack member ID (i.e. W1234567890)
     * @var string
     */
    protected $memberId;

    public function __construct($id, LoopInterface $loop,
        string $memberId, array $config)
    {
        parent::__construct($id);

        $this->loop = $loop;
        $this->memberId = $memberId;

        $this->config = \array_merge([
            'api_token' => '',
            'message_template' => ''
        ], $config);
    }

    /**
     * {@inheritDoc}
     */
    public function receive(Alert $alert) : PromiseInterface
    {
        if (!$this->memberId || !$this->config['api_token']) {
            return \React\Promise\resolve([]);
        }

        if ($alert instanceof ThrottledReceiverAlert) {
            $msg = 'Alerts to this receiver have been throttled until ' .
                \date(DATE_ATOM, $alert->getAttributes()['expiresAt']) . '.';
        } else {
            $msg = $this->interpolate(
                $alert->getAttributes(),
                ($alert->isRecovered() ?
                    'RECOVERED from ' . $this->config['message_template'] :
                    $this->config['message_template'])
            );
        }

        $params = [
            'token' => $this->config['api_token'],
            'user' => $this->memberId
        ];
        return $this->asyncHttpPostJson(
            'https://slack.com/api/im.open', $params
        )->then(function ($result) use ($msg) {
            if (!isset($result->ok) || $result->ok != 'true'
                || !$result->channel->id) {
                throw new \Exception("Failed response from Slack's im.open.");
            }

            $params = [
                'token' => $this->config['api_token'],
                'text' => $msg,
                'channel' => $result->channel->id,
                'as_user' => 'true'
            ];
            return $this->asyncHttpPostJson(
                'https://slack.com/api/chat.postMessage', $params
            )->then(function ($result) {
                if (!isset($result->ok) || $result->ok != 'true') {
                    throw new \Exception("Failed response from Slack's chat.postMessage.");
                }
            });
        });
    }

    /**
     * Make async HTTP POST to $url with json-encoded $params as payload
     *
     * @param string $url
     * @param array $params Payload
     *
     * @return PromiseInterface
     */
    private function asyncHttpPostJson(string $url, array $params)
    {
        $deferred = new \React\Promise\Deferred();

        $client = new Client($this->loop);
        $jsonParams = \json_encode($params);
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($jsonParams)
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
            $response->on('end', function() use (&$respBody, $deferred) {
                // assume data back is JSON-encoded....
                $respData = \json_decode(\trim($respBody));
                $deferred->resolve($respData);
            });
        });
        $request->on('error', function (\Throwable $e) use ($deferred) {
            $deferred->reject($e);
        });
        $request->end($jsonParams);

        return $deferred->promise();
    }

    /**
     * Interpolate values from $vars [variable=>value] into $str
     * where $str uses %foo% for variable named 'foo' from $vars.
     * Case insensitive.
     *
     * @var array $vars Array of variable=>value pairs used as interpolation source
     * @var string $str Template string using variables from $vars as %var%
     *
     * @return string The interpolated string
     */
    private function interpolate(array $vars, string $str)
    {
        return \str_ireplace(
            \array_map(function ($var) {
                return "%$var%";
            }, \array_keys($vars)),
            \array_values($vars),
            $str
        );
    }

    /**
     * Get the memberId
     *
     * @return string
     */
    public function getMemberId()
    {
        return $this->memberId;
    }

    public function __toString()
    {
        return parent::__toString() . '; ' .
            'slack-member-id=' . $this->memberId;
    }
}
