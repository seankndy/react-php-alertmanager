<?php
namespace SeanKndy\AlertManager\Auth;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

class BasicAuthorizer implements AuthorizerInterface
{
    /**
     * Array of users and passwords ( [user => password] )
     */
    private array $users;

    public function __construct(array $users = [])
    {
        $this->users = $users;
    }

    /**
     * {@inheritDoc} Basic HTTP authentication with users authed from an array.
     */
    public function authorize(ServerRequestInterface $request): PromiseInterface
    {
        $authorizationHeader = $request->getHeaderLine('Authorization');
        if (strpos($authorizationHeader, ' ') === false) {
            return \React\Promise\resolve(false);
        }
        list($type,$creds) = preg_split('/\s+/', $authorizationHeader);

        if (strtoupper($type) != 'BASIC' || !($creds = \base64_decode($creds))
            || strpos($creds, ':') === false) {
            return \React\Promise\resolve(false);
        }
        list($user,$pass) = \explode(':', $creds);
        if (!isset($this->users[$user]) || $this->users[$user] != $pass) {
            return \React\Promise\resolve(false);
        }

        return \React\Promise\resolve(true);
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function setUsers(array $users): self
    {
        $this->users = $users;

        return $this;
    }

}
