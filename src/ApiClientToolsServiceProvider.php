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
        $argv = $this->app->request->server->get('argv');
        if(isset($argv[1]) and $argv[1]=='vendor:publish') {
            if(!file_exists(app_path('/Api'))) {
                mkdir(app_path('/Api'));
            }

            $this->publishes([
                __DIR__.'/../config/api-client.php' => config_path('api-client.php'),
            ], ['config', 'apiclienttools', 'adminify']);
            $this->publishes([
                __DIR__.'/../stubs/Api/Base.php.stub' => app_path('/Api/Base.php'),
            ], ['model', 'apiclienttools', 'adminify']);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/apiclient.php', 'api-client');

        $this->app->bind('command.apitools:check', Commands\SetupCommand::class);
        $this->app->bind('command.apitools:build', Commands\DocsCommand::class);

        $this->commands([
            'command.apitools:check',
            'command.apitools:build',
        ]);

    }

}
