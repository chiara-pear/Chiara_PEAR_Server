<?php
require_once 'Chiara/PEAR/Server/Frontend.php';
require_once 'Chiara/XML/RPC5/Server.php';
require_once 'Chiara/XML/RPC5/Interface.php';

class Chiara_PEAR_Server_Frontend_Xmlrpc5_Function extends Chiara_XML_RPC5_Server
{
    public function __construct()
    {
        parent::__construct('function');
    }

    /**
     * @return boolean
     */
    public function logintest()
    {
        return true;
    }
}

class Chiara_PEAR_Server_Frontend_Xmlrpc5_Channel extends Chiara_XML_RPC5_Server
{
    private $_server;
    public function __construct($server)
    {
        $this->_server = $server;
        parent::__construct('channel');
    }

    /**
     * @return array
     */
    public function listAll()
    {
        return $this->_server->channelListAll();
    }
}

class Chiara_PEAR_Server_Frontend_Xmlrpc5_Package extends Chiara_XML_RPC5_Server
{
    private $_server;
    private $_channel;
    public function __construct($server, $channel)
    {
        $this->_server = $server;
        $this->_channel = $channel;
        parent::__construct('package');
    }

    function callWithChannel($method, $args)
    {
        array_unshift($args, $this->_channel);
        return call_user_func_array(array($this->_server, $method), $args);
    }

    /**
     * @param array
     * @param string
     * @param string
     * @return array
     */
    public function getDownloadURL($packageinfo)
    {
        $args = func_get_args();
        return call_user_func_array(array($this->_server, 'packageGetDownloadURL'), $args);
    }

    /**
     * @param string
     * @param array
     * @param array
     * @param string
     * @param string
     * @return array
     */
    public function getDepDownloadURL($xsdversion, $dependency, $deppackage)
    {
        $args = func_get_args();
        return $this->callWithChannel('packageGetDepDownloadURL', $args);
    }

    /**
     * @param boolean
     * @param boolean
     * @param boolean
     * @return array
     */
    public function listAll()
    {
        $args = func_get_args();
        return $this->callWithChannel('packagelistAll', $args);
    }

    /**
     * @param string
     * @param string|boolean
     * @param boolean
     * @param boolean
     * @param boolean
     * @param boolean
     * @return array
     */
    public function search($fragment)
    {
        $args = func_get_args();
        return $this->callWithChannel('packageSearch', $args);
    }

    /**
     * @param string
     * @return array
     */
    public function listLatestReleases()
    {
        $args = func_get_args();
        return $this->callWithChannel('packageListLatestReleases', $args);
    }

    /**
     * @param string|int
     * @param string
     * @return array
     */
    public function info($package)
    {
        $args = func_get_args();
        return $this->callWithChannel('packagePackageInfo', $args);
    }
}

class Chiara_PEAR_Server_Frontend_Xmlrpc5 extends Chiara_PEAR_Server_Frontend
{
    protected $input;
    protected $output;
    protected $server;
    private $_dontappend = true;
    private static $singleton = array();

    protected function __construct($channel)
    {
        parent::__construct($channel);
    }

    public static function singleton($channel)
    {
        if (isset(self::$singleton[$channel])) {
            return self::$singleton[$channel];
        }
        self::$singleton[$channel] = new Chiara_PEAR_Server_Frontend_Xmlrpc5($channel);
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
        try {
            if (empty($this->input)) {
                throw new Chiara_PEAR_Server_ExceptionNeedPayload;
            } else {
                Chiara_XML_RPC5_Server::run();
            }
        } catch (Exception $e) {
            $this->error($e);
        }
    }

    public function parseInput($input)
    {
    }

    /**
     * The server object that receives input from
     * the frontend
     * @param Chiara_PEAR_Server
     */
    public function setServer($server)
    {
        $this->_server = $server;
        $this->_backend = $server->getBackend($this->_channel);
        // register the handlers
        new Chiara_PEAR_Server_Frontend_Xmlrpc5_Channel($this->_server);
        new Chiara_PEAR_Server_Frontend_Xmlrpc5_Function($this->_server);
        new Chiara_PEAR_Server_Frontend_Xmlrpc5_Package($this->_server, $this->_channel);
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

    /**
     * Get a list of protocols that this frontend supports
     * @return array
     */
    public function getProtocols()
    {
        $ret = array();
        return $ret;
    }

    public function getOutput()
    {
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