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
@if(!empty(\ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodBodyContent']))
        {!! \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodBodyContent'] !!}
@endif
        return self::getRequest('{{ $method['route']['uri'] }}'{{ \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodParametersString'] }}, $data);
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
@if(!empty(\ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodBodyContent']))
        {!! \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodBodyContent'] !!}
@endif
        return self::postRequest('{{ $method['route']['uri'] }}'{{ \ApiClientTools\Commands\PublishCommand::getParametersStrings($method)['methodParametersString'] }}, $data);
    }
@endif
