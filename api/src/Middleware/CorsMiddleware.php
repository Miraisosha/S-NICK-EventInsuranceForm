<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Cake\Core\env;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = env('FRONTEND_ORIGIN', 'http://localhost:5173');

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = new Response(['status' => 204]);
        } else {
            $response = $handler->handle($request);
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type')
            ->withHeader('Access-Control-Max-Age', '600');
    }
}
