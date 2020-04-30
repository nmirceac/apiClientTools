<?php namespace ApiClientTools\App\Api;

class Base
{
    public static $apiBaseUrl = null;
    public static $baseNamespace = null;
    public static $pathTrimCharacters = '/ ';
    public static $caching = null;
    public static $reCaching = false;

    protected static function getCacheTimeout()
    {
        if(is_null(static::$caching)) {
            return (int) static::getConfig()['caching'];
        }

        return self::$caching;
    }

    public static function withoutCache()
    {
        self::$caching = (int) 0;
        return new static;
    }

    public static function withCache(int $timeout = null)
    {
        if(is_null($timeout)) {
            $timeout = self::getCacheTimeout();
        }
        self::$caching = (int) $timeout;
        return new static;
    }

    public static function recache()
    {
        self::$reCaching = true;
        return new static;
    }

    public static function getApiBaseUrl()
    {
        if(is_null(static::$apiBaseUrl)) {
            static::$apiBaseUrl = trim(static::getConfig()['endpoint']['baseUrl'], self::$pathTrimCharacters);
        }
        return static::$apiBaseUrl;
    }

    public static function getBaseNamespace()
    {
        if(is_null(static::$baseNamespace)) {
            static::$baseNamespace = trim(static::getConfig()['baseNamespace']);
        }
        return static::$baseNamespace;
    }

    public static function getConfig()
    {
        return config('api-client');
    }

    /**
     * Returns current page for paginator
     * @return int
     */
    public static function page()
    {
        return \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
    }

    protected static function buildUrl($endpoint, $params=[], $data=[])
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
            $values[] = rawurlencode($value);
        }

        $endpoint = str_replace($matched, $values, $endpoint);

        if(!empty($data)) {
            $data = http_build_query($data);
            if(strpos($endpoint, '?')!==false) {
                $endpoint.='&'.$data;
            } else {
                $endpoint.='?'.$data;
            }
        }

        return self::getApiBaseUrl().'/'.trim($endpoint, static::$pathTrimCharacters);
    }

    protected static function processResponse(string $json, $responseInfo = [])
    {
        if(empty($json)) {
            throw new \Exception('The request data is empty', 0);
        }

        if($responseInfo['http_code']=='500' and static::getConfig()['debug']) {
            echo $json;
            exit();
        }

        $response = json_decode($json, true);
        if(is_null($response)) {
            $errorMessage = json_last_error_msg();
            $errorCode = '10'.json_last_error();
            if(static::getConfig()['debug']) {
                echo $json;
                exit();
            } else {
                throw new \Exception('JSON parsing error: "'.$errorMessage.'" - JSON content:'.substr($json, 0, 64), $errorCode);
            }
        }

        if(!$response['success']) {
            throw new \Exception(static::class.' API request exception '.$responseInfo['http_code'].': '.$response['message'], $responseInfo['http_code']);
        }

        $data = null;
        if(!array_key_exists('data', $response)) {
            if(!isset($response['ack'])) {
                throw new \Exception('The request has no data' , 400);
            }
        } else {
            $data = $response['data'];
        }

        if($data) {
            $data = static::identifyObjects($data);
        }

        return $data;
    }

    protected static function identifyObjects($responseData)
    {
        $autoDetectColorTools = static::getConfig()['colorTools']['autoDetect'];

        if(is_array($responseData)) {
            $responseData = self::buildPaginationFromArray($responseData);

            foreach($responseData as $key=>$value)
            {
                if(!is_array($value)) {
                    continue;
                } else {
                    $responseData[$key] = self::buildPaginationFromArray($value);
                    if(!$autoDetectColorTools) {
                        continue;
                    }

                    if(isset($value['id']) and isset($value['hash']) and isset($value['type'])
                        and in_array($value['type'], ['jpeg', 'png']))
                    {
                        $responseData[$key] = \ApiClientTools\App\ApiImageStore::buildFromArray($value);
                    } else if($key=='thumbnail' and isset($value['model']) and isset($value['modelId'])) {
                        $responseData[$key] = \ApiClientTools\App\ApiThumbnail::buildFromArray($value);
                    } else {
                        $responseData[$key] = static::identifyObjects($value);
                    }
                }
            }
        }

        return $responseData;
    }

    protected static function checkIfPaginationArray($paginationArray)
    {
        if(!is_array($paginationArray)) {
            return false;
        }
        if(empty($paginationArray)) {
            return false;
        }

        if(
            isset($paginationArray['data']) and
            isset($paginationArray['total']) and
            isset($paginationArray['per_page']) and
            isset($paginationArray['current_page'])
        ) {
            return true;
        }

        return false;
    }

    protected static function buildPaginationFromArray($paginationArray, $options=array())
    {
        if(self::checkIfPaginationArray($paginationArray)) {
            $options = ['path'=>\Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()] + $options;
            return new \Illuminate\Pagination\LengthAwarePaginator(
                $paginationArray['data'],
                $paginationArray['total'],
                $paginationArray['per_page'],
                $paginationArray['current_page'],
                $options
            );
        }
        return $paginationArray;
    }

    private static function getCurlSession($url)
    {
        $session = curl_init($url);
        curl_setopt ($session, CURLOPT_POST, false);

        $requestHeader[] = 'x-api-key: '.static::getConfig()['endpoint']['secret'];
        $requestHeader[] = 'content-type: application/json';

        $sessionId = \Session::get((\Auth::guard('web')->getName()));
        if(static::getConfig()['sendAuth'] and $sessionId) {
            $requestHeader[] = 'x-auth-id: '.$sessionId;
        }

        curl_setopt($session, CURLOPT_HTTPHEADER, $requestHeader);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_FOLLOWLOCATION, true);

        return $session;
    }

    public static function getRequest($endpoint, $params=[], $data=[])
    {
        $start=microtime(true);
        $caching = static::getCacheTimeout();

        $response = null;
        if($caching)
        {
            $cacheKey = 'apiGet-'.json_encode([$endpoint, $params, $data]);
            if(!self::$reCaching) {
                $response = \Cache::get($cacheKey);
            }
        }

        if(is_null($response)) {
            $session = static::getCurlSession(self::buildUrl($endpoint, $params, $data));
            $response = curl_exec($session);
            $responseInfo = curl_getinfo($session);
            curl_close($session);

            $response = self::processResponse($response, $responseInfo);
            if($caching) {
                \Cache::put($cacheKey, $response, $caching);
            }
        }

        $endtime = microtime(true) - $start;

        return $response;
    }

    public static function postRequest($endpoint, $params=[], $data=[])
    {
        $session = static::getCurlSession(self::buildUrl($endpoint, $params));
        curl_setopt ($session, CURLOPT_POST, true);
        if(!empty($data)) {
            curl_setopt ($session, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($session);
        $responseInfo = curl_getinfo($session);
        curl_close($session);

        return self::processResponse($response, $responseInfo);
    }
}
