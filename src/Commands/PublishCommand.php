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
            $content = file_get_contents($filePath);
            if (strpos($content, '@apiHash')) {
                $this->error('Removing class ' . $className);
                unlink($filePath);
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
            $parameterString = $parameter['type'] . ' $' . $parameter['name'];
            if($parameter['default']) {
                if($parameter['type']=='string') {
                    $parameterString.='=\''.$parameter['default'].'\'';
                } else {
                    $parameterString.='='.$parameter['default'];
                }
            }

            $parametersString[] = $parameterString;
            $methodParamsString[] = '$' . $parameter['name'];
        }

        if(!in_array('POST', $method['route']['accepts'])) {
            $parametersString[] = 'array $data=[]';
        }

        $parametersString = implode(', ', $parametersString);

        if (empty($methodParamsString)) {
            $methodParamsString = ', []';
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

        // post arguments of the published method
        if (empty($postParamsString)) {
            $postParamsString = 'array $data = []';
        } else {
            $postParamsString = implode(', ', $postParamsString).', array $data = []';
        }

        // arguments of the published method
        if(!empty($parametersString)) {
            $postParamsString = ', '.$postParamsString;
        }

        if(isset($method['api']['supportsPagination']) and $method['api']['supportsPagination']) {
            $methodPostParamsString[] = '\'page\'=>\ApiClientTools\App\Api\Base::page()';
        }

        // in body of the method
        if (empty($methodPostParamsString)) {
            $methodPostParamsString = '';
        } else {
            $methodPostParamsString = '$data = [' . implode(', ', $methodPostParamsString).'] + $data;';
        }

        return [
            'parametersString'=>$parametersString, // arguments of the published method
            'postParamsString'=>$postParamsString, // post arguments of the published method
            'methodBodyContent'=>$methodPostParamsString, // in the body of the method
            'methodParametersString'=>$methodParamsString, // arguments of the invoked method
        ];
    }

    public static function getDotNetParametersStrings($method)
    {
        $parametersString = [];
        $methodParamsString = [];
        $postParamsString = [];
        $methodPostParamsString = [];

        foreach ($method['parameters'] as $param=>$parameter) {
            if($parameter['type']=='float') {
                $parameter['type'] = 'double';
                $method['parameters'][$param] = $parameter;
            }

            $parameterString = $parameter['type'] . ' ' . $parameter['name'];
            if($parameter['default']) {
                if($parameter['type']=='string') {
                    $parameterString.='=\''.$parameter['default'].'\'';
                } else {
                    $parameterString.='='.$parameter['default'];
                }
            }

            $parametersString[] = $parameterString;

            if($parameter['type']=='string') {
                $methodParamsString[] = '{ "'.$parameter['name'].'", '.$parameter['name'].' }';
            } else if($parameter['type']=='int') {
                $methodParamsString[] = '{ "'.$parameter['name'].'", '.$parameter['name'].'.ToString() }';
            } else if($parameter['type']=='double') {
                $methodParamsString[] = '{ "'.$parameter['name'].'", '.$parameter['name'].'.ToString() }';
            }
        }

        if(!in_array('POST', $method['route']['accepts'])) {
            $parametersString[] = 'System.Collections.Generic.Dictionary<string, string> endpointUrlData = null';
        }

        //
        if(!in_array('POST', $method['route']['accepts']) and isset($method['api']['supportsPagination']) and $method['api']['supportsPagination']) {
            $parametersString[] = 'int page = 1';
        }

        $parametersString = implode(', ', $parametersString);

        if (empty($methodParamsString)) {
            $methodParamsString = ', null';
        } else {
            $methodParamsString = ', new System.Collections.Generic.Dictionary<string, string>() { '.implode(', ', $methodParamsString).' }';
        }

        foreach($method['api'] as $parameter=>$value)
        {
            if(strpos($parameter, 'postParam')===0) {
                $postParam = trim(lcfirst(substr($parameter, 9)));
                $postParamsString[] = 'dynamic ' . $postParam;
                $methodPostParamsString[$value] = $postParam;
            }
        }

        // post arguments of the published method
        if (empty($postParamsString)) {
            $postParamsString = 'dynamic endpointData = null';
        } else {
            $postParamsString = implode(', ', $postParamsString).', dynamic endpointData = null';
        }

        // arguments of the published method
        if(!empty($parametersString)) {
            $postParamsString = ', '.$postParamsString;
        }

        if(isset($method['api']['supportsPagination']) and $method['api']['supportsPagination']) {
            //$methodPostParamsString[] = '\'page\'=>\ApiClientTools\App\Api\Base::page()';
        }

        // in body of the method
        if (empty($methodPostParamsString)) {
            $methodPostParamsString = '';
        } else {
            $methodPostParamsStringOutput = 'if(endpointData==null) {
                endpointData = new System.Dynamic.ExpandoObject();
            }'.PHP_EOL;
            foreach($methodPostParamsString as $value=>$postParam) {
                $methodPostParamsStringOutput.='            endpointData.'.$value.' = '.$postParam.';'.PHP_EOL;
            }
            $methodPostParamsString = $methodPostParamsStringOutput;
        }

        if(!in_array('POST', $method['route']['accepts']) and isset($method['api']['supportsPagination'])) {
            $methodPostParamsString = 'if(endpointUrlData==null) {endpointUrlData = new System.Collections.Generic.Dictionary<string, string> { {"page", page.ToString()} }; }
                else if (!endpointUrlData.ContainsKey("page")) { endpointUrlData.Add("page", page.ToString()); }'.PHP_EOL;
        }

        return [
            'parametersString'=>$parametersString, // arguments of the published method
            'postParamsString'=>$postParamsString, // post arguments of the published method
            'methodBodyContent'=>$methodPostParamsString, // in the body of the method
            'methodParametersString'=>$methodParamsString, // arguments of the invoked method
        ];
    }
}
