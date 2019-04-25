<?php
namespace SeanKndy\AlertManager\Auth;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

class BasicAuthorizer implements AuthorizerInterface
{
    /**
     * [user=>password]
     * @var array
     */
    private $users = [];

    /**
     * {@inheritDoc} Basic HTTP authentication with users authed from an array.
     */
    public function authorize(ServerRequestInterface $request) : PromiseInterface
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

    /**
     * Get the value of [user=>password]
     *
     * @return array
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Set the value of [user=>password]
     *
     * @param array users
     *
     * @return self
     */
    public function setUsers(array $users)
    {
        $this->users = $users;

        return $this;
    }

}
