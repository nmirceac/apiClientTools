@if(in_array('GET', $method['route']['accepts']))
    /**
    * {{ $method['name'] }}
@if(isset($method['api']['description']) and !empty($method['api']['description']))
    * {!! $method['api']['description'] !!}
@endif
@foreach ($method['parameters'] as $parameter)
    * {{ '@' }}param {{ $parameter['type'] }} ${{ $parameter['name'] }}
@endforeach
    * {{ '@' }}return array
    */
    public static function {{ $method['name'] }}({{ \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['parametersString'] }})
    {
        return self::getRequest('{{ $method['route']['uri'] }}'{{ \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodParametersString'] }});
    }
@elseif(in_array('POST', $method['route']['accepts']))
    /**
    * {{ $method['name'] }}
@if(isset($method['api']['description']) and !empty($method['api']['description']))
    * {!! $method['api']['description'] !!}
@endif
@foreach ($method['parameters'] as $parameter)
    * {{ '@' }}param {{ $parameter['type'] }} ${{ $parameter['name'] }}
@endforeach
    * {{ '@' }}return array
    */
    public static function {{ $method['name'] }}({{ \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['parametersString'] }}{{ \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['postParamsString'] }})
    {
@if(!empty(\ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodPostParamsString']))
        {!! \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodPostParamsString'] !!}
@endif
        return self::postRequest('{{ $method['route']['uri'] }}'{{ \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodParametersString'] }}, $data);
    }
@endif
