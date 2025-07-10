<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helpers\RabbitMqHelper;

class RabbitMQServiceProvider extends ServiceProvider
{
    protected $rabbit;

    public function register()
    {
        $this->app->singleton(RabbitMqHelper::class, function () {
            return new RabbitMqHelper();
        });
    }
    public function boot()
    {
        $this->rabbit = app(RabbitMqHelper::class);

        register_shutdown_function(function () {
            if ($this->rabbit) {
                $this->rabbit->close();
            }
        });
    }
}
