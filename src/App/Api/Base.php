<?php namespace ApiClientTools\App\Api;

use Illuminate\Support\Facades\File as Filesystem;

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
    public static $temporaryCaching = null;

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
        if(!is_null(static::$temporaryCaching)) {
            $temporaryCacheTimeout = static::$temporaryCaching;
            static::$temporaryCaching = null;
            return $temporaryCacheTimeout;
        }

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
        self::$temporaryCaching = (int) 0;
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
        self::$temporaryCaching = (int) $timeout;
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
        if(empty($responseInfo['primary_ip'])) {
            throw new \ApiClientTools\Exception('Unable to resolve the requested endpoint\'s IP address: '.static::getConfig()['endpoint']['baseUrl'], 0, $json, ['responseInfo'=>$responseInfo]);
        }

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

    public static function getLocaleValue($default = 'en')
    {
        $locale = request('locale');

        if(empty($locale)) {
            $locale = \Session::get('locale');
        }

        if(empty($locale)) {
            $locale = \Cookie::get('locale');
        }

        if(empty($locale)) {
            $locale = app()->getLocale();
        }

        if(empty($locale)) {
            $locale = $default;
        }

        return $locale;
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

        if(static::getConfig()['sendLocale']) {
            $requestHeader[] = 'x-locale: '.static::getLocaleValue();
        }

        if(static::getConfig()['identifier']) {
            $requestHeader[] = 'x-identifier: '.static::getConfig()['identifier'];
        }

        /*
         * When CURLOPT_SSL_VERIFYPEER is enabled, and the verification fails to prove that the certificate is authentic, the connection fails.
         * When the option is zero, the peer certificate verification succeeds regardless.
         *
         * Authenticating the certificate is not enough to be sure about the server.
         * You typically also want to ensure that the server is the server you mean to be talking to.
         * Use CURLOPT_SSL_VERIFYHOST for that.
         * The check that the host name in the certificate is valid for the host name you're connecting to is done independently of the CURLOPT_SSL_VERIFYPEER option.
         */

        if(isset(static::getConfig()['endpoint']['ignoreSslErrors']) and static::getConfig()['endpoint']['ignoreSslErrors']) {
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        } else if(isset(static::getConfig()['endpoint']['ignoreSslHost']) and static::getConfig()['endpoint']['ignoreSslHost']) {
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
        $cacheKey = 'apiGet-'.json_encode([$endpoint, $params, $data]);
        if(!self::$reCaching) {
            $response = \Cache::get($cacheKey);
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

    public static function getRayData($data)
    {
        foreach($data as $param=>$value) {
            if(is_array($value)) {
                $data[$param] = self::getRayDataArray($value);
            }
        }

        return $data;
    }

    public static function getRayDataArray($array)
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

    private static function checkMime($mime)
    {
        $mime = trim(strtolower($mime));
        if($mime == 'image/svg') {
            $mime = 'image/svg+xml';
        }

        return $mime;
    }

    /**
     * Creates a file
     * @param array $metadata
     * @param string|null $contents
     * @return \FileTools\File
     * @throws \Exception
     */
    public static function createFilePayload(array $metadata, string $contents = null, $isUrl = false)
    {
        if($isUrl) {
            $validator = \Validator::make($metadata, [
                'name' => 'required|string',
                'extension' => 'required|string',
            ]);
        } else {
            $validator = \Validator::make($metadata, [
                'name' => 'required|string',
                'mime' => 'required|string',
                'extension' => 'required|string',
                'size' => 'required|numeric'
            ]);
        }


        if ($validator->fails()) {
            throw new \Exception($validator->errors());
        }

        if (empty($contents)) {
            throw new \Exception('Contents is empty');
        }

        $payload = $metadata;

        if($isUrl) {
            // check if urlBase64 or just a url for download
            if(substr(strtolower($contents), 0, 5)=='data:') {
                $payload['urlBase64'] = $contents;
            } else {
                $payload['url'] = $contents;
            }
        } else {
            $payload['contents'] = base64_encode($contents);
        }


        return $payload;
    }

    /**
     * Creates the file payload from path
     * @param string $filePath
     * @return \FileTools\File
     * @throws \Exception
     */
    public static function createFileFromPath(string $filePath, string $role = 'files', int $order = 0)
    {
        if (!file_exists(($filePath))) {
            throw new \Exception('File not found at path ' . $filePath . ' (' . base_path($filePath) . ')');
        }

        if (is_dir(($filePath))) {
            throw new \Exception('The path ' . $filePath . ' resolves to a directory, not to a file');
        }

        $metadata['mime'] = Filesystem::mimeType($filePath);
        $metadata['name'] = Filesystem::name($filePath);
        $metadata['dirname'] = Filesystem::dirname($filePath);
        $metadata['basename'] = Filesystem::basename($filePath);
        $metadata['extension'] = Filesystem::extension($filePath);
        $metadata['size'] = Filesystem::size($filePath);
        $metadata['lastModified'] = date('Y-m-d H:i:s', Filesystem::lastModified($filePath));
        $metadata['originalPath'] = $filePath;
        $metadata['role'] = $role;
        $metadata['order'] = $order;

        return self::createFilePayload($metadata, Filesystem::get($filePath));
    }

    /**
     * Create the file payload from a laravel request
     * @param \Illuminate\Http\Request $request
     * @param string $fileKey
     * @return \FileTools\File|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public static function createFileFromRequest(string $fileKey = 'file', string $role = 'files', int $order = 0)
    {
        if (!request()->hasFile($fileKey)) {
            throw new \Exception('Missing file');
        }

        $fileInfo = request()->file($fileKey);

        $metadata['mime'] = $fileInfo->getMimeType();
        $metadata['name'] = $fileInfo->getClientOriginalName();
        $metadata['basename'] = $fileInfo->getClientOriginalName();
        $metadata['extension'] = $fileInfo->getClientOriginalExtension();
        $metadata['size'] = $fileInfo->getSize();
        $metadata['originalPath'] = $fileInfo->getRealPath();
        $metadata['hash'] = md5_file($metadata['originalPath']);

        if (!empty($metadata['extension'])) {
            $metadata['name'] = substr($metadata['name'], 0, -(1 + strlen($metadata['extension'])));
        }

        $metadata['role'] = $role;
        $metadata['order'] = $order;

        $contents = file_get_contents($fileInfo->getRealPath());

        return self::createFilePayload($metadata, $contents);
    }

    public static function createFileFromXhr(string $fileKey = 'file', string $role = 'files', int $order = 0)
    {
        $fileInfo = request($fileKey);

        if (empty($fileKey)) {
            throw new \Exception('Missing file');
        }

        if (!isset($fileInfo['urlBase64'])) {
            throw new \Exception('Missing file content');
        }

        if (empty($fileInfo['urlBase64'])) {
            throw new \Exception('Empty file content');
        }

        if (!isset($fileInfo['name'])) {
            throw new \Exception('Missing file name');
        }

        if (empty($fileInfo['name'])) {
            throw new \Exception('Empty file name');
        }

        $metadata['name'] = $fileInfo['name'];
        $metadata['basename'] = $metadata['name'];

        $name = explode('.', $metadata['name']);
        if(count($name)>=2) {
            $metadata['extension'] = array_pop($name);
            $metadata['name'] = implode('.', $name);
        } else {
            $metadata['extension'] = '';
        }

        $metadata['role'] = $role;
        $metadata['order'] = $order;

        $contents = $fileInfo['urlBase64'];

        return self::createFilePayload($metadata, $contents, true);
    }

    public static function createFileFromUrl(string $url = 'file', ?string $name = null, string $role = 'files', int $order = 0)
    {
        if (empty($url)) {
            throw new \Exception('Missing url');
        }

        if(empty($name)) {
            $name = substr($url, 1 + strrpos($url, '/'));
        }

        $metadata['url'] = $url;
        $metadata['name'] = $name;

        $name = explode('.', $metadata['name']);
        if(count($name)>=2) {
            $metadata['extension'] = array_pop($name);
            $metadata['name'] = implode('.', $name);
        } else {
            $metadata['extension'] = '';
        }

        $metadata['role'] = $role;
        $metadata['order'] = $order;

        return self::createFilePayload($metadata, $url, true);
    }
}
