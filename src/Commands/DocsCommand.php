<?php namespace ApiTools\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;

class DocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apitools:docs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generating API documentation';

    protected $lastElement = null;
    protected $anchors = [];

    protected $commonRecipe = 'has-common-headers="true"';

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
        $this->checkPaths();
        $this->info('Generating documentation');
        $this->generateDocumentation();
        $this->info('All done');
    }

    private function checkPaths()
    {
        $basePath = resource_path('docs');
        if(!file_exists($basePath)) {
            mkdir($basePath);
        }
        $versionPath = $basePath.'/'.'master';
        if(!file_exists($versionPath)) {
            mkdir($versionPath);
        }
    }

    private function getApiSchema()
    {
        return (new \App\Http\Controllers\Api\Api())->generateSchema();
    }

    private function generateDocumentation()
    {
        $schema = $this->getApiSchema();

        foreach ($schema['classes'] as $class) {
            $this->generateDocsForClass($class);
        }

        $this->generateOverview($schema);

        $this->generateSideMenu($schema['classes']);
    }

    private function generateSideMenu($models)
    {
        $string  = '- '.$this->h2('Introduction');
        $string .= '    - [Overview](/{{route}}/{{version}}/overview)'.$this->newline(1);
        $string .= '- '.$this->h2('Models');

        foreach ($models as $model) {
            $string .= '    - [' . $model['name'] . '](/{{route}}/{{version}}/' . Str::slug($model['name']) .')'. $this->newline(1);
        }

        file_put_contents(resource_path('docs/master/index.md'), $string);
    }

    private function generateDocsForClass($class)
    {
        $string = $this->h1($class['name']);

        // Table of contents

        $string .= $this->sideMenuTitle('Methods');
        foreach ($class['methods'] as $method) {
            $string .= $this->sideMenuItemAnchor($method['name']);
        }

        // Content

        $string .= $this->alarm($class['description'], 'primary', 'fa-info');

        $string .= $this->alarm('Controller class: '.$class['fullName'], 'warning', 'fa-file-code-o');

        $string .= $this->alarm('Model: '.$class['model'], 'success', 'fa-code');
        $string .= $this->spacer();


        if(!empty($class['properties'])) {
            $string .= $this->h4('Properties');

            $rows = [];
            foreach ($class['properties'] as $name => $value) {
                $rows[] = [$name, $value];
            }

            $string .= $this->tableSet(['Name', 'Value'], $rows);
        }

        if (!empty($class['constants'])) {
            $string .= $this->spacer();
            $string .= $this->h4('Constants');

            $rows = [];
            foreach ($class['constants'] as $name => $value) {
                $rows[] = [$name, $value];
            }

            $string .= $this->tableSet(['Name', 'Value'], $rows);
        }

        $string .= $this->spacer();


        $string .= $this->anchor('methods');
        $string .= $this->h2('Methods');

        foreach ($class['methods'] as $method) {
            $string .= $this->anchor(Str::slug($method['name']));
            $string .= $this->h3($method['name']);

            if(isset($method['api']['description'])) {
                $string .= $this->alarm($method['api']['description'], 'primary', 'fa-commenting');
            }

            if (isset($method['api']['exampleReturn']) and !empty($method['api']['exampleReturn'])) {
                $string .= $this->h4('Example response');

                $json = $this->formatJsonForMarkdown($method['api']['exampleReturn']);
                $string .= $this->code($json, 'json');
            }

            $string .= $this->h4('Path');
            $string .= "```markdown \n" . $method['route']['uri'] . " \n ``` \n";

            $string .= $this->h4('Accepts');
            $this->newline();

            foreach($method['route']['accepts'] as $acceptedMethod) {
                $string .= $this->badge($acceptedMethod, 'success');
            }

            if (!empty($method['parameters'])) {
                $string .= $this->spacer();
                $string .= $this->h4('Parameters');

                $rows = [];
                foreach ($method['parameters'] as $param) {
                    $rows[] = [$param['name'], $param['type'], ($param['required'] ? 'True' : 'False'), ($param['default'] == null ? 'Null' : $param['default'])];
                }

                $string .= $this->tableSet(['Name', 'Type', 'Required', 'Default'], $rows);
            }

            $string .= $this->spacer();
            $string .= $this->recipeSwagger($method);
        }

        file_put_contents(resource_path('docs/master/' . Str::slug($class['name']) . '.md'), $string);
    }

    private function formatJsonForMarkdown($jsonText)
    {
        $json = str_replace('#', '\#', $jsonText);
        $result = '';
        $level = 0;
        $in_quotes = false;
        $in_escape = false;
        $ends_line_level = NULL;
        $json_length = strlen($json);

        for ($i = 0; $i < $json_length; $i++) {
            $char = $json[$i];
            $new_line_level = NULL;
            $post = "";
            if ($ends_line_level !== NULL) {
                $new_line_level = $ends_line_level;
                $ends_line_level = NULL;
            }
            if ($in_escape) {
                $in_escape = false;
            } else if ($char === '"') {
                $in_quotes = !$in_quotes;
            } else if (!$in_quotes) {
                switch ($char) {
                    case '}':
                    case ']':
                        $level--;
                        $ends_line_level = NULL;
                        $new_line_level = $level;
                        break;

                    case '{':
                    case '[':
                        $level++;
                    case ',':
                        $ends_line_level = $level;
                        break;

                    case ':':
                        $post = " ";
                        break;

                    case " ":
                    case "\t":
                    case "\n":
                    case "\r":
                        $char = "";
                        $ends_line_level = $new_line_level;
                        $new_line_level = NULL;
                        break;
                }
            } else if ($char === '\\') {
                $in_escape = true;
            }
            if ($new_line_level !== NULL) {
                $result .= "\n" . str_repeat("\t", $new_line_level);
            }
            $result .= $char . $post;
        }

        return $result;
    }

    private function generateOverview($schema)
    {
        $string = '';

        $string .= $this->anchor('Intro');

        foreach($schema['intro'] as $part) {
            $string .= $this->{$part['type']}($part['content']);
        }

        //$string .= $this->anchor('Image');
        //$string .= $this->h2('Image');
        //$string .= $this->image('https://media.tenor.com/images/f45c43d124468dc602a95baabadab70d/tenor.gif', 'cute image');
        //
        //
        //$string .= $this->anchor('Code');
        //$string .= $this->h2('Code');
        //$string .= $this->paragraph('This is an `example` of a `inline code`.');
        //$string .= $this->code('This is an example of a code.', 'php');
        //$string .= $this->badge('Example.', 'warning');
        //$string .= $this->alarm('Here is an example of danger alarm message.', 'danger');
        //$string .= $this->alarm('Here is an example of a font awesome icon.', 'danger', 'fa-close');

        //$string = $this->processAnchors($string);

         $string .= $this->sideMenuTitle('Introduction');
         $string .= $this->sideMenuItem('Overview');
         //$string .= $this->sideMenuItem('Installation');

         //$string .= $this->sideMenuTitle('Models');
         //$string .= $this->sideMenuItem('Pages');
         //$string .= $this->sideMenuItem('Settings');

        file_put_contents(resource_path('docs/master/overview.md'), $string);
    }

    private function demo()
    {
        $string = '';

        $string .= $this->anchor('Typography');
        $string .= $this->h2('Typography');

        $string .= $this->h1('First H1 on page');
        $string .= $this->h1('Second H1 on page');
        $string .= $this->h2('H2');
        $string .= $this->h3('H3');
        $string .= $this->h4('H4');
        $string .= $this->h5('H5');
        $string .= $this->h6('H6');
        $string .= $this->paragraph('This is an example of a paragraph.');
        $string .= $this->paragraph('Lorem ipsum.');

        $string .= $this->anchor('Image');
        $string .= $this->h2('Image');
        $string .= $this->image('https://media.tenor.com/images/f45c43d124468dc602a95baabadab70d/tenor.gif', 'cute image');


        $string .= $this->anchor('Code');
        $string .= $this->h2('Code');
        $string .= $this->paragraph('This is an `example` of a `inline code`.');
        $string .= $this->code('This is an example of a code.', 'php');

        $headers = ['Param', 'Value'];
        $items = [
            [
                'ID',
                '1'
            ],
            [
                'Slug',
                'i-like-cheese'
            ]
        ];
        $string .= $this->anchor('Tables');
        $string .= $this->h2('Tables');
        $string .= $this->tableSet($headers, $items);

        $string .= $this->anchor('Alarms and badges');
        $string .= $this->h2('Alarms and badges');
        $string .= $this->badge('Example.', 'warning');
        $string .= $this->alarm('Here is an example of danger alarm message.', 'danger');
        $string .= $this->alarm('Here is an example of a font awesome icon.', 'danger', 'fa-close');

        $string = $this->processAnchors($string);

        file_put_contents(resource_path('docs/master/demo.md'), $string);


        // Side menu

        // $string = '';
        // $string .= $this->sideMenuTitle('Introduction');
        // $string .= $this->sideMenuItem('Overview');
        // $string .= $this->sideMenuItem('Installation');

        // $string .= $this->sideMenuTitle('Models');
        // $string .= $this->sideMenuItem('Pages');
        // $string .= $this->sideMenuItem('Settings');

        file_put_contents(resource_path('docs/master/overview.md'), $string);
    }

    private function sideMenuTitle($title)
    {
        return '- ## ' . trim($title) . $this->newline(2);
    }

    private function sideMenuItem($item, $anchorName=null)
    {
        if(empty($anchorName)) {
            $anchorName = Str::slug($item);
        }
        return '    - [' . $item . '](/{{route}}/{{version}}/' . $anchorName . ')' . $this->newline(2);
    }

    private function sideMenuItemAnchor($item, $anchorName=null)
    {
        if(empty($anchorName)) {
            $anchorName = Str::slug($item);
        }
        return '    - [' . $item . '](#' . $anchorName . ')' . $this->newline(2);
    }

    private function h1($text = '')
    {
        $this->setLastElement(__FUNCTION__);

        return '# ' . trim($text) . $this->newline(2);
    }

    private function h2($text = '')
    {
        $this->setLastElement(__FUNCTION__);

        return '## ' . trim($text) . $this->newline(2);
    }

    private function h3($text = '')
    {
        $this->setLastElement(__FUNCTION__);

        return '### ' . trim($text) . $this->newline(2);
    }

    private function h4($text = '')
    {
        $this->setLastElement(__FUNCTION__);

        return '#### ' . trim($text) . $this->newline(2);
    }

    private function h5($text = '')
    {
        $this->setLastElement(__FUNCTION__);

        return '##### ' . trim($text) . $this->newline(2);
    }

    private function h6($text = '')
    {
        $this->setLastElement(__FUNCTION__);

        return '###### ' . trim($text) . $this->newline(2);
    }

    private function paragraph($text = '')
    {
        $this->setLastElement(__FUNCTION__);

        return trim($text) . $this->newline(2);
    }

    private function newline($amount = 1)
    {
        $string = '';

        $i = 1;
        while ($i <= $amount) {
            $i++;
            $string .= "\n";
        }

        $this->setLastElement(__FUNCTION__);

        return $string;
    }

    private function image($src, $alt = 'Image')
    {
        $this->setLastElement(__FUNCTION__);

        return '![' . $alt . '](' . $src . ')' . $this->newline(2);
    }

    private function code($text, $type = 'php')
    {
        $this->setLastElement(__FUNCTION__);

        return '```' . $type . ' ' . $this->newline() . trim($text) . $this->newline() . '```' . $this->newline(2);
    }

    private function badge($text, $type = 'success')
    {
        $this->checkType($type);

        $this->setLastElement(__FUNCTION__);

        return '<larecipe-badge type="' . $type . '" rounded>' . trim($text) . '</larecipe-badge>' . $this->newline(2);
    }

    private function alarm($text, $type = 'success', $icon = null)
    {
        $this->checkType($type);

        $string = '';

        if ($this->lastElement == __FUNCTION__) {
            $string .= '<!-- -->' . $this->newline(2);
        }

        $string .= '> {';

        $string .= trim($type);
        if ($icon) {
            $string .= '.' . trim($icon);
        }
        $string .= '} ' . trim($text);
        $string .= $this->newline(2);

        $this->setLastElement(__FUNCTION__);

        return $string;
    }

    private function anchor($name)
    {
        $this->anchors[] = $name;

        $this->setLastElement(__FUNCTION__);

        return '<a name="' . Str::slug($name) . '"></a>' . $this->newline(2);
    }

    private function processAnchors($string)
    {
        $anchorString = '';

        foreach ($this->anchors as $anchor) {
            $anchorString .= $this->tocItem($anchor);
        }

        return $anchorString . $this->newline(2) . $string;
    }

    private function tocItem($name)
    {
        return '- [' . $name . '](#' . Str::slug($name) . ')' . $this->newline();
    }

    private function checkType($type)
    {
        $allowedTypes = ['primary', 'success', 'info', 'danger', 'warning'];
        if (!in_array($type, $allowedTypes)) {
            $this->error('Invalid type:' . $type . '. Allowed: ' . implode(',', $allowedTypes));
            exit();
        }
    }

    private function setLastElement($element)
    {
        $this->lastElement = $element;
    }

    private function spacer()
    {
        return '---'.$this->newline(1);
    }

    private function recipeSwagger($method)
    {
        $url = $method['route']['uri'];

        foreach($method['parameters'] as $param) {
            if(isset($method['api']['exampleParam'.ucfirst($param['name'])])) {
                $url = str_replace('{'.$param['name'].'}', $method['api']['exampleParam'.ucfirst($param['name'])], $url);
            } else {
                if($param['type']=='int') {
                    $url = str_replace('{'.$param['name'].'}', '1', $url);
                }
            }
        }

        $requestParams = [];

        foreach($method['api'] as $param=>$value) {
            if(Str::startsWith($param, 'requestParam')) {
                $requestParams[strtolower(substr($param, 12))] = $value;
            }
        }

        $string  = $this->newline();
        $string .='<larecipe-swagger';
        $string .=' base-url="/"';
        $string .=' endpoint="' . $url . '"';
        $string .=' default-method="'.strtolower($method['route']['accepts'][0]).'"';
        $string .=' :default-headers=\''.json_encode(config('api.api.requestHeaders')).'\'';
        $string .= ' '.trim($this->commonRecipe);
        $string .=' :default-params=\''.json_encode($requestParams).'\'';
        $string .='></larecipe-swagger>';
        $string .= $this->newline();

        return $string;
    }

    private function tableSet($headers = [], $items = [])
    {
        if (empty($headers)) {
            exit();
        }

        $string = '';
        $headerString = '| ';
        $headerString .= implode(' | ', $headers);


        $string .= trim($headerString) . ' |' . $this->newline();

        $separatorString = '| ';

        $first = true;
        foreach ($headers as $header) {
            if ($first) {
                $separatorString .= ':- | ';
                $first = false;
            } else {
                $separatorString .= ': |';
            }
        }

        $string .= trim($separatorString) . $this->newline();

        $headerCount = count($headers);

        foreach ($items as $row) {
            $rowString = '| ';
            foreach($headers as $index=>$header) {
                if(isset($row[$index])) {
                    $item = $row[$index];
                    if(is_array($item)) {
                        $item = implode(',', $item);
                    }

                    $item = trim($item);
                    if(empty($item)) {
                        continue 2;
                    }
                } else {
                    continue 2;
                }

                $rowString.= trim($item).' | ';
            }
            $string .= trim($rowString).$this->newline();
        }

        return $string . $this->newline();
    }
}
