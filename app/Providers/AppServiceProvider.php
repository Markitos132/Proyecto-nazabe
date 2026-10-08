<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Carbon no se entera del idioma de la aplicación por su cuenta.
         *
         * Laravel dispara un evento LocaleUpdated al fijar el locale, pero
         * nadie lo escucha para avisarle a Carbon: se queda en inglés pase
         * lo que pase. Por eso el panel mostraba "Última lectura hace
         * 15 seconds" aunque APP_LOCALE ya dijera es.
         *
         * Afecta a todo lo que use diffForHumans: la antigüedad de la última
         * lectura en las tarjetas, el "última vez visto" de Dispositivos y
         * el detalle de la ficha.
         */
        if (config('database.default') === 'sqlite') {
            \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode=WAL;');
             \Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout=5000;');
}
        $idioma = config('app.locale');

        Carbon::setLocale($idioma);
        CarbonImmutable::setLocale($idioma);
    }
}
