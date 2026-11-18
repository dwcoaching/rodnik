<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ReturnUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RememberReturnUrl
{
    public function __construct(private ReturnUrl $returnUrl) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->routeIs('login', 'register')) {
            $this->returnUrl->remember($request);
        }

        return $next($request);
    }
}
