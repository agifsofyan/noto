<?php

namespace Agifsofyan\Noto\Providers;

use Illuminate\Support\ServiceProvider;

class NotoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configurePublishing();
        $this->migratePublishing();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Merge Config files.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/noto.php', 'noto');
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing()
    {
        if ($this->app->runningInConsole()) {
            
            $this->publishes([
                __DIR__.'/../config/noto.php' => config_path('noto.php'),
              ], 'noto-config');
        }
    }

    /**
     * Migrate publishing for the package.
     *
     * @return void
     */
    protected function migratePublishing()
    {
        if ($this->app->runningInConsole()) {
            $migrationFileName = '2023_01_31_000001_Db_System_Files';
            if (! $this->migrationFileExists($migrationFileName)) {
                $this->publishes([
                    __DIR__ . "/../database/migrations/{$migrationFileName}.php" => database_path('migrations/' . date('Y_m_d_His', time()) . '_' . $migrationFileName),
                ], 'systemfiles-migrations');
            }
        }
    }

    public static function migrationFileExists(string $migrationFileName): bool
    {
        $len = strlen($migrationFileName);
        foreach (glob(database_path("migrations/*.php")) as $filename) {
            if ((substr($filename, -$len) === $migrationFileName)) {
                return true;
            }
        }

        return false;
    }
}