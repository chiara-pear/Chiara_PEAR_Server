<?php
class Server_mysqlinstall_postinstall
{
    var $_pkg;
    var $_ui;
    var $_config;
    var $_registry;
    var $db;
    var $user;
    var $password;
    var $lastversion;
    var $channel;
    var $alias;
    var $handle;
    var $docroot;
    var $port;
    var $ssl;
    var $xmlrpcphp;
    var $pearconfigloc;
    var $dbhost;
    var $databaseExists;
    function init(&$config, &$pkg, $lastversion)
    {
        $this->_config = &$config;
        $this->_registry = &$config->getRegistry();
        $this->_ui = &PEAR_Frontend::singleton();
        $this->_pkg = &$pkg;
        $this->lastversion = $lastversion;
        $this->databaseExists = false;
        return true;
    }

    function run($answers, $phase)
    {
        switch ($phase) {
            case 'questionCreate' :
                if ($answers['doit'] != 'yes') {
                    return false;
                }
                return true;
            break;
            case 'databaseSetup' :
                return $this->createDatabase($answers);
            break;
            case 'channelCreate' :
                return $this->createChannel($answers);
            break;
            case 'administrator' :
                return $this->setupAdministrator($answers);
            break;
            case 'files' :
                return $this->createServerFiles($answers);
            break;
            case '_undoOnError' :
                // answers contains paramgroups that succeeded in reverse order
                foreach ($answers as $group) {
                    switch ($group) {
                        case 'questionCreate' :
                        break;
                        case 'databaseCreate' :
                            if ($this->lastversion || $this->databaseExists) {
                                // don't uninstall the database if it had already existed
                                break;
                            }
                            $conn = $this->getDBConnection();
                            if (!$conn) {
                                break;
                            }
                            if (extension_loaded('mysqli')) {
                                $query = mysqli_select_db($conn, $this->db);
                            } else {
                                $query = mysql_select_db($this->db, $conn);
                            }
                            if ($query) {
                                if (extension_loaded('mysqli')) {
                                    $res = @mysqli_query($conn, 'DROP DATABASE ' . $this->db);
                                } else {
                                    $res = @mysql_query('DROP DATABASE ' . $this->db);
                                }
                            }
                            $this->closeDB($conn);
                        break;
                        case 'channelCreate' :
                            $conn = $this->getDBConnection();
                            if (!$conn) {
                                break;
                            }
                            if (extension_loaded('mysqli')) {
                                $query = mysqli_select_db($conn, $this->db);
                            } else {
                                $query = mysql_select_db($this->db, $conn);
                            }
                            if ($query) {
                                if (extension_loaded('mysqli')) {
                                    $res = @mysqli_query($conn, 'DELETE FROM channels WHERE channel="' .
                                        $this->channel . '"');
                                } else {
                                    $res = @mysql_query('DELETE FROM channels WHERE channel="' .
                                        $this->channel . '"');
                                }
                            }
                            $this->closeDB($conn);
                        break;
                        case 'administrator' :
                            $conn = $this->getDBConnection();
                            if (!$conn) {
                                break;
                            }
                            if (extension_loaded('mysqli')) {
                                $query = mysqli_select_db($conn, $this->db);
                            } else {
                                $query = mysql_select_db($this->db, $conn);
                            }
                            if ($query) {
                                if (extension_loaded('mysqli')) {
                                    $res = @mysqli_query($conn, 'DELETE FROM handles WHERE handle="' .
                                        $this->handle . '"');
                                } else {
                                    $res = @mysql_query('DELETE FROM handles WHERE handle="' .
                                        $this->handle . '"');
                                }
                            }
                            $this->closeDB($conn);
                        break;
                        case 'files' :
                            if (file_exists($this->docroot . DIRECTORY_SEPARATOR . 'xmlrpc.php')) {
                                unlink($this->docroot . DIRECTORY_SEPARATOR . 'xmlrpc.php');
                            }
                            if (file_exists($this->docroot . DIRECTORY_SEPARATOR . $this->frontend)) {
                                unlink($this->docroot . DIRECTORY_SEPARATOR . $this->frontend);
                            }
                        break;
                    }
                }
            break;
        }
    }

    function getDBConnection()
    {
        if (extension_loaded('mysqli')) {
            $conn = @mysqli_connect($this->dbhost, $this->user, $this->password);
        } else {
            $conn = @mysql_connect($this->dbhost, $this->user, $this->password);
        }
        if (!$conn) {
            $this->_ui->outputData('Connection to mysql server failed');
            return false;
        }
        return $conn;
    }

    function closeDB($conn)
    {
        if (extension_loaded('mysqli')) {
            mysqli_close($conn);
        } else {
            mysql_close($conn);
        }
    }

    function checkSetup()
    {
        $conn = $this->getDBConnection();
        if (!$conn) {
            return false;
        }
        if (extension_loaded('mysqli')) {
            $query = mysqli_select_db($conn, $this->db);
            $query = mysqli_query($conn, 'SELECT handle FROM handles WHERE handle = "' .
                mysqli_real_escape_string($conn, $this->handle) . '"');
            if (mysqli_num_rows($query)) {
                $this->_ui->skipParamGroup('administrator');
            }
            $query = mysqli_query($conn, 'SELECT channel FROM channels WHERE channel = "' .
                mysqli_real_escape_string($conn, $this->channel) . '"');
            if (mysqli_num_rows($query)) {
                $this->_ui->skipParamGroup('channelCreate');
            }
        } else {
            $query = mysql_select_db($this->db, $conn);
            $query = mysql_query('SELECT handle FROM handles WHERE handle = "' .
                mysql_real_escape_string($this->handle, $conn) . '"', $conn);
            if (mysql_num_rows($query)) {
                $this->_ui->skipParamGroup('administrator');
            }
            $query = mysql_query('SELECT channel FROM channels WHERE channel = "' .
                mysql_real_escape_string($this->channel, $conn) . '"', $conn);
            if (mysql_num_rows($query)) {
                $this->_ui->skipParamGroup('channelCreate');
            }
        }
        $this->closeDB($conn);
        return true;
    }

    function updateDatabase($sqlfile, $msg, $conn)
    {
        $contents = explode(';',
            file_get_contents($sqlfile));

        if ($msg) {
            $this->_ui->outputData($msg);
        }
        if (count($contents) > 1) {
            foreach ($contents as $indquery) {
                if (strpos($indquery, 'CREATE') === false && strpos($indquery, 'ALTER') === false) {
                    continue;
                }
                if (extension_loaded('mysqli')) {
                    $query = mysqli_query($conn, $indquery);
                } else {
                    $query = mysql_query($indquery, $conn);
                }
                if (!$query) {
                    break;
                }
            }
            if ($query) {
                $this->_ui->outputData('Upgrading tables succeeded');
                return true;
            } else {
                if (extension_loaded('mysqli')) {
                    $this->_ui->outputData(mysqli_error($conn));
                } else {
                    $this->_ui->outputData(mysql_error());
                }
                $this->_ui->outputData('Upgrading tables failed');
                return false;
            }
        } else {
            $this->_ui->outputData('Could not open the sql for table upgrade');
            return false;
        }
    }

    function createDatabase($answers)
    {
        $this->dbhost = $answers['dbhost'];
        $this->db = $answers['database'];
        $this->user = $answers['user'];
        $this->password = $answers['password'];
        $this->channel = $answers['name'];
        $this->handle = $answers['handle'];
        $conn = $this->getDBConnection();
        if (!$conn) {
            return false;
        }
        if (extension_loaded('mysqli')) {
            $query = mysqli_select_db($conn, $this->db);
        } else {
            $query = mysql_select_db($this->db, $conn);
        }
        if ($this->lastversion) {
            if ($query) {
                // upgrading?
                if (extension_loaded('mysqli')) {
                    $query = @mysqli_query($conn, 'SELECT channel_deprecated FROM packages');
                } else {
                    $query = @mysql_query('SELECT channel_deprecated FROM packages', $conn);
                }
                if (!$query) {
                    $a = $this->updateDatabase(
             '@data-dir@/Chiara_PEAR_Server/data/deprecatedpackages-chiara_pear_server-0.17.0.sql',
             'updating database to add deprecated package support', $conn);
                    if (!$a) {
                        return $a;
                    }
                }
                if (extension_loaded('mysqli')) {
                    $check = @mysqli_query($conn, 'SELECT * FROM categories');
                } else {
                    $check = @mysql_query('SELECT * FROM categories', $conn);
                }
                if ($check) {
                    $this->_ui->outputData('database is already upgraded');
                    $this->closeDB($conn);
                    return $this->checkSetup(); // tables already updated
                }
                if (extension_loaded('mysqli')) {
                    $query = mysqli_select_db($conn, $answers['database']);
                } else {
                    $query = mysql_select_db($answers['database'], $conn);
                }
                if ($query) {
                    $a = $this->updateDatabase(
                        '@data-dir@/Chiara_PEAR_Server/data/upgrade-0.12.0_0.13.0.sql', false, $conn);
                    if (!$a) {
                        $this->closeDB($conn);
                        return $a;
                    }
                }
                return $this->checkSetup();
            }
        } else {
            if ($query) {
                $this->databaseExists = true;
                if (extension_loaded('mysqli')) {
                    $query = @mysqli_query($conn, 'SELECT channel_deprecated FROM packages');
                } else {
                    $query = @mysql_query('SELECT channel_deprecated FROM packages', $conn);
                }
                if (!$query) {
                    $a = $this->updateDatabase(
             '@data-dir@/Chiara_PEAR_Server/data/deprecatedpackages-chiara_pear_server-0.17.0.sql',
             'updating database to add deprecated package support', $conn);
                    if (!$a) {
                        $this->closeDB($conn);
                        return $a;
                    }
                } else {
                    $this->_ui->outputData('database is already setup');
                }
                $this->closeDB($conn);
                return $this->checkSetup();
            }
        }
        if (extension_loaded('mysqli')) {
            $query = mysqli_query($conn, 'CREATE DATABASE ' . $answers['database']);
        } else {
            $query = mysql_query('CREATE DATABASE ' . $answers['database'], $conn);
        }
        if ($query) {
            if (extension_loaded('mysqli')) {
                $query = mysqli_select_db($conn, $answers['database']);
            } else {
                $query = mysql_select_db($answers['database'], $conn);
            }
            if ($query) {
                $a = $this->updateDatabase('@data-dir@/Chiara_PEAR_Server/data/pearserver.sql', false,
                    $conn);
                if (!$a) {
                    $this->closeDB($conn);
                    return false;
                }
            } else {
                if (extension_loaded('mysqli')) {
                    $this->_ui->outputData(mysqli_error($conn));
                } else {
                    $this->_ui->outputData(mysql_error());
                }
                $this->_ui->outputData('Could not select database for creating');
            }
        } else {
            $this->_ui->outputData('Database creation failed' .
                (extension_loaded('mysqli') ? mysqli_error($conn) : mysql_error()));
        }
        $this->closeDB($conn);
        return false;
    }

    function createChannel($answers)
    {
        $this->alias = $answers['alias'];
        if (!$this->lastversion) {
            include_once 'DB/DataObject.php';
            if (!class_exists('DB_DataObject')) {
                $this->_ui->outputData('DB_DataObject is required to use Chiara_PEAR_Server');
                return false;
            }
            $options = &PEAR::getStaticProperty('DB_DataObject','options');
            $type = extension_loaded('mysqli') ? 'mysqli' : 'mysql';
            $phpdir = str_replace('\\', '/', '@php-dir@');
            $datadir = str_replace('\\', '/', '@data-dir@');
            $options = array(
                'database'         => $type . '://' . $this->user . ':' . $this->password .
                    '@' . $this->dbhost . '/' . $this->db,
                'schema_location'  => $datadir . '/Chiara_PEAR_Server/data/DBDataObject',
                'class_location'   => $phpdir . '/Chiara/PEAR/Server/Backend/DBDataObject/',
                'require_prefix'   => 'Chiara/PEAR/Server/Backend/DBDataObject/',
                'class_prefix'     => 'Chiara_PEAR_Server_Backend_DBDataObject_',
            );
            $dbo = DB_DataObject::factory('channels');
            $dbo->channel = $answers['name'];
            $dbo->summary = $answers['summary'];
            $dbo->alias = $answers['alias'];
            if (!$dbo->find()) {
                $dbo->channel = $answers['name'];
                $dbo->summary = $answers['summary'];
                $dbo->alias = $answers['alias'];
                if (!$dbo->insert()) {
                    $this->_ui->outputData('Creation of channel failed');
                    return false;
                }
            }
        }
        return true;
    }

    function setupAdministrator($answers)
    {
        if ($this->lastversion) {
            return true;
        }
        include_once 'DB/DataObject.php';
        if (!class_exists('DB_DataObject')) {
            $this->_ui->outputData('DB_DataObject is required to use Chiara_PEAR_Server');
            return false;
        }
        $options = &PEAR::getStaticProperty('DB_DataObject','options');
        $type = extension_loaded('mysqli') ? 'mysqli' : 'mysql';
        $phpdir = str_replace('\\', '/', '@php-dir@');
        $datadir = str_replace('\\', '/', '@data-dir@');
        $options = array(
            'database'         => $type . '://' . $this->user . ':' . $this->password .
                '@' . $this->dbhost . '/' . $this->db,
            'schema_location'  => $datadir . '/Chiara_PEAR_Server/data/DBDataObject',
            'class_location'   => $phpdir . '/Chiara/PEAR/Server/Backend/DBDataObject/',
            'require_prefix'   => 'Chiara/PEAR/Server/Backend/DBDataObject/',
            'class_prefix'     => 'Chiara_PEAR_Server_Backend_DBDataObject_',
        );
        $this->_ui->outputData('Add the primary administrator');
        $dbo = DB_DataObject::factory('handles');
        $dbo->handle = $this->handle;
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);
        if (!$dbo->find()) {
            $dbo->name = $answers['name'];
            $dbo->email = $answers['email'];
            $dbo->password = md5($answers['password']);
            $dbo->admin = 1;
            $a = $dbo->insert();
        } else {
            $dbo->name = $answers['name'];
            $dbo->email = $answers['email'];
            $dbo->password = md5($answers['password']);
            $dbo->admin = 1;
            $a = $dbo->update();
        }
        PEAR::popErrorHandling();
        if ($a === false || PEAR::isError($a)) {
            $this->_ui->outputData('Creation of admin user failed');
            if (PEAR::isError($a)) {
                PEAR::raiseError($a);
            }
            return false;
        } else {
            $this->_ui->outputData('Creation of admin user succeeded');
            return true;
        }
    }

    function createServerFiles($answers)
    {
        $this->pearconfigloc = $answers['pearconfigloc'];
        $options = &PEAR::getStaticProperty('DB_DataObject','options');
        $type = extension_loaded('mysqli') ? 'mysqli' : 'mysql';
        $phpdir = str_replace('\\', '/', '@php-dir@');
        $datadir = str_replace('\\', '/', '@data-dir@');
        $options = array(
            'database'         => $type . '://' . $this->user . ':' . $this->password .
                '@' . $this->dbhost . '/' . $this->db,
            'schema_location'  => $datadir . '/Chiara_PEAR_Server/data/DBDataObject',
            'class_location'   => $phpdir . '/Chiara/PEAR/Server/Backend/DBDataObject/',
            'require_prefix'   => 'Chiara/PEAR/Server/Backend/DBDataObject/',
            'class_prefix'     => 'Chiara_PEAR_Server_Backend_DBDataObject_',
        );
        require_once 'DB/DataObject.php';
        $dbo = DB_DataObject::factory('channels');
        $dbo->channel = $this->channel;
        if (!$dbo->find(true)) {
            $this->_ui->outputData('Channel "' . $this->channel . '" does not exist in database?');
            return false;
        }
        include_once 'PEAR/ChannelFile.php';
        $this->_ui->outputData('adding channel to local registry');
        $chan = new PEAR_ChannelFile;
        $chan->setName($dbo->channel);
        $chan->setSummary($dbo->summary);
        $chan->setAlias($dbo->alias);
        $chan->setDefaultPEARProtocols();
        if ($this->port != 80) {
            $chan->setPort($this->port);
        }
        if ($this->ssl) {
            $chan->setSSL();
        }
        if ($this->xmlrpcphp != 'xmlrpc.php') {
            $chan->setPath('xmlrpc', $this->xmlrpcphp);
        }
        if (!$this->_registry->channelExists($dbo->channel)) {
            $this->_registry->addChannel($chan);
        } else {
            $this->_registry->updateChannel($chan);
        }
        $xml = $chan->toXml();
        $this->xmlrpcphp = $answers['xmlrpcphp'];
        $this->ssl = ($answers['ssl'] == 'https');
        $this->port = $answers['port'];
        $this->frontend = $answers['frontendphp'];
        include_once 'System.php';
        if (!class_exists('System')) {
            $this->_ui->outputData('System class required to create server files');
            return false;
        }
        if (!file_exists($answers['docroot'])) {
            System::mkdir(array('-p', $answers['docroot']));
        }
        $fp = @fopen($answers['docroot'] . DIRECTORY_SEPARATOR . 'channel.xml', 'w');
        if ($fp) {
            fwrite($fp, $xml);
            fclose($fp);
        } else {
            $this->_ui->outputData('Cannot open "' . $answers['docroot'] . DIRECTORY_SEPARATOR .
                'channel.xml" for writing');
            return false;
        }
        if (!file_exists($answers['docroot'] . DIRECTORY_SEPARATOR . 'get')) {
            System::mkdir(array('-p', $answers['docroot'] . DIRECTORY_SEPARATOR . 'get'));
        }
        @chmod($answers['docroot'] . DIRECTORY_SEPARATOR . 'get', 0777);
        // create xmlrpc.php
        $type = extension_loaded('mysqli') ? 'mysqli' : 'mysql';
        $config = 'array(\'database\'         => \'' .
            $type . '://' . $this->user . ':' . $this->password . '@' . $this->dbhost .
            '/' . $this->db . '\')';
        $contents = '<?php
require_once \'Chiara/PEAR/Server.php\';
require_once \'Chiara/PEAR/Server/Backend/DBDataObject.php\';
require_once \'Chiara/PEAR/Server/Frontend/Xmlrpc5.php\';
$backend = new Chiara_PEAR_Server_Backend_DBDataObject(\'' . $this->channel . '\', ' . $config . ');
$frontend = Chiara_PEAR_Server_Frontend_Xmlrpc5::singleton(\'' . $this->channel . '\');
$server = new Chiara_PEAR_Server(\'' . $answers['docroot'] . DIRECTORY_SEPARATOR . 'get' . '\');
$server->setBackend($backend);
$server->setFrontend($frontend);
$server->run();
?>';
        $fp = fopen($answers['docroot'] . DIRECTORY_SEPARATOR . $this->xmlrpcphp, 'w');
        $a = fwrite($fp, $contents, strlen($contents));
        fclose($fp);
        if ($a) {
            $this->_ui->outputData('Successfully created ' .
                $answers['docroot'] . DIRECTORY_SEPARATOR . $this->xmlrpcphp);
        } else {
            $this->_ui->outputData('Could not create ' .
                $answers['docroot'] . DIRECTORY_SEPARATOR . $this->xmlrpcphp);
            return false;
        }

        // create frontend.php
        $extraconf = '';
        if ($this->pearconfigloc != '(Use default)') {
            if (@file_exists($this->pearconfigloc)) {
                $extraconf = "require_once 'PEAR/Config.php';\n" .
                             'PEAR_Config::singleton("' . addslashes($this->pearconfigloc) . "\",\n" .
                             '"' . addslashes($this->pearconfigloc) . "\");\n";
            }
        }
        $type = extension_loaded('mysqli') ? 'mysqli' : 'mysql';
        $config = 'array(\'database\' => \'' .
            $type . '://' . $this->user . ':' . $this->password . '@' . $this->dbhost . '/' . $this->db . '\')';
        $contents = '<?php
' . $extraconf . 'require_once \'Chiara/PEAR/Server.php\';
require_once \'Chiara/PEAR/Server/Backend/DBDataObject.php\';
require_once \'Chiara/PEAR/Server/Frontend/HTMLQuickForm.php\';
$backend = new Chiara_PEAR_Server_Backend_DBDataObject(\'' . $this->channel . '\', ' . $config . ');
$frontend = new Chiara_PEAR_Server_Frontend_HTMLQuickForm(\'' . $this->channel .
        '\', new HTML_QuickForm(\'channel\'),
        \'' . $answers['frontendphp'] . '\', \'' . $answers['uploadpath'] . '\');
$frontend->sessionStart();
$server = new Chiara_PEAR_Server(\'' . $answers['docroot'] . DIRECTORY_SEPARATOR . 'get' . '\');
$server->setBackend($backend);
$server->setFrontend($frontend);
$server->run();
?>';
        $fp = fopen($answers['docroot'] . DIRECTORY_SEPARATOR . $answers['frontendphp'], 'w');
        $a = fwrite($fp, $contents, strlen($contents));
        fclose($fp);
        if ($a) {
            $this->_ui->outputData('Successfully created ' .
                $answers['docroot'] . DIRECTORY_SEPARATOR . $answers['frontendphp']);
        } else {
            $this->_ui->outputData('Could not create ' .
                $answers['docroot'] . DIRECTORY_SEPARATOR . $answers['frontendphp']);
            return false;
        }
        if (!file_exists($answers['docroot'] . DIRECTORY_SEPARATOR . 'pear_server.css')) {
            $b = @copy(str_replace(array('/', '\\'), array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR),
                '@data-dir@/PEAR_Server/data/pear_server.css'), $answers['docroot'] .
                DIRECTORY_SEPARATOR . 'pear_server.css');
            if ($b) {
                $this->_ui->outputData('Successfully created ' .
                    $answers['docroot'] . DIRECTORY_SEPARATOR . 'pear_server.css');
            } else {
                $this->_ui->outputData('Could not create ' .
                    $answers['docroot'] . DIRECTORY_SEPARATOR . 'pear_server.css');
                return false;
            }
        } else {
            $this->_ui->outputData(
                $answers['docroot'] . DIRECTORY_SEPARATOR . 'pear_server.css already exists');
        }
        return true;
    }
}
?>