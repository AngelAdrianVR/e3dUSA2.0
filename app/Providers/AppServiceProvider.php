<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ProductionTask;
use App\Observers\ProductionTaskObserver;
use App\Models\DesignOrder;
use App\Observers\DesignOrderObserver;

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
        DesignOrder::observe(DesignOrderObserver::class);
        ProductionTask::observe(ProductionTaskObserver::class);
    }
}
