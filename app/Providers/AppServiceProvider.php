<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate Limiter Anti-Brute-Force sur la connexion (/api/v1/auth/login) : Max 5 tentatives par minute
        RateLimiter::for('login', function (Request $request) {
            $key = 'login.' . $request->ip() . '.' . Str::slug($request->input('login', $request->input('email', '')));
            return Limit::perMinute(5)->by($key)->response(function () {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Trop de tentatives de connexion échouées. Veuillez réespacer vos requêtes d\'une minute pour des raisons de sécurité.',
                ], 429);
            });
        });

        // Rate Limiter Anti-Spam sur les préinscriptions publiques (/api/v1/preinscriptions) : Max 10 par heure
        RateLimiter::for('preinscription', function (Request $request) {
            return Limit::perHour(10)->by($request->ip())->response(function () {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Limite de soumissions de préinscriptions atteinte pour votre adresse IP. Veuillez réessayer plus tard.',
                ], 429);
            });
        });

        // Rate Limiter Général pour l'API : Max 120 requêtes par minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Observers pour les notifications d'activité automatiques
        \App\Models\Paiement::observe(\App\Observers\PaiementObserver::class);
        \App\Models\Note::observe(\App\Observers\NoteObserver::class);
        \App\Models\Seance::observe(\App\Observers\SeanceObserver::class);
        \App\Models\Preinscription::observe(\App\Observers\PreinscriptionObserver::class);
        \App\Models\Catechumene::observe(\App\Observers\CatechumeneObserver::class);
        \App\Models\Classe::observe(\App\Observers\ClasseObserver::class);
    }
}
