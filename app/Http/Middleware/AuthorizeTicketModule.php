<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AuthorizeTicketModule
{
    public function handle(Request $request, Closure $next, string $ability)
    {
        try {
            Gate::authorize($ability);
            $response = $next($request);
            // Laravel's routing pipeline can render a controller exception before it returns here.
            if ($response->getStatusCode() === 403 && !$request->isMethodSafe() && !$request->expectsJson()) {
                return redirect()->back()->with('error', 'You have no permission.');
            }
            return $response;
        } catch (AuthorizationException|HttpExceptionInterface $exception) {
            $forbidden = $exception instanceof AuthorizationException || $exception->getStatusCode() === 403;
            if (!$forbidden || $request->isMethodSafe()) throw $exception;
            $message = $exception->getMessage() ?: 'You have no permission.';
            if ($request->expectsJson()) return response()->json(['message' => $message], 403);
            return redirect()->back()->with('error', $message);
        }
    }
}
