<?php namespace ApiClientTools\App;

use ColorTools\Exception;
use ColorTools\ImageStore;
use ColorTools\Store;
use Illuminate\Support\Facades\File as Filesystem;

class ApiThumbnail
{
    public $modifierString = null;
    public $modifiers = [];

    /**
     * Returns an image object from an array
     * @param $id
     * @return \ApiClientTools\App\ApiThumbnail
     */
    public static function buildFromArray($imageArray)
    {
        $image = new static;

        foreach($imageArray as $param=>$value) {
            $image->{$param} = $value;
        }

        $image->url = $image->getUrl('jpeg');

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
     * Gets a public image URL of an image
     * @param closure $transformations
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
        return '/thumbnail/'.$this->model.'/'.$this->modelId.$transformations.'.'.$type;
    }

    /**
     * Gets a public absolute URL of an image
     * @param null $transformations
     * @param string $type
     * @return string
     */
    public function getAbsoluteUrl($transformations = null, $type='jpeg')
    {
        return \ApiClientTools\App\Api\Base::getApiBaseUrl().$this->getRelativeUrl($transformations, $type);
    }

    /**
     * Applies modifiers to an image
     * @param Modifier closure $closure
     * @return \ColorTools\Image
     * @throws Exception
     */
    public function modifyImage($closure = null)
    {
        $closure($this);
        return $this;
    }

    /**
     * Modify and publish an image
     * @param Modifier closure $closure
     * @return string
     */
    public function modifyImagePublish($closure = null, $type='jpeg')
    {
        $closure($this);
        return $this->publish($type);
    }

    public function getModifierString()
    {
        if(empty($this->modifiers)) {
            return '';
        } else {
            return '-'.implode('-', $this->modifiers);
        }
    }

    public function fit($width, $height)
    {
        $this->modifiers[] = 's='.$width.'x'.$height;
        return $this;
    }

    /**
     * Publishes an image and returns it's path
     * @param string $type
     * @return string
     */
    public function publish($type='jpeg')
    {
        return $this->getUrl($this->getModifierString(), $type);
    }

    public function __call($name, $arguments)
    {
        // ignoring incompatible ColorTools modifires
    }
}
