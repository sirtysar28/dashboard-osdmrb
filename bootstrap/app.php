<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        // catat setiap kunjungan halaman (statistik pengunjung + audit log)
        $middleware->web(append: \App\Http\Middleware\LogAuditVisit::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
