namespace App\{{ \ApiClientTools\App\Api\Base::getBaseNamespace() }};

/**
 * Class {{ $class['name'] }}
 * {{ $class['description'] }}
 * {{ '@' }}apiHash {{ md5(json_encode($class)) }}
 * {{ '@' }}package App\{{ \ApiClientTools\App\Api\Base::getBaseNamespace() }}\{{ $class['name'] }}
 */
class {{ $class['name'] }} extends Base
{
@if(isset($class['internalModel']) and !empty($class['internalModel']))    protected static $internalModel = '{{ $class['internalModel'] }}';

@endif
@foreach ($class['constants'] as $constant => $value)
@if(is_string($value))
    const {{ $constant }} = '{{ $value }}';
@elseif(!is_array($value))
    const {{ $constant }} = {{ $value }};
@endif
@endforeach
