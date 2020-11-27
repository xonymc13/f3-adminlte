<?php

/**
 * restServerClass -
 * NOTE1:    This class implements the restServerClass class to support the api call.
 *            You can include that as a import instead
 * NOTE2:    This requires sshpass to call the remote enum server unless you turn on apache.
 * NOTE 3:  Add config to global config file..
 *
 * @author      Chad Rosenbohm, 319-431-1901, chad@fuzemobi.com
 * @copyright   Copyright (c) 2018-2019 dlcc llc.
 *
 * @date        09-06-2019
 * @file       restServerClass.php
 *
 * @version     2.5.4
 *.
 * https://www.revolvy.com/main/index.php?s=NAPTR%20record&item_type=topic
 * https://tools.ietf.org/html/rfc3824
 * https://en.wikipedia.org/wiki/Telephone_number_mapping
 */

date_default_timezone_set('America/Chicago');

ini_set('soap.wsdl_cache_enabled', 0);
ini_set('soap.wsdl_cache_ttl', 900);
ini_set('default_socket_timeout', 15);
ini_set('max_execution_time', 90);

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {

    //ignore the headers for the remote_calls so that we can send headers from those actions (overwatch)
    if (!isset($_GET['remote_call'])) {
        // clear the old headers and expire history
        header_remove();
        //header("HTTP/1.1 400 Bad Request");
        if (isset($_GET['debug']) && $_GET['debug'] > 0)
            header("Content-Type: text/html");
        else
            header("Content-Type: application/json");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // date in  past
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");  //date now

        //cors
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Expose-Headers: Content-Length, X-JSON");
        header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: *");

        //  CACHE (30 seconds)
        //if we don't want to expire the content
        /*
        $seconds_to_cache = 10; //TODO: set a global veriable for apiExpires
        $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
        header("Expires: $ts");
        header("Pragma: cache");
        header("Cache-Control: max-age=$seconds_to_cache");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        */
    }
} catch (Exception $ex) {
    //ignore the header issue.
}

//declare the restServer classes locally
final class restServerClass
{

    private $serviceClasses = array();

    public function addServiceClass($serviceClass)
    {
        $this->serviceClasses[] = $serviceClass;
    }

    public function handle()
    {
        try {

            //get the params
            $requestAttributes = $this->getRequestAttributeArray();

            if ($this->methodIsDefinedInRequest()) {
                $method = $requestAttributes["method"];
                $serviceClass = $this->getClassContainingMethod($method);

                if ($serviceClass != null) {
                    $ref = new ReflectionMethod($serviceClass, $method);
                    if (!$ref->isPublic()) {
                        //echo json_encode(array('error' => 'API call is invalid.'));
                        $msg = 'API call is invalid.';
                        return self::jsonErrorMessage("0x10FF", 'System Error', $msg);
                    }

                    $params = $ref->getParameters();
                    $paramCount = count($params);
                    $pArray = array();
                    $paramStr = "";
                    $iterator = 0;
                    foreach ($params as $param) {
                        $pArray[strtolower($param->getName())] = null;
                        $paramStr .= $param->getName();
                        if ($iterator != $paramCount - 1) {
                            $paramStr .= ", ";
                        }
                        $iterator++;
                    }

                    foreach ($pArray as $key => $val) {
                        $pArray[strtolower($key)] = @$requestAttributes[strtolower($key)];
                    }

                    //for now, ignore null parameters.
                    //if (count($pArray) == $paramCount && !in_array(null, $pArray)) {
                    $result = call_user_func_array(array($serviceClass, $method), $pArray);

                    //get the version
                    $version = call_user_func_array(array($serviceClass, 'getVersion'), $pArray);
                    $params = str_replace("method=$method&", '', $_SERVER['QUERY_STRING']);

                    if ($result != null && !(bool)stripos(serialize($result), 'error')) {
                        $params = str_replace("method=$method&", '', $_SERVER['QUERY_STRING']);
                        self::formatResponse($result, 200, $method, $version, $params);

                    } else {
                        //what shall we do about an error response?
                        if ((bool)stripos(serialize($result), 'no data')) {
                            return self::formatResponse($result, 200, $method, $version, $params);
                        } elseif (empty($result)) {
                            $msg = "NULL Response from method [$method]";
                            return self::formatResponse($msg, 200, $method, $version, $params);
                        } elseif (is_array($result)) {
                            //var_dump($result);die();
                            //$msg = "response_message = " . self::formatErrorResponse($result);
                            return self::formatResponse($result, 200, $method, $version, $params);
                        } else {
                            $msg = $result;
                        }
                        return self::jsonErrorMessage("0x00FF", 'System Error', $msg);
                    }

                } else {
                    $msg = "The method [$method] does not exist.";
                    return self::jsonErrorMessage("1x00FF", 'System Error', $msg);
                }

            } else {
                //echo json_encode(array('error' => 'No method was requested.'));
                $msg = "No method was requested.";
                return self::jsonErrorMessage("2x00FF", 'System Error', $msg);
            }

        } catch (Exception $ex) {
            self::jsonErrorMessage("3x00FF", 'System Error - ' . $ex->getMessage(), $ex->getMessage(), 'Unknown', $method, 0, $ex->getTraceAsString());
        }
    }

    private function getClassContainingMethod($method)
    {
        $serviceClass = null;
        foreach ($this->serviceClasses as $class) {
            if ($this->methodExistsInClass($method, $class)) {
                $serviceClass = $class;
            }
        }
        return $serviceClass;
    }

    private function methodExistsInClass($method, $class)
    {
        return method_exists($class, $method);
    }

    private function methodIsDefinedInRequest()
    {
        return array_key_exists("method", $this->getRequestAttributeArray());
    }

    private function getRequestAttributeArray()
    {
        return array_change_key_case($_REQUEST, CASE_LOWER);
    }

    public static function formatErrorResponse($errorMsg = null)
    {
        try {
            $json = json_encode($errorMsg);//, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (Exception $ex) {
            $json = $ex->getMessage();
        }
        return $json;
    }

    public static function formatResponse($message = null, $code = 200, $method = 'Unknown', $version = 'Unknown', $params = '')
    {

        try {
            /*
            if (@in_array("Bad Request", $message)) {
                $code = 400;
            } elseif (@in_array("Unprocessable Entity", $message)) {
                $code = 422;
            }
            */

            /*
            //force 200 ressponse
            $code = 200;
            $status = array(
                200 => '200 OK',
                400 => '400 Bad Request',
                422 => 'Unprocessable Entity',
                500 => '500 Internal Server Error'
            );
            // ok, validation error, or failure
            header('status: ' . $status[$code]);

            echo($code);
            */

            // set the http response code
            //if (version_compare(PHP_VERSION, '5.5.0') > 0) {
            //http_response_code($code);
            //}

            @header("HTTP/1.1 200 OK", true, 200);

            //PHP < 5.4
            // json_encode() options
            if (!defined('JSON_HEX_TAG')) define('JSON_HEX_TAG', 1);    // Since PHP 5.3.0
            if (!defined('JSON_HEX_AMP')) define('JSON_HEX_AMP', 2);    // Since PHP 5.3.0
            if (!defined('JSON_HEX_APOS')) define('JSON_HEX_APOS', 4);    // Since PHP 5.3.0
            if (!defined('JSON_HEX_QUOT')) define('JSON_HEX_QUOT', 8);    // Since PHP 5.3.0
            if (!defined('JSON_FORCE_OBJECT')) define('JSON_FORCE_OBJECT', 16);   // Since PHP 5.3.0
            if (!defined('JSON_NUMERIC_CHECK')) define('JSON_NUMERIC_CHECK', 32);   // Since PHP 5.3.3
            if (!defined('JSON_UNESCAPED_SLASHES')) define('JSON_UNESCAPED_SLASHES', 64);   // Since PHP 5.4.0
            if (!defined('JSON_PRETTY_PRINT')) define('JSON_PRETTY_PRINT', 128);  // Since PHP 5.4.0
            if (!defined('JSON_UNESCAPED_UNICODE')) define('JSON_UNESCAPED_UNICODE', 256);  // Since PHP 5.4.0
            // json_decode() options
            if (!defined('JSON_OBJECT_AS_ARRAY')) define('JSON_OBJECT_AS_ARRAY', 1);    // Since PHP 5.4.0
            if (!defined('JSON_BIGINT_AS_STRING')) define('JSON_BIGINT_AS_STRING', 2);    // Since PHP 5.4.0
            if (!defined('JSON_PARSE_JAVASCRIPT')) define('JSON_PARSE_JAVASCRIPT', 4);    // upgrade.php
            // json_last_error() error codes
            if (!defined('JSON_ERROR_NONE')) define('JSON_ERROR_NONE', 0);    // Since PHP 5.3.0
            if (!defined('JSON_ERROR_DEPTH')) define('JSON_ERROR_DEPTH', 1);    // Since PHP 5.3.0
            if (!defined('JSON_ERROR_STATE_MISMATCH')) define('JSON_ERROR_STATE_MISMATCH', 2);    // Since PHP 5.3.0
            if (!defined('JSON_ERROR_CTRL_CHAR')) define('JSON_ERROR_CTRL_CHAR', 3);    // Since PHP 5.3.0
            if (!defined('JSON_ERROR_SYNTAX')) define('JSON_ERROR_SYNTAX', 4);    // Since PHP 5.3.0
            if (!defined('JSON_ERROR_UTF8')) define('JSON_ERROR_UTF8', 5);    // Since PHP 5.3.3
            if (!defined('JSON_ERROR_RECURSION')) define('JSON_ERROR_RECURSION', 6);    // Since PHP 5.5.0
            if (!defined('JSON_ERROR_INF_OR_NAN')) define('JSON_ERROR_INF_OR_NAN', 7);    // Since PHP 5.5.0
            if (!defined('JSON_ERROR_UNSUPPORTED_TYPE')) define('JSON_ERROR_UNSUPPORTED_TYPE', 8);    // Since PHP 5.5.0

            // return the json_encoded array
            $arr = array(
                'status' => $code < 300, // success or not?
                'message' => $message,
                "method" => $method,
                "params" => $params,
                "version" => self::getApiVersion(),
                "timestamp" => date('Y-m-d H:i:s'));

            if (isset($_GET['debug']) && $_GET['debug'] > 0)  $arr['debug'] = loggerClass::getDebugLog();

            $json = json_encode($arr,
             JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        } catch (Exception $ex) {
            $json = array(
                'status' => false,
                'message' => 'Error:' . $ex->getMessage(),
                'failure_code' => $ex->getCode(),
                'failure_description' => $ex->getTraceAsString(),
                'method' => 'jsonErrorMessage',
                'version' => self::getApiVersion(),
                'timestamp' => date('Y-m-d H:i:s'),
            );
            header("HTTP/1.1 200 OK", true, 200);
            $json = json_encode($json);
        }

        echo $json;
        return;

    }

    public static function getApiVersion(){
        return '1.0';
    }

    public static function jsonErrorMessage($code = "0x20FF", $message = null, $detail = null, $version = null, $method = null, $debug = 1, $trace = null)
    {

        try {

            //set method
            if (empty($method)) {
                if (!empty($_GET['method'])) {
                    $method = $_GET['method'];
                } else {
                    $method = "Method Not Set";
                }
            }

            //set version
            if (empty($version)) {
                if (!empty($_GET['version'])) {
                    $version = $_GET['version'];
                } else {
                    $version = "1.0";
                }
            }


            if ($code === "0x00FE" || $code === "0x30FF" || $debug > 0) {
                $message .= " - " . $detail;
            }

            $response = array(
                'status' => false,
                'message' => $message,
                'failure_code' => $code,
                'failure_description' => $trace,
                'method' => $method,
                'version' => $version,
                'timestamp' => date('Y-m-d H:i:s'),
            );

            //we need to remove any JSON encoding from the message.
            $response = str_replace(PHP_EOL, '', $response);
            $response = str_replace('\"', '"', $response);
            $response = str_replace('"', '', $response);
            $response = str_replace(',', ', ', $response);
            $response = str_replace('{', '', $response);
            $response = str_replace('}', '', $response);
            //$json = json_encode($response,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
            $json = self::json_encode_noescape_slashes_unicode($response);

        } catch (Exception $ex) {
            $json = array(
                'status' => false,
                'message' => $ex->getMessage(),
                'failure_code' => $ex->getCode(),
                'failure_description' => $ex->getTraceAsString(),
                'method' => 'jsonErrorMessage',
                'version' => self::getApiVersion(),
                'timestamp' => date('Y-m-d H:i:s'),
            );
            $json = json_encode($json);
        }
        echo $json;
        return;

    }

    public static function jsonSuccessMessage($response, $version = null, $method = null)
    {
        try {

            //set method
            if (empty($method)) {
                if (!empty($_GET['method'])) {
                    $method = $_GET['method'];
                } else {
                    $method = "Testing Not Set";
                }
            }
            //set version
            if (empty($version)) {
                if (!empty($_GET['version'])) {
                    $version = $_GET['version'];
                } else {
                    $version = "Version Not Set";
                }
            }

            //set response
            $response['Method'] = $method;
            $response['Version'] = $version;
            $response['Date Executed'] = date('Y-m-d H:i:s');

            //$json = json_encode($response);//,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
            $json = self::json_encode_noescape_slashes_unicode($response);

        } catch (Exception $ex) {
            $json = array(
                'Status' => false,
                'Message' => array(
                    'Error' => $ex->getMessage()
                ),
                'Failure Code' => $ex->getCode(),
                'Failure Description' => $ex->getTraceAsString(),
                'Method' => 'jsonErrorMessage',
                'Version' => 'Unknown',
                'Date Executed' => date('Y-m-d H:i:s'),
            );
            $json = json_encode($json);
        }

        echo $json;
        return;

    }

    public static function json_encode_noescape_slashes_unicode($arr)
    {
        //   array_walk_recursive($arr, function (&$item, $key) {
        //       if (is_string($item)) {
        //           $item = mb_encode_numericentity($item, array(0x80, 0xffff, 0, 0xffff), 'UTF-8');
        //       }
        //   });
        //   $str = mb_decode_numericentity(json_encode($arr), array(0x80, 0xffff, 0, 0xffff), 'UTF-8');

        $str = json_encode($arr);//,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        $str = str_replace('\/', '/', $str);
        return $str;
    }

}

if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL)
    {

        if ($code !== NULL) {

            switch ($code) {
                case 100:
                    $text = 'Continue';
                    break;
                case 101:
                    $text = 'Switching Protocols';
                    break;
                case 200:
                    $text = 'OK';
                    break;
                case 201:
                    $text = 'Created';
                    break;
                case 202:
                    $text = 'Accepted';
                    break;
                case 203:
                    $text = 'Non-Authoritative Information';
                    break;
                case 204:
                    $text = 'No Content';
                    break;
                case 205:
                    $text = 'Reset Content';
                    break;
                case 206:
                    $text = 'Partial Content';
                    break;
                case 300:
                    $text = 'Multiple Choices';
                    break;
                case 301:
                    $text = 'Moved Permanently';
                    break;
                case 302:
                    $text = 'Moved Temporarily';
                    break;
                case 303:
                    $text = 'See Other';
                    break;
                case 304:
                    $text = 'Not Modified';
                    break;
                case 305:
                    $text = 'Use Proxy';
                    break;
                case 400:
                    $text = 'Bad Request';
                    break;
                case 401:
                    $text = 'Unauthorized';
                    break;
                case 402:
                    $text = 'Payment Required';
                    break;
                case 403:
                    $text = 'Forbidden';
                    break;
                case 404:
                    $text = 'Not Found';
                    break;
                case 405:
                    $text = 'Method Not Allowed';
                    break;
                case 406:
                    $text = 'Not Acceptable';
                    break;
                case 407:
                    $text = 'Proxy Authentication Required';
                    break;
                case 408:
                    $text = 'Request Time-out';
                    break;
                case 409:
                    $text = 'Conflict';
                    break;
                case 410:
                    $text = 'Gone';
                    break;
                case 411:
                    $text = 'Length Required';
                    break;
                case 412:
                    $text = 'Precondition Failed';
                    break;
                case 413:
                    $text = 'Request Entity Too Large';
                    break;
                case 414:
                    $text = 'Request-URI Too Large';
                    break;
                case 415:
                    $text = 'Unsupported Media Type';
                    break;
                case 500:
                    $text = 'Internal Server Error';
                    break;
                case 501:
                    $text = 'Not Implemented';
                    break;
                case 502:
                    $text = 'Bad Gateway';
                    break;
                case 503:
                    $text = 'Service Unavailable';
                    break;
                case 504:
                    $text = 'Gateway Time-out';
                    break;
                case 505:
                    $text = 'HTTP Version not supported';
                    break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            header($protocol . ' ' . $code . ' ' . $text);

            $GLOBALS['http_response_code'] = $code;

        } else {

            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);

        }

        return $code;

    }
}


//error_reporting(0);
//set_error_handler('globalExceptionHandler', E_ALL & ~E_NOTICE | E_USER_ERROR | E_USER_NOTICE | E_USER_DEPRECATED | E_WARNING);
//make this a global function
function globalExceptionHandler($code = "0x40FF", $message = null, $detail = null, $version = null, $method = null, $debug = 1)
{

    try {

        //set method
        if (empty($method)) {
            if (!empty($_GET['method'])) {
                $method = $_GET['method'];
            } else {
                $method = "Method Not Set";
            }
        }
        //set version
        if (empty($version)) {
            if (!empty($_GET['version'])) {
                $version = $_GET['version'];
            } else {
                $version = "Version Not Set";
            }
        }
        if ($code === "0x00FE" || $code === "0x50FF" || $debug > 0) {
            $message .= " - " . $detail;
        }
        $response = array(
            'Status' => false,
            'Message' => array(
                'Error' => $message,
            ),
            'Failure Code' => $code,
            'Failure Description' => $message,
            'Method' => $method,
            'Version' => $version,
            'Date Executed' => date('Y-m-d H:i:s'),
        );
        $json = json_encode($response);//,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        //$json = self::json_encode_noescape_slashes_unicode($response);

    } catch (Exception $ex) {
        $json = array(
            'Status' => false,
            'Message' => array(
                'Error' => $ex->getMessage()
            ),
            'Failure Code' => $ex->getCode(),
            'Failure Trace' => $ex->getTraceAsString(),
            'Method' => 'jsonErrorMessage',
            'Version' => 'Unknown',
            'Date Executed' => date('Y-m-d H:i:s'),
        );
        $json = json_encode($json);
    }

    echo $json;

}
