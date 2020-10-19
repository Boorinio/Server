<?php

namespace Rubix\Server\Http\Middleware;

use React\Http\Message\Response as ReactResponse;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use InvalidArgumentException;

/**
 * Trusted Clients
 *
 * A whitelist of trust clients - all other clients will be dropped.
 *
 * @category    Machine Learning
 * @package     Rubix/Server
 * @author      Andrew DalPino
 */
class TrustedClients extends Middleware
{
    protected const UNAUTHORIZED = 401;

    protected const FLAGS = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

    /**
     * An array of trusted client ip addresses.
     *
     * @var string[]
     */
    protected $ips;

    /**
     * @param string[] $ips
     * @throws \InvalidArgumentException
     */
    public function __construct(array $ips = ['127.0.0.1'])
    {
        if (empty($ips)) {
            throw new InvalidArgumentException('At least 1 trusted client is required.');
        }

        foreach ($ips as $i => $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, self::FLAGS) === false) {
                throw new InvalidArgumentException("Invalid IP address at position $i.");
            }
        }

        $this->ips = $ips;
    }

    /**
     * Run the middleware over the request.
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next) : Response
    {
        $params = $request->getServerParams();

        if (isset($params['REMOTE_ADDR'])) {
            $ip = (string) explode(':', $params['REMOTE_ADDR'])[0];

            if (in_array($ip, $this->ips)) {
                return $next($request);
            }
        }

        return new ReactResponse(self::UNAUTHORIZED);
    }
}