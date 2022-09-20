<?php
namespace SeanKndy\AlertManager\Auth;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

interface AuthorizerInterface
{
    /**
     * Authorize $request, return promise w/ value of true for success or false
     * for authorization failure.
     *
     * @param ServerRequestInterface $request Request to check auth
     *
     * @return PromiseInterface Return Promise<bool,\Exception>
     */
    public function authorize(ServerRequestInterface $request): PromiseInterface;
}
