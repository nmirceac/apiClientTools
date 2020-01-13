<?php

namespace App\Api;

class Base
{
    public static $apiEndpoint = 'https://admin-collaboration.weanswer.it/';

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

        return self::$apiEndpoint.$endpoint;
    }

    protected static function getRequest($endpoint, $params=[])
    {
        return self::processResponse(file_get_contents(self::buildUrl($endpoint, $params)));
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

        return $response['data'];
    }
}
