<?php namespace ApiClientTools\App\Api;

class Base
{
    /**
     * @var string
     */
    public static $pathTrimCharacters = '/ ';

    /**
     * @var null
     */
    public static $caching = null;

    /**
     * @var bool
     */
    public static $reCaching = false;

    /**
     * Gets the set cache timeout
     * @return int|null
     */
    protected static function getCacheTimeout()
    {
        if(is_null(static::$caching)) {
            return (int) static::getConfig()['caching'];
        }

        return self::$caching;
    }

    /**
     * Bypasses the cache for the current call
     * @return static
     */
    public static function withoutCache()
    {
        self::$caching = (int) 0;
        return new static;
    }

    /**
     * @param int|null $timeout
     * @return static
     */
    public static function withCache(int $timeout = null)
    {
        if(is_null($timeout)) {
            $timeout = self::getCacheTimeout();
        }
        self::$caching = (int) $timeout;
        return new static;
    }

    /**
     * Refreshes the cache while fetching new data
     * @return static
     */
    public static function recache()
    {
        self::$reCaching = true;
        return new static;
    }

    /**
     * Gets the base API url
     * @return string
     */
    public static function getApiBaseUrl()
    {
        return trim(static::getConfig()['endpoint']['baseUrl'], self::$pathTrimCharacters);
    }

    /**
     * Gets the published Api models namespace
     * @return string
     */
    public static function getBaseNamespace()
    {
        return trim(static::getConfig()['baseNamespace']);
    }

    /**
     * Gets the Api Client config
     * @return \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|mixed
     */
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

    /**
     * Builds the URL of a call
     * @param string $endpoint
     * @param array $params
     * @param array $data
     * @return string
     */
    protected static function buildUrl(string $endpoint, $params=[], $data=[])
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

    /**
     * Processes the response of a GET/POST request
     * @param string $json
     * @param array $responseInfo
     * @return \Illuminate\Pagination\LengthAwarePaginator|mixed|null
     * @throws \ApiClientTools\Exception
     */
    protected static function processResponse(string $json, $responseInfo = [])
    {
        if(empty($json)) {
            throw new \ApiClientTools\Exception('The response data is empty', 0, $json, ['responseInfo'=>$responseInfo]);
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
                throw new \ApiClientTools\Exception('JSON parsing error: "'.$errorMessage.'"', $errorCode, $json, ['responseInfo'=>$responseInfo]);
            }
        }

        if(!$response['success']) {
            if(isset($response['data'])) {
                $data = $response['data'];
            } else {
                $data = [];
            }
            $data['class'] = static::class;
            $data['errorMessage'] = $response['message'];
            $data['responseInfo'] = $responseInfo;

            throw new \ApiClientTools\Exception('API response exception '.$responseInfo['http_code'].': '.$response['message'], $responseInfo['http_code'], $json, $data);
        }

        $data = null;
        if(!array_key_exists('data', $response)) {
            if(!isset($response['ack'])) {
                throw new \ApiClientTools\Exception('The response has no data - please use sendAck for post methods' , 400, $json, ['responseInfo'=>$responseInfo]);
            }
        } else {
            $data = $response['data'];
        }

        if($data) {
            $data = static::identifyObjects($data);
        }

        return $data;
    }

    /**
     * Identifies in the response paginations, thumbnails, images etc
     * @param $responseData
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
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
                        if(static::getConfig()['ray_thumbnails'] and function_exists('ray')) {
                            if($key=='thumbnail') {
                                //ray($responseData[$key])->blue();
                                ray()->image($responseData[$key]->getUrl(function(\ColorTools\Image $image) { $image->fit(250, 250); }))->blue();
                            }
                        }
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

    /**
     * Checks if a response has a pagination array
     * @param $paginationArray
     * @return bool
     */
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

    /**
     * Builds a pagination array from the response of a call
     * @param array $paginationArray
     * @param array $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected static function buildPaginationFromArray(array $paginationArray, $options=array())
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

    /**
     * Prepares a curl session
     * @param $url
     * @return false|resource
     */
    private static function getCurlSession(string $url)
    {
        $session = curl_init($url);
        curl_setopt ($session, CURLOPT_POST, false);

        $requestHeader[] = 'x-api-key: '.static::getConfig()['endpoint']['secret'];
        $requestHeader[] = 'content-type: application/json';

        $sessionId = \Session::get((\Auth::guard('web')->getName()));
        if(static::getConfig()['sendAuth'] and $sessionId) {
            $requestHeader[] = 'x-auth-id: '.$sessionId;
            $impersonatorId = \Session::get(static::getConfig()['impersonator_id_session_variable']);
            if($impersonatorId) {
                $requestHeader[] = 'x-auth-impersonator-id: '.$impersonatorId;
            }
        }

        if(isset(static::getConfig()['endpoint']['ignoreSslErrors']) and static::getConfig()['endpoint']['ignoreSslErrors']) {
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, true);
        }

        curl_setopt($session, CURLOPT_HTTPHEADER, $requestHeader);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_FOLLOWLOCATION, true);

        return $session;
    }

    /**
     * Get request
     * @param string $endpoint
     * @param array $params
     * @param array $data
     * @return \Illuminate\Pagination\LengthAwarePaginator|mixed|null
     * @throws \ApiClientTools\Exception
     */
    public static function getRequest(string $endpoint, $params=[], $data=[])
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
            $url = self::buildUrl($endpoint, $params, $data);
            $session = static::getCurlSession($url);
            $response = curl_exec($session);
            $responseInfo = curl_getinfo($session);
            curl_close($session);

            $response = self::processResponse($response, $responseInfo);
            if($caching) {
                \Cache::put($cacheKey, $response, $caching);
            }
        }

        $duration = microtime(true) - $start;

        if(static::getConfig()['ray'] and function_exists('ray')) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 6);

            $significantTrace = $trace[1];
            $title = self::getTitlePartsFromSignificantTrace('API GET', $significantTrace);


            $cached = false;
            if(!isset($url)) {
                $cached = true;
                $url = self::buildUrl($endpoint, $params, $data);
            }

            $sessionId = \Session::get((\Auth::guard('web')->getName()));
            if(static::getConfig()['sendAuth'] and $sessionId) {
                $userId=$sessionId;
                $impersonatorId = \Session::get(static::getConfig()['impersonator_id_session_variable']);
            } else {
                $userId=null;
                $impersonatorId=null;
            }

            $payloadResponse = $cached ? null : $response;
            $payloadResponseInfo = $cached ? null : $responseInfo;


            if(static::getConfig()['ray_response_trim'] and isset($payloadResponseInfo['size_download']) and $payloadResponseInfo['size_download'] > static::getConfig()['ray_response_trim']) {
                $payloadResponse = substr(json_encode($payloadResponse), 0, static::getConfig()['ray_response_trim']).'...';
            }

            $rayPayload = [
                'request'=>[
                    'endpoint'=>$endpoint,
                    'params'=>$params,
                    'url'=>$url,
                    'data'=>self::getRayData($data),
                    'userId'=>$userId,
                    'impersonatorId'=>$impersonatorId,
                ],
                'trace'=>$trace,
                'cached'=>$cached,
                'duration'=>$duration,
                'response'=>$payloadResponse,
                'responseInfo'=>$payloadResponseInfo,
            ];

            ray(implode(' - ', $title), $rayPayload)->blue();
        }

        return $response;
    }

    protected static function getRayData($data)
    {
        foreach($data as $param=>$value) {
            if(is_array($value)) {
                $data[$param] = self::getRayDataArray($value);
            }
        }

        return $data;
    }

    private static function getRayDataArray($array)
    {
        $maskValuesParam = [
            'password',
            'Password',
            'urlBase64',
            'content',
        ];

        foreach($array as $param=>$value) {
            if(is_array($value)) {
                $request[$param] = self::getRayDataArray($value);
            }

            if(in_array($param, $maskValuesParam)) {
                $request[$param] = '...';
            }
        }

        return $array;
    }

    /**
     * Return titles from label and trace information
     * @param $label
     * @param $significantTrace
     * @return array
     */
    public static function getTitlePartsFromSignificantTrace($label, $significantTrace)
    {
        $title[] = strtoupper(config('app.name'));
        $title[] = $label;
        if(isset($significantTrace['class']) and isset($significantTrace['function'])) {
            $title[] = $significantTrace['class'];
            $title[] = $significantTrace['function'];

            if(isset($significantTrace['args']) and !empty($significantTrace['args'])) {
                foreach($significantTrace['args'] as $arg) {
                    if(empty($arg) or is_array($arg)) {
                        continue;
                    }
                    $title[] = $arg;
                }
            }
        }

        return $title;
    }

    /**
     * Post request
     * @param string $endpoint
     * @param array $params
     * @param array $data
     * @return \Illuminate\Pagination\LengthAwarePaginator|mixed|null
     * @throws \ApiClientTools\Exception
     */
    public static function postRequest(string $endpoint, $params=[], $data=[])
    {
        $start=microtime(true);

        $url = self::buildUrl($endpoint, $params);
        $session = static::getCurlSession($url);
        curl_setopt ($session, CURLOPT_POST, true);
        if(!empty($data)) {
            curl_setopt ($session, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($session);
        $responseInfo = curl_getinfo($session);
        curl_close($session);

        $duration = microtime(true) - $start;

        $response = self::processResponse($response, $responseInfo);

        if(static::getConfig()['ray'] and function_exists('ray')) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 6);
            $significantTrace = $trace[1];
            $title = self::getTitlePartsFromSignificantTrace('API POST', $significantTrace);

            $sessionId = \Session::get((\Auth::guard('web')->getName()));
            if(static::getConfig()['sendAuth'] and $sessionId) {
                $userId=$sessionId;
                $impersonatorId = \Session::get(static::getConfig()['impersonator_id_session_variable']);
            } else {
                $userId=null;
                $impersonatorId=null;
            }

            $payloadResponse = $response;

            if(static::getConfig()['ray_response_trim'] and isset($responseInfo['size_download']) and $responseInfo['size_download'] > static::getConfig()['ray_response_trim']) {
                $payloadResponse = substr(json_encode($payloadResponse), 0, static::getConfig()['ray_response_trim']).'...';
            }

            $rayPayload = [
                'request'=>[
                    'endpoint'=>$endpoint,
                    'params'=>$params,
                    'url'=>$url,
                    'data'=>self::getRayData($data),
                    'userId'=>$userId,
                    'impersonatorId'=>$impersonatorId,
                ],
                'trace'=>$trace,
                'duration'=>$duration,
                'response'=>$payloadResponse,
                'responseInfo'=>$responseInfo,
            ];

            ray(implode(' - ', $title), $rayPayload)->blue();
        }

        return $response;
    }

    /**
     * Get loggable representation to be sent with a Log api call
     * @param $id
     * @return array
     */
    public static function logObject($id)
    {
        $object = static::class;
        if(isset(static::$internalModel) and !empty(static::$internalModel)) {
            $object = static::$internalModel;
        }
        return [
            'object'=>$object,
            'id'=>$id,
        ];
    }
}
