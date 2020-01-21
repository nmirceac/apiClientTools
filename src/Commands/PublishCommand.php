<?php namespace ApiClientTools\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;

class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apitools:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the API models';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Check the API models directory');
        $this->checkApiModelsDirectory();
        $this->info('Cleaning around');
        $this->cleanOldModels();
        $this->info('Publishing new models');
        $this->publishModels();
        $this->info('All done');
    }

    private function getApiModelsDirectoryPath()
    {
        return app_path(\ApiClientTools\App\Api\Base::getBaseNamespace());
    }

    private function checkApiModelsDirectory()
    {
        if (!file_exists($this->getApiModelsDirectoryPath())) {
            mkdir($this->getApiModelsDirectoryPath());
        }
    }

    private function cleanOldModels()
    {
        $modelFiles = glob($this->getApiModelsDirectoryPath() . '/*.php');
        foreach ($modelFiles as $filePath) {
            $className = substr($filePath, 1 + strrpos($filePath, '/'), -4);
            //$class = '\App\\'.$this->baseNameSpace.'\\'.$className;
            $content = file_get_contents($filePath);
            if (strpos($content, '@apiHash')) {
                $this->error('Removing class ' . $className);
            }
        }
    }

    private function publishModels()
    {
        $schema = $this->getApiSchema();
        $classes = $schema['classes'];
        foreach ($classes as $class) {
            $model = $class['model'];
            $this->comment('Publishing model ' . $model);
            $phpFilePath = $this->getApiModelsDirectoryPath() . '/' . $model . '.php';
            $phpCode = $this->getPhpCode($class);
            file_put_contents($phpFilePath, $phpCode);
        }
    }

    private function getApiSchema()
    {
        return \ApiClientTools\App\Api\Base::getRequest('api/schema');
    }

    private function getPhpCode(array $class)
    {
        $this->class = $class;

        $phpCode = '<?php '.view('api-client::class', ['class'=>$this->class])->render();

        return trim($phpCode);
    }

    public static function getParametersStrings($method)
    {
        $parametersString = [];
        $methodParamsString = [];
        $postParamsString = [];
        $methodPostParamsString = [];

        foreach ($method['parameters'] as $parameter) {
            $parametersString[] = $parameter['type'] . ' $' . $parameter['name'];
            $methodParamsString[] = '$' . $parameter['name'];
        }

        $parametersString = implode(', ', $parametersString);

        if (empty($methodParamsString)) {
            $methodParamsString = '';
        } else {
            $methodParamsString = ', [' . implode(', ', $methodParamsString) . ']';
        }

        foreach($method['api'] as $parameter=>$value)
        {
            if(strpos($parameter, 'postParam')===0) {
                $postParam = trim(lcfirst(substr($parameter, 9)));
                $postParamsString[] = '$' . $postParam;
                $methodPostParamsString[] = '\''.$value.'\'=>$'.$postParam;
            }
        }

        if (empty($postParamsString)) {
            $postParamsString = '$data = []';
        } else {
            $postParamsString = implode(', ', $postParamsString).', $data = []';
        }

        if(!empty($parametersString)) {
            $postParamsString = ', '.$postParamsString;
        }

        if (empty($methodPostParamsString)) {
            $methodPostParamsString = '';
        } else {
            $methodPostParamsString = '$data = [' . implode(', ', $methodPostParamsString).'] + $data;';
        }

        return [
            'parametersString'=>$parametersString,
            'methodParametersString'=>$methodParamsString,
            'postParamsString'=>$postParamsString,
            'methodPostParamsString'=>$methodPostParamsString,
        ];
    }
}
