<?php namespace ApiClientTools\App\Api;

class Base
{
    public static $apiBaseUrl = null;
    public static $baseNamespace = null;
    public static $pathTrimCharacters = '/ ';

    public static function getApiBaseUrl()
    {
        if(is_null(static::$apiBaseUrl)) {
            static::$apiBaseUrl = trim(config('api-client.endpoint.baseUrl'), self::$pathTrimCharacters);
        }
        return static::$apiBaseUrl;
    }

    public static function getBaseNamespace()
    {
        if(is_null(static::$baseNamespace)) {
            static::$baseNamespace = trim(config('api-client.baseNamespace'));
        }
        return static::$baseNamespace;
    }

    public static function getConfig()
    {
        return config('api-client');
    }

    protected static function buildUrl($endpoint, $params=[])
    {
        $matched=[];
        $values=[];

        preg_match_all('/{(.*)}/sU', $endpoint, $matches);
        foreach($matches[0] as $no=>$match) {
            $param = $matches[1][$no];
            if(isset($params[$param])) {
                $value = $params[$param];
            } else {
                $value = $params[$no];
            }

            $matched[] = $match;
            $values[] = $value;
        }

        $endpoint = str_replace($matched, $values, $endpoint);

        return self::getApiBaseUrl().'/'.trim($endpoint, static::$pathTrimCharacters);
    }

    protected static function processResponse(string $json)
    {
        if(empty($json)) {
            throw new \Exception('The request data is empty', 0);
        }

        $response = json_decode($json, true);
        if(is_null($response)) {
            $errorMessage = json_last_error_msg();
            $errorCode = '10'.json_last_error();
            throw new \Exception('JSON parsing error: "'.$errorMessage.'" - JSON content:'.substr($json, 0, 64), $errorCode);
        }

        if(!$response['success']) {
            throw new \Exception('The request was not successful: #$response[error messages]' , 422);
        }

        if(!isset($response['data'])) {
            throw new \Exception('The request has no data' , 400);
        }


        $data = $response['data'];

        if(config('api-client.colorTools.autoDetect')) {
            $data = static::identifyImages($data);
        }

        return $data;
    }

    protected static function identifyImages($responseData)
    {
        if(is_array($responseData)) {
            foreach($responseData as $key=>$value)
            {
                if(!is_array($value)) {
                    continue;
                } else {
                    if(isset($value['id']) and isset($value['hash']) and isset($value['type'])
                        and in_array($value['type'], ['jpeg', 'png']))
                    {
                        $responseData[$key] = \ApiClientTools\App\ApiImageStore::buildFromArray($value);
                    } else if($key=='thumbnail' and isset($value['model']) and isset($value['modelId'])) {
                        $responseData[$key] = \ApiClientTools\App\ApiThumbnail::buildFromArray($value);
                    } else {
                        $responseData[$key] = static::identifyImages($value);
                    }
                }
            }
        }

        return $responseData;
    }

    public static function getRequest($endpoint, $params=[])
    {
        return self::processResponse(file_get_contents(self::buildUrl($endpoint, $params)));
    }
}
