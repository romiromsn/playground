<?php
declare(strict_types=1);

namespace CakeHx\Test\TestCase\Middleware;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use CakeHx\Middleware\HxMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HxMiddlewareTest extends TestCase
{
    private function _makeHandler(?callable $checker = null): RequestHandlerInterface
    {
        return new class ($checker) implements RequestHandlerInterface {
            private $checker;

            public function __construct(?callable $checker)
            {
                $this->checker = $checker;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($this->checker) {
                    ($this->checker)($request);
                }

                return new Response();
            }
        };
    }

    public function testNonHxRequestPassesThrough(): void
    {
        $middleware = new HxMiddleware();
        $request = new ServerRequest();
        $response = $middleware->process($request, $this->_makeHandler());

        $this->assertFalse($response->hasHeader('Vary'));
    }

    public function testHxRequestSetsVaryHeader(): void
    {
        $middleware = new HxMiddleware();
        $request = new ServerRequest([
            'environment' => ['HTTP_X_HX_REQUEST' => 'true'],
        ]);

        $response = $middleware->process($request, $this->_makeHandler());
        $this->assertStringContainsString('X-HX-Request', $response->getHeaderLine('Vary'));
    }

    public function testHxRequestAddsIsHxAttribute(): void
    {
        $middleware = new HxMiddleware();
        $request = new ServerRequest([
            'environment' => ['HTTP_X_HX_REQUEST' => 'true'],
        ]);

        $middleware->process($request, $this->_makeHandler(function (ServerRequestInterface $req) {
            $this->assertTrue($req->getAttribute('isHx'));
        }));
    }

    public function testNonHxRequestSetsIsHxFalse(): void
    {
        $middleware = new HxMiddleware();
        $request = new ServerRequest();

        $middleware->process($request, $this->_makeHandler(function (ServerRequestInterface $req) {
            $this->assertFalse($req->getAttribute('isHx'));
        }));
    }
}
