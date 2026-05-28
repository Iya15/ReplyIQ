<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('SecurityHeaders middleware adds X-Content-Type-Options header', function () {
    $middleware = new SecurityHeaders;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('SecurityHeaders middleware adds X-Frame-Options header', function () {
    $middleware = new SecurityHeaders;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('SecurityHeaders middleware adds Referrer-Policy header', function () {
    $middleware = new SecurityHeaders;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('SecurityHeaders middleware adds Permissions-Policy header', function () {
    $middleware = new SecurityHeaders;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});
