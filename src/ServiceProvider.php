<?php namespace Visualplus\PgInicis;

class ServiceProvider extends \Illuminate\Support\ServiceProvider {
    /**
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../views', 'inicis');

        $this->publishes([
            __DIR__ . '/../config/inicis.php' => config_path('inicis.php'),
        ]);
    }

    /**
     * @return void
     */
    public function register()
    {

    }
}