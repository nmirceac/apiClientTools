namespace App\{{ \ApiClientTools\App\Api\Base::getBaseNamespace() }};

/**
 * Class {{ $class['name'] }}
 * {{ $class['description'] }}
 * {{ '@' }}apiHash {{ md5(json_encode($class)) }}
 * {{ '@' }}package App\{{ \ApiClientTools\App\Api\Base::getBaseNamespace() }}\{{ $class['name'] }}
 */
class {{ $class['name'] }} extends Base
{

@foreach ($class['constants'] as $constant => $value)
@if(is_string($value))
    const {{ $constant }} = '{{ $value }}';
@else
    const {{ $constant }} = {{ $value }};
@endif
@endforeach
