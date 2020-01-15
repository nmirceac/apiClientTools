# apiClientTools
API Client Tools

## Contents
1. Intro
2. Examples

# 1. Intro

## How to install?

- composer require nmirceac/api-client-tools
- php artisan vendor:publish
- check config/api-client.php (just in case)
- add your API details to .env
- php artisan apitools:publish - to generate the models
- check the examples below
- enjoy! 

## Samples

### .env sample config

```
API_CLIENT_BASE_NAMESPACE="Api"
API_CLIENT_BASE_URL="https://admin-collaboration.weanswer.it/"
API_CLIENT_SECRET="FTPU6jA3YIqELg8XKI*****************"
API_CLIENT_COLOR_TOOLS_AUTODETECT=true
API_CLIENT_COLOR_TOOLS_PUBLIC_PATTERN="images/%hash%"
```

# 2. Examples

## ColorTools images support

Objects are scanned for \ColorTools\ImageStore objects that are hydrated to a \ApiClientTools\App\ApiImageStore object

```php
return \App\Api\Project::get(3)['thumbnail'];
// ApiClientTools\App\ApiImageStore {#284 ▼
//  +modifierString: null
//  +"id": 22
//  +"hash": "db16b0f3fe1533135914df5fa455e3c5"
//  +"name": "Collaboration Maersk 001"
//  +"type": "jpeg"
//  +"size": 48227
//  +"width": 471
//  +"height": 471
//  +"metadata": array:12 [▶]
//  +"colors": array:5 [▶]
//  +"created_at": "2019-12-12 14:37:06"
//  +"updated_at": "2019-12-12 14:37:08"
//  +"canDelete": true
//  +"orientation": "L"
//  +"basename": "Collaboration Maersk 001.jpeg"
//  +"details": array:1 [▶]
//  +"pivot": array:6 [▶]
//  +"url": "https://admin-collaboration.weanswer.it/images/db16b0f3fe1533135914df5fa455e3c5.jpeg"
//}
```

All the objects have a `url` property to their full size URL

```php
return \App\Api\Project::get(3)['thumbnail']->url;
// https://admin-collaboration.weanswer.it/images/db16b0f3fe1533135914df5fa455e3c5.jpeg
```

The objects will have modifying and publishing methods similar to a \ColorTools\ImageStore object

You can apply modifiers and then publish

```php
$thumbnail=\App\Api\Project::get(3)['thumbnail'];
$thumbnail->modifyImage(function(\ColorTools\Image $img) {
    $img->fit(100, 100);
});
return $thumbnail->publish();
// https://admin-collaboration.weanswer.it/images/db16b0f3fe1533135914df5fa455e3c5-ft=100+100.jpeg
```

The modifier is only a mutator that returns the object

```php
$thumbnail=\App\Api\Project::get(3)['thumbnail'];
return $thumbnail->modifyImage(function(\ColorTools\Image $img) {
    $img->fit(100, 100);
})->publish();
// https://admin-collaboration.weanswer.it/images/db16b0f3fe1533135914df5fa455e3c5-ft=100+100.jpeg
```

The publishing format can be overridden

```php
$thumbnail=\App\Api\Project::get(3)['thumbnail'];
return $thumbnail->modifyImage(function(\ColorTools\Image $img) {
    $img->fit(100, 100);
})->publish('png');
// https://admin-collaboration.weanswer.it/images/db16b0f3fe1533135914df5fa455e3c5-ft=100+100.png
```

You can also modify and publish in one go

```php
$thumbnail=\App\Api\Project::get(3)['thumbnail'];
$thumbnail->modifyImagePublish(function(\ColorTools\Image $img) {
    $img->fit(100, 100);
});
// https://admin-collaboration.weanswer.it/images/db16b0f3fe1533135914df5fa455e3c5-ft=100+100.png
```

And do that while specifying the publishing format

```php
$thumbnail=\App\Api\Project::get(3)['thumbnail'];
$thumbnail->modifyImagePublish(function(\ColorTools\Image $img) {
    $img->fit(100, 100);
}, 'png');
// https://admin-collaboration.weanswer.it/images/db16b0f3fe1533135914df5fa455e3c5-ft=100+100.png
``` 

## Thumbnails support

Non image thumbnails are also supported through a similar syntax.
Just make sure you remove the typehint as the passed object to the close might not be an image. 

```php
$thumbnail=\App\Api\Project::get(2)['thumbnail'];
$thumbnail->modifyImagePublish(function($img) {
    $img->fit(100, 100);
});
// https://admin-collaboration.weanswer.it/thumbnail/project/2-s=100x100.jpeg
``` 

or

```php
$thumbnail=\App\Api\Project::get(2)['thumbnail'];
$thumbnail->modifyImagePublish(function($img) {
    $img->fit(400, 270);
}, 'png');
// https://admin-collaboration.weanswer.it/thumbnail/project/2-s=400x270.png
``` 

Just note that only the `fit` method is supported at the moment - all the other methods are gracefully ignored

```php
$thumbnail=\App\Api\Project::get(2)['thumbnail'];
$thumbnail->modifyImagePublish(function($img) {
    $img->fit(400, 270);
    $img->applyFilter(\ColorTools\Image::FILTER_GRAYSCALE);
});
// https://admin-collaboration.weanswer.it/thumbnail/project/2-s=400x270.jpeg

$thumbnail=\App\Api\Project::get(4)['thumbnail'];
$thumbnail->modifyImagePublish(function($img) {
    $img->fit(400, 270);
    $img->applyFilter(\ColorTools\Image::FILTER_GRAYSCALE);
});
// https://admin-collaboration.weanswer.it/images/45771d1cdfe109aa6e5b3fd48c925ebd-fi=1+-ft=400+270.jpeg
```


