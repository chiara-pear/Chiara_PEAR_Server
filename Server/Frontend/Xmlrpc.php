<?php
require_once 'Chiara/PEAR/Server/Frontend.php';
function Chiara_PEAR_Server_Frontend_Xmlrpc_handleMethod($method_name, $params, $channel)
{
    return Chiara_PEAR_Server_Frontend_Xmlrpc::singleton($channel)->parseInput(array($method_name, $params));
}

class Chiara_PEAR_Server_Frontend_Xmlrpc extends Chiara_PEAR_Server_Frontend
{
    protected $input;
    protected $output;
    protected $server;
    protected $signatures =
        array(
            'logintest' =>
                array(
                    'sig' =>
                        array(
                            '' => 1,
                        ),
                    'version' => '1.0',
                ),
            'package.listAll' =>
                array(
                    'sig' =>
                        array(
                            '' => 1,
                            'boolean' => 2,
                            'boolean,boolean' => 3,
                            'boolean,boolean,boolean' => 4
                        ),
                    'version' => '1.0',
                ),
            'package.search' =>
                array(
                    'sig' =>
                        array(
                            'string' => 1,
                            'string,string' => 2,
                            'string,boolean' => 3,
                            'string,string,boolean' => 4,
                            'string,boolean,boolean' => 5,
                            'string,string,boolean,boolean' => 6,
                            'string,boolean,boolean,boolean' => 7,
                            'string,string,boolean,boolean,boolean' => 8,
                            'string,boolean,boolean,boolean,boolean' => 9,
                        ),
                    'version' => '1.0',
                ),
            'package.listLatestReleases' =>
                array(
                    'sig' =>
                        array(
                            '' => 1,
                            'string' => 2
                        ),
                    'version' => '1.0',
                ),
            'package.info' =>
                array(
                    'sig' =>
                        array(
                            'string' => 1,
                            'int' => 2,
                            'string,string' => 3,
                            'int,string' => 4,
                            'string,string,boolean' => 5,
                            'int,string,boolean' => 6,
                        ),
                    'version' => '1.0',
                ),
            'package.getDownloadURL' =>
                array(
                    'sig' =>
                        array(
                            'struct,string' => 1,
                            'struct,string,string' => 2,
                        ),
                    'version' => '1.0',
                ),
            'package.getDepDownloadURL' =>
                array(
                    'sig' =>
                        array(
                            'string,struct,struct' => 1,
                            'string,struct,struct,string' => 2,
                            'string,struct,struct,string,string' => 3,
                        ),
                    'version' => '1.0',
                ),
            'channel.listAll' =>
                array(
                    'sig' =>
                        array(
                            '' => 1,
                        ),
                    'version' => '1.0',
                ),
        );
    private $_dontappend = true;
    private static $singleton = array();
    protected function __construct($channel)
    {
        parent::__construct($channel);
        if (!extension_loaded('xmlrpc')) {
            throw new Chiara_PEAR_Server_ExceptionNoXmlrpc;
        }
        $this->server = xmlrpc_server_create();
        foreach ($this->signatures as $method => $signatures) {
            xmlrpc_server_register_method($this->server, $method, 'Chiara_PEAR_Server_Frontend_Xmlrpc_handleMethod');
        }
    }

    public static function singleton($channel)
    {
        if (isset(self::$singleton[$channel])) {
            return self::$singleton[$channel];
        }
        self::$singleton[$channel] = new Chiara_PEAR_Server_Frontend_Xmlrpc($channel);
        return self::$singleton[$channel];
    }

    public function main()
    {
        if (!isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            $this->input = file_get_contents('php://input');
        } else {
            $this->input = $GLOBALS['HTTP_RAW_POST_DATA'];
        }
        //$this->input = xmlrpc_encode_request('channel.listAll', array());
        if (empty($this->input)) {
            throw new Chiara_PEAR_Server_ExceptionNeedPayload;
        } else {
            $this->output = xmlrpc_server_call_method($this->server, $this->input,
                $this->_channel, array('output_type' => 'xml'));
        }
    }

    /**
     * The server object that receives input from
     * the frontend
     * @param Chiara_PEAR_Server
     */
    public function setServer(&$server)
    {
        $this->_server = $server;
        $this->_backend = $server->getBackend($this->_channel);
    }

    /**
     * Return output to the user from a function
     * @param mixed
     */
    public function funcReturn($output, $class, $method)
    {
        if (!$this->_dontappend) {
            if ($class == 'Chiara_PEAR_Server') {
                switch ($method) {
                    case 'packageListAll' :
                    case 'packageListLatestReleases' :
                    case 'packageInfo' :
                    case 'packageGetDownloadURL' :
                    case 'packageGetDepDownloadURL' :
                    case 'channelListAll' :
                    case 'channelUpdate' :
                    case 'logintest' :
                        return $output;
                    break;
                    default :
                        return $class . $method;
                    break;
                }
            }
        }
        return $output;
    }

    public function parseInput($input)
    {
        $method = $input[0];
        $params = $input[1];
        try {
            if (!isset($this->signatures[$method])) {
                throw new Chiara_PEAR_Server_ExceptionXmlrpcUnknownMethod($method);
            }
            $type = '';
            foreach ($params as $param) {
                if (!empty($type)) {
                    $type .= ',';
                }
                $type .= xmlrpc_get_type($param);
            }
            if (!isset($this->signatures[$method]['sig'][$type])) {
                throw new Chiara_PEAR_Server_ExceptionXmlrpcUnknownSignature($method, $type);
            }
            if ($method != 'package.getDownloadURL') {
                array_unshift($params, $this->_channel);
            }
            $this->_dontappend = false;
            if ($method == 'logintest') {
                return $this->funcReturn(true, __CLASS__, __FUNCTION__);
            }
            // change package.method to packageMethod
            $method = explode('.', $method);
            $method[1] = ucfirst($method[1]);
            $method = implode('', $method);
            return call_user_func_array(array($this->_server, $method), $params);
        } catch (Chiara_PEAR_Server_Exception $e) {
            $this->error($e);
        }
    }

    /**
     * Get a list of protocols that this frontend supports
     * @return array
     */
    public function getProtocols()
    {
        $start = array_keys($this->signatures);
        $ret = array();
        foreach ($start as $protocol) {
            $ret[] = array('type' => 'xml-rpc', 'name' => $protocol, 'version' => $this->signatures[$protocol]['version']);
        }
        return $ret;
    }

    public function getOutput()
    {
        header('Content-length: ' . strlen($this->output));
        header('Content-type: text/xml');
        echo $this->output;
        exit;
    }

    protected function error($exception)
    {
        $response = "<?xml version='1.0' encoding='iso-8859-1' ?>
<methodResponse>
<fault>
 <value>
  <struct>
   <member>
    <name>faultString</name>
    <value>
     <string>" . $exception->getMessage() . "</string>
    </value>
   </member>
   <member>
    <name>faultCode</name>
    <value>
     <int>1</int>
    </value>
   </member>
  </struct>
 </value>
</fault>
</methodResponse>
";
        header('Content-length: ' . strlen($response));
        header('Content-type: text/xml');
        echo $response;
        exit;
    }
}
?>