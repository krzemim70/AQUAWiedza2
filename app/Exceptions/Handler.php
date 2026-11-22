<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    protected function prepareResponse($request, Throwable $e)
    {
        if ($e instanceof HttpExceptionInterface ? $e->getStatusCode() !== 500 : config('app.debug')) {
            return parent::prepareResponse($request, $e);
        }

        // The response factory resolves the redirector, which needs the session store.
        try {
            $response = new Response(view('errors.500'), 500, $e instanceof HttpExceptionInterface ? $e->getHeaders() : []);
        } catch (Throwable $viewException) {
            if (config('app.debug')) {
                throw $viewException;
            }

            $this->report($viewException);
            $fallback = $this->convertExceptionToResponse($e);
            $response = new Response($fallback->getContent(), $fallback->getStatusCode(), $fallback->headers->all());
        }

        return $response->withException($e)->prepare($request);
    }
}
