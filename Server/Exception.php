<?php
abstract class Chiara_PEAR_Server_Exception extends Exception
{
    protected $_data = array();
    /**
     * @param string
     * @param array
     */
    function __construct($msg)
    {
        foreach ($this->_data as $name => $value)
        {
            $msg = str_replace("%$name%", $value, $msg);
        }
        parent::__construct($msg);
    }

    public function getData()
    {
        return $this->_data;
    }
}

class Chiara_PEAR_Server_ExceptionPackageExists extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Package "%package%" in Channel "%channel%" already exists';
    public function __construct($package, $channel, $msg = false)
    {
        $this->_data = array('package' => $package, 'channel' => $channel);
        parent::__construct($msg ? $msg : $this->_message);
    }
}


class Chiara_PEAR_Server_ExceptionCategoryExists extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Category "%category%" in Channel "%channel%" already exists';
    public function __construct($category, $channel, $msg = false)
    {
        $this->_data = array('category' => $category, 'channel' => $channel);
        parent::__construct($msg ? $msg : $this->_message);
    }
}


class Chiara_PEAR_Server_ExceptionPackageDoesntExist extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Package "%package%" in Channel "%channel%" does not exist';
    public function __construct($package, $channel, $msg = false)
    {
        $this->_data = array('package' => $package, 'channel' => $channel);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionCategoryDoesntExist extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Category "%category%" in Channel "%channel%" does not exist';
    public function __construct($category, $channel, $msg = false)
    {
        $this->_data = array('category' => $category, 'channel' => $channel);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionCategoryNoUpdate extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Unable to update category "%category%" in Channel "%channel%"';
    public function __construct($category, $channel, $msg = false)
    {
        $this->_data = array('category' => $category, 'channel' => $channel);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionParentPackageDoesntExist extends Chiara_PEAR_Server_ExceptionPackageDoesntExist
{
    protected $_message = 'Parent package "%package%" in Channel "%channel%" does not exist';
}

class Chiara_PEAR_Server_ExceptionMaintainerExists extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Maintainer "%handle%" already exists, cannot add';
    public function __construct($handle, $msg = false)
    {
        $this->_data = array('handle' => $handle);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionMaintainerDoesntExist extends Chiara_PEAR_Server_ExceptionMaintainerExists
{
    protected $_message = 'Maintainer "%handle%" does not exist, cannot update';
}

class Chiara_PEAR_Server_ExceptionPackageMaintainerDoesntExist extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Package "%package%" in channel "%channel%" is not maintained by "%maintainer%"';
    public function __construct($package, $channel, $maintainer, $msg = false)
    {
        $this->_data = array('package' => $package, 'channel' => $channel, 'maintainer' => $maintainer);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionPackageMaintainerExists extends Chiara_PEAR_Server_ExceptionPackageMaintainerDoesntExist
{
    protected $_message = 'Package "%package%" in channel "%channel%" is already maintained by "%maintainer%", use update instead of add';
}

class Chiara_PEAR_Server_ExceptionNoXmlrpc extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'XML-RPC extension must be loaded to use the xml-rpc frontend';
    public function __construct($msg = false)
    {
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionXmlrpcUnknownMethod extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Unknown XML-RPC method "%method%" requested';
    public function __construct($method, $msg = false)
    {
        $this->_data = array('method' => $method);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionXmlrpcUnknownSignature extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Invalid Method signature "%signature%" for XML-RPC method "%method%"';
    public function __construct($method, $signature, $msg = false)
    {
        $this->_data = array('method' => $method, 'signature' => $signature);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionNeedPayload extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'This page may not be accessed directly, but only through xml-rpc';
    public function __construct($msg = false)
    {
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionChannelSetup extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Channel "%channel%" must be manually set up in the database prior to use';
    public function __construct($channel, $msg = false)
    {
        $this->_data = array('channel' => $channel);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionDoesntMaintain extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Maintainer "%maintainer%" does not maintain "%channel%::%package%", and cannot release a new version';
    public function __construct($maintainer, $package, $channel, $msg = false)
    {
        $this->_data = array('channel' => $channel, 'package' => $package, 'maintainer' => $maintainer);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionMaintainerNotLead extends Chiara_PEAR_Server_ExceptionDoesntMaintain
{
    protected $_message = 'Maintainer "%maintainer%" is not a lead maintainer of "%channel%::%package%", and cannot release a new version';
}

class Chiara_PEAR_Server_ExceptionChannelNotLocal extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Channel "%channel%" is not a local channel (is it in the database?)';
    public function __construct($channel, $msg = false)
    {
        $this->_data = array('channel' => $channel);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionNeedChannel extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'getDownloadURL expects index "channel" and index "package" to be set in the first parameter';

    public function __construct($msg = false)
    {
        $this->_data = array();
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionInvalidState extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'getDownloadURL: preferred state "%state%" is not a valid stability state';
    public function __construct($state, $msg = false)
    {
        $this->_data = array('state' => $state);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionGroupNotFound extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'getDownloadURL: package "%package%" version "%version%" has no optional dependency groups';

    public function __construct($package, $version, $msg = false)
    {
        $this->_data = array('package' => $package, 'version' => $version);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionXsdversion1_0 extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'getDepDownloadURL: package.xml dependencies cannot depend on channels other than pear.php.net';

    public function __construct($msg = false)
    {
        $this->_data = array();
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionInvalidRelease extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Invalid release uploaded: "%info%"';

    public function __construct($packagefile, $msg = false)
    {
        $this->_data = array('info' => $packagefile->getUserInfo());
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class PEAR_Server_ExceptionReleaseNotFound extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Package %p% release %v% does not exist';

    public function __construct($package, $version, $msg = false)
    {
        $this->_data = array('p' => $package, 'v' => $version);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionCannotDeleteHasReleases extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Package %p% in channel %c% cannot be deleted, delete all releases first';

    public function __construct($package, $channel, $msg = false)
    {
        $this->_data = array('p' => $package, 'c' => $channel);
        parent::__construct($msg ? $msg : $this->_message);
    }
}

class Chiara_PEAR_Server_ExceptionMaintainerManagesPackages extends Chiara_PEAR_Server_Exception
{
    protected $_message = 'Maintainer %m% cannot be deleted, this maintainer maintains packages';

    public function __construct($maintainer, $msg = false)
    {
        $this->_data = array('m' => $maintainer);
        parent::__construct($msg ? $msg : $this->_message);
    }
}
?>