<?php namespace ApiClientTools;

use Illuminate\Support\ServiceProvider;

class ApiClientToolsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(\Illuminate\Routing\Router $router)
    {
        \Auth::provider('api-client', function ($app) {
            return new App\ApiClientUserProvider();
        });

        $argv = $this->app->request->server->get('argv');
        if(isset($argv[1]) and $argv[1]=='vendor:publish') {
            $this->publishes([
                __DIR__.'/../config/api-client.php' => config_path('api-client.php'),
            ], ['config', 'apiclienttools', 'adminify']);
            $this->publishes([
                __DIR__.'/../stubs/App/Api/Base.php.stub' => app_path('/Api/Base.php'),
                __DIR__.'/../stubs/App/User.php.stub' => app_path('/User.php'),
            ], ['model', 'apiclienttools', 'adminify']);
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'api-client');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-client.php', 'api-client');

        $this->app->bind('command.apitools:check', Commands\SetupCommand::class);
        $this->app->bind('command.apitools:publish', Commands\PublishCommand::class);

        $this->commands([
            'command.apitools:check',
            'command.apitools:publish',
        ]);

    }

}
