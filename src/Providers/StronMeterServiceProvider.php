<?php

namespace Inensus\StronMeter\Providers;

use App\Models\MainSettings;
use App\Models\Manufacturer;
use App\Models\Meter\MeterParameter;
use App\Models\Transaction\Transaction;
use GuzzleHttp\Client;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Inensus\StronMeter\Console\Commands\InstallPackage;
use Inensus\StronMeter\Helpers\ApiHelpers;
use Inensus\StronMeter\Http\Requests\StronMeterApiRequests;
use Inensus\StronMeter\Models\StronCredential;
use Inensus\StronMeter\Models\StronTransaction;
use Inensus\StronMeter\StronMeterApi;

class StronMeterServiceProvider extends ServiceProvider
{
    public function boot(Filesystem $filesystem)
    {
        $this->app->register(RouteServiceProvider::class);
        if ($this->app->runningInConsole()) {
            $this->publishConfigFiles();
            $this->publishVueFiles();
            $this->publishMigrations($filesystem);
            $this->commands([InstallPackage::class]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/stron-meter.php', 'stron-meter');
        $this->app->register(EventServiceProvider::class);
        $this->app->register(ObserverServiceProvider::class);
        $this->app->bind('StronMeterApi', function () {
            $client = new Client();
            $meterParameter = new MeterParameter();
            $transaction = new Transaction();
            $stronTransaction = new StronTransaction();
            $mainSettings = new MainSettings();
            $stronCredential = new StronCredential();
            $manufacturer= new Manufacturer();
            $apiHelpers = new ApiHelpers($manufacturer);
            $apiRequests = new StronMeterApiRequests($client,$apiHelpers,$stronCredential);
            return new StronMeterApi(
                $client,
                $meterParameter,
                $stronTransaction,
                $transaction,
                $mainSettings,
                $stronCredential,
                $apiRequests
            );
        });
    }

    public function publishConfigFiles()
    {
        $this->publishes([
            __DIR__ . '/../../config/stron-meter.php' => config_path('stron-meter.php'),
        ]);
    }

    public function publishVueFiles()
    {
        $this->publishes([
            __DIR__ . '/../resources/assets' => resource_path('assets/js/plugins/stron-meter'),
        ], 'vue-components');
    }

    public function publishMigrations($filesystem)
    {
        $this->publishes([
            __DIR__ . '/../../database/migrations/create_stron_tables.php.stub'
            => $this->getMigrationFileName($filesystem),
        ], 'migrations');
    }

    protected function getMigrationFileName(Filesystem $filesystem): string
    {
        $timestamp = date('Y_m_d_His');
        return Collection::make($this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem) {
                return $filesystem->glob($path . '*_create_stron_tables.php');
            })->push($this->app->databasePath() . "/migrations/{$timestamp}_create_stron_tables.php")
            ->first();
    }
}
