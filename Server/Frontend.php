<?php
abstract class Chiara_PEAR_Server_Frontend
{
    protected $_server;
    protected $_channel;
    
    /**
     * Backend
     *
     * @var Chiara_PEAR_Server_Backend
     */
    protected $_backend;

    public function __construct($channel)
    {
        $this->_channel = $channel;
    }

    abstract public function main();

    /**
     * The server object that receives input from
     * the frontend
     * @param Chiara_PEAR_Server
     */
    public function setServer(&$server)
    {
        $this->_server = &$server;
        $this->_backend = $server->getBackend($this->_channel);
    }

    /**
     * Return output to the user from a function
     * @param mixed
     */
    public function funcReturn($output, $class, $method)
    {
        return $output;
    }

    abstract public function parseInput($input);

    /**
     * Get a list of protocols that this frontend supports
     * @return array
     */
    public function getProtocols()
    {
        return array();
    }

    public function getOutput()
    {
        return '';
    }
}
?>