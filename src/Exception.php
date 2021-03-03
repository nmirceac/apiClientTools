<?php namespace ApiClientTools;

/**
 * Class Exception
 *
 * Custom exception class for apiClientTools
 *
 * @package ApiClientTools
 */
class Exception extends \Exception
{
    /**
     * @var string
     * The raw response as received
     */
    private $rawResponse;
    /**
     * @var
     * The decoded data, if anything was sent and properly decoded
     */
    private $data;

    /**
     * Exception constructor.
     *
     * @param string $message
     * @param int $code
     * @param string $rawResponse
     * @param array $data
     */
    public function __construct(string $message, int $code, string $rawResponse, array $data=[])
    {
        $this->rawResponse = $rawResponse;
        $this->data = $data;

        if(App\Api\Base::getConfig()['ray'] and function_exists('ray')) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);

            $significantTrace = $trace[3];
            $title = App\Api\Base::getTitlePartsFromSignificantTrace('API EXCEPTION', $significantTrace);


            $rayPayload = [
                'message'=>$message,
                'clientMessage'=>$this->getClientMessage(),
                'code'=>$this->getCode(),
                'data'=>App\Api\Base::getRayData($this->getData()),
                'responseInfo'=>$this->getResponseInfo(),
                'rawResponse'=>$this->getRawResponse(),
                'trace'=>$trace,
            ];

            ray(implode(' - ', $title), $rayPayload)->red();
        }

        return parent::__construct($message, $code);
    }

    /**
     * Returns raw response
     * @return string
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    /**
     * Returns the response data
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Gets the client message - with grecefull fallback on the error message
     * @return array
     */
    public function getClientMessage()
    {
        if(isset($this->data['clientMessage'])) {
            return $this->data['clientMessage'];
        }

        return $this->getErrorMessage();
    }

    /**
     * Gets the error message as sent by the api endpoint
     * @return array
     */
    public function getErrorMessage()
    {
        if(isset($this->data['errorMessage'])) {
            return $this->data['errorMessage'];
        }

        return $this->message;
    }

    /**
     * Gets the curl response info
     * @return array
     */
    public function getResponseInfo()
    {
        if(isset($this->data['responseInfo'])) {
            return $this->data['responseInfo'];
        }
    }
}
