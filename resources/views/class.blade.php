@include('api-client::class.header')
@foreach($class['methods'] as $method)

    @include('api-client::class.method', ['method'=>$method])
@endforeach
@include('api-client::class.footer')
