<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', fn () => Inertia::render('Login'));
Route::get('/select-tenant', fn () => Inertia::render('SelectTenant'));
Route::get('/', fn () => Inertia::render('Home'));
