<?php
declare(strict_types=1);

namespace CakeHx\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * HxMiddleware — handles CakeHx-specific request/response processing.
 *
 * - Adds an `isHx` attribute to the request for convenient checking.
 * - Processes server-sent response headers for client-side behaviour
 *   (redirect, refresh, trigger events).
 * - Sets Vary: X-HX-Request for proper HTTP caching.
 */
class HxMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $isHx = $request->getHeaderLine('X-HX-Request') === 'true';

        // Tag the request so controllers/views can easily check
        $request = $request->withAttribute('isHx', $isHx);

        /** @var Response $response */
        $response = $handler->handle($request);

        if (!$isHx) {
            return $response;
        }

        // Ensure caching correctly varies on HX requests
        $vary = $response->getHeaderLine('Vary');
        if (!str_contains($vary, 'X-HX-Request')) {
            $response = $response->withHeader(
                'Vary',
                $vary ? $vary . ', X-HX-Request' : 'X-HX-Request'
            );
        }

        return $response;
    }
}
