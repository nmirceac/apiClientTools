<?php namespace ApiClientTools\App;

use ColorTools\Exception;
use ColorTools\ImageStore;
use ColorTools\Store;
use Illuminate\Support\Facades\File as Filesystem;

class ApiImageStore
{
    public $modifierString = null;

    /**
     * Returns an image object from an array
     * @param $id
     * @return \ApiClientTools\App\ApiImageStore
     */
    public static function buildFromArray($imageArray)
    {
        $image = new static;

        foreach($imageArray as $param=>$value) {
            $image->{$param} = $value;
        }

        $image->url = $image->getUrl(null, $image->type);

        return $image;
    }

    /**
     * Gets the stored content of an image
     * @return mixed
     */
    public function getContent()
    {
        return file_get_contents($this->url);
    }

    /**
     * Serves an image (inline)
     * @return mixed
     */
    public function serve()
    {
        return response($this->getContent())
            ->header('Content-Type', 'image/'.$this->type)
            ->header('Content-Description', $this->name)
            ->header('Content-Disposition', 'inline; filename="'.$this->basename.'"');
    }

    /**
     * Serves an image (download)
     * @return mixed
     */
    public function serveForceDownload()
    {
        return response($this->getContent())
            ->header('Content-Type', 'image/'.$this->type)
            ->header('Content-Description', $this->name)
            ->header('Content-Disposition', 'attachment; filename="'.$this->basename.'"');
    }


    /**
     * Gets a public image URL of an image
     * @param closure $transformations
     * @return \ColorTools\Image
     * @throws Exception
     */
    public function getUrl($transformations = null, $type='jpeg')
    {
        return $this->getAbsoluteUrl($transformations, $type);
    }

    /**
     * Gets a public relative URL of an image
     * @param null $transformations
     * @param string $type
     * @return string
     */
    public function getRelativeUrl($transformations = null, $type='jpeg')
    {
        return str_replace([
            '%hash_prefix%',
            '%hash%'
        ], [
            substr($this->hash, 0, 2),
            \ColorTools\Store::getHashAndTransformations($this->hash, $transformations, $type)
        ], config('api-client.colorTools.publicPattern'));
    }

    /**
     * Gets a public absolute URL of an image
     * @param null $transformations
     * @param string $type
     * @return string
     */
    public function getAbsoluteUrl($transformations = null, $type='jpeg')
    {
        return \ApiClientTools\App\Api\Base::getApiBaseUrl().'/'.$this->getRelativeUrl($transformations, $type);
    }


    /**
     * Applies modifiers to an image
     * @param Modifier closure $closure
     * @return \ColorTools\Image
     * @throws Exception
     */
    public function modifyImage($closure = null)
    {
        $this->modifierString = \ColorTools\Store::convertTransformationsToModifierString($closure);
        return $this;
    }

    /**
     * Modify and publish an image
     * @param Modifier closure $closure
     * @return string
     */
    public function modifyImagePublish($closure = null, $type='jpeg')
    {
        $this->modifierString = \ColorTools\Store::convertTransformationsToModifierString($closure);
        return $this->publish($type);
    }

    /**
     * Publishes an image and returns it's path
     * @param string $type
     * @return string
     */
    public function publish($type='jpeg')
    {
        return $this->getUrl($this->modifierString, $type);
    }
}
