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
    var $fixHandles = false;
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

    function postProcessPrompts($prompts, $section)
    {
        switch ($section) {
            case 'channelCreate' :
                $prompts[0]['default'] = array_shift($a = explode('.', $this->channel));
            break;
            case 'files' :
                $conffile = $this->_config->getConfFile('user');
                if (!file_exists($conffile)) {
                    $conffile = $this->_config->getConfFile('system');
                }
                $prompts[0]['default'] = $conffile;
                $prompts[1]['prompt'] = sprintf($prompts[1]['prompt'], $this->channel);
            break;
        }
        return $prompts;
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
            $conn = mysqli_connect($this->dbhost, $this->user, $this->password);
        } else {
            $conn = mysql_connect($this->dbhost, $this->user, $this->password);
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
                mysqli_real_escape_string($conn, $this->handle) . '" AND channel = "' .
                mysqli_real_escape_string($conn, $this->channel) . '"');
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
                mysql_real_escape_string($this->handle, $conn) . '" AND channel = "' .
                mysql_real_escape_string($this->channel, $conn) . '"', $conn);
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
                $this->_ui->outputData('Updating database succeeded');
                return true;
            } else {
                if (extension_loaded('mysqli')) {
                    $this->_ui->outputData(mysqli_error($conn));
                } else {
                    $this->_ui->outputData(mysql_error());
                }
                $this->_ui->outputData('Updating database failed');
                return false;
            }
        } else {
            $this->_ui->outputData('Could not open the sql for database update');
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
        if ($this->db != 'pearserver') {
            $ds = DIRECTORY_SEPARATOR;
            $this->_ui->outputData('Copying DB_DataObject config file to "@data-dir@' .
                $ds . 'Chiara_PEAR_Server' . $ds . 'data' . $ds . 'DBDataObject' .
                $ds . $this->db . '.ini"');
            copy('@data-dir@' . $ds . 'Chiara_PEAR_Server' . $ds . 'data' . $ds .
                    'DBDataObject' . $ds . 'pearserver.ini',
                '@data-dir@' . $ds . 'Chiara_PEAR_Server' . $ds . 'data' . $ds .
                    'DBDataObject' . $ds . $this->db . '.ini');
        }
        if (extension_loaded('mysqli')) {
            $query = mysqli_select_db($conn, $this->db);
        } else {
            $query = mysql_select_db($this->db, $conn);
        }
        if ($query) {
            // upgrading?
            // Deprecated packages upgrade
            if (extension_loaded('mysqli')) {
                $query = @mysqli_query($conn, 'SELECT deprecated_package FROM packages');
            } else {
                $query = @mysql_query('SELECT deprecated_package FROM packages', $conn);
            }
            if (!$query) {
                $a = $this->updateDatabase(
         '@data-dir@/Chiara_PEAR_Server/data/deprecatedpackages-chiara_pear_server-0.17.0.sql',
         'updating database to add deprecated package support', $conn);
                if (!$a) {
                    return $a;
                }
            }
            // handles-channel upgrade
            if (extension_loaded('mysqli')) {
                $query = @mysqli_query($conn, 'SELECT channel FROM handles');
            } else {
                $query = @mysql_query('SELECT channel FROM handles', $conn);
            }
            if (!$query) {
                $a = $this->updateDatabase(
                    '@data-dir@/Chiara_PEAR_Server/data/handles-channel-0.18.2.sql',
                    'updating database to add support for grouping handles by channel', $conn);
                if (!$a) {
                    return $a;
                }
                $this->fixHandles = true;
            }
            $upgraded = false;
            // channel field size upgrade
            if (extension_loaded('mysqli')) {
                $query = @mysqli_query($conn, 'SHOW COLUMNS FROM maintainers');
                while ($res = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                    if ($res['Field'] == 'channel') {
                        if ($res['Type'] == 'varchar(255)') {
                            $upgraded = true;
                        }
                    }
                }
            } else {
                $query = @mysql_query('SHOW COLUMNS FROM maintainers', $conn);
                while ($res = mysql_fetch_array($query, MYSQL_ASSOC)) {
                    if ($res['Field'] == 'channel') {
                        if ($res['Type'] == 'varchar(255)') {
                            $upgraded = true;
                        }
                    }
                }
            }
            if (!$upgraded) {
                $a = $this->updateDatabase(
                    '@data-dir@/Chiara_PEAR_Server/data/maintainers-channel-0.18.4.sql',
                    'updating database to increase size of channel field', $conn);
                if (!$a) {
                    return $a;
                }
            }
            // update the contents of package_extras
            $upgraded = false;
            if (extension_loaded('mysqli')) {
                $query = @mysqli_query($conn, 'SHOW COLUMNS FROM package_extras');
                while ($res = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                    if ($res['Field'] == 'qa_approval') {
                        $upgraded = true;
                        break;
                    }
                }
            } else {
                $query = @mysql_query('SHOW COLUMNS FROM package_extras', $conn);
                while ($res = mysql_fetch_array($query, MYSQL_ASSOC)) {
                    if ($res['Field'] == 'qa_approval') {
                        $upgraded = true;
                        break;
                    }
                }
            }
            if (!$upgraded) {
                $a = $this->updateDatabase(
                    '@data-dir@/Chiara_PEAR_Server/data/package_extras_missingvalues-0.18.5.sql',
                    'updating database to add missing fields to package_extras', $conn);
                if (!$a) {
                    return $a;
                }
            }
            // update the keys of package_extras
            $upgraded = true;
            if (extension_loaded('mysqli')) {
                $query = @mysqli_query($conn, 'SHOW COLUMNS FROM package_extras');
                while ($res = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                    if ($res['Field'] == 'package') {
                        if ($res['Null'] == 'YES') {
                            $upgraded = false;
                        }
                        break;
                    }
                }
            } else {
                $query = @mysql_query('SHOW COLUMNS FROM package_extras', $conn);
                while ($res = mysql_fetch_array($query, MYSQL_ASSOC)) {
                    if ($res['Field'] == 'package') {
                        if ($res['Null'] == 'YES') {
                            $upgraded = false;
                        }
                        break;
                    }
                }
            }
            if (!$upgraded) {
                $a = $this->updateDatabase(
                    '@data-dir@/Chiara_PEAR_Server/data/package_extras_keys-0.18.5.sql',
                    'updating database to add keys to package_extras', $conn);
                if (!$a) {
                    return $a;
                }
            }
            // update the NULL of package_extras new fields
            $upgraded = true;
            if (extension_loaded('mysqli')) {
                $query = @mysqli_query($conn, 'SHOW COLUMNS FROM package_extras');
                while ($res = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                    if ($res['Field'] == 'qa_approval') {
                        if ($res['Null'] == 'YES') {
                            $upgraded = false;
                        }
                        break;
                    }
                }
            } else {
                $query = @mysql_query('SHOW COLUMNS FROM package_extras', $conn);
                while ($res = mysql_fetch_array($query, MYSQL_ASSOC)) {
                    if ($res['Field'] == 'qa_approval') {
                        if ($res['Null'] == 'YES') {
                            $upgraded = false;
                        }
                        break;
                    }
                }
            }
            if (!$upgraded) {
                $a = $this->updateDatabase(
                    '@data-dir@/Chiara_PEAR_Server/data/package_extras_fixvalues-0.18.5.sql',
                    'updating database to make all fields non-null in package_extras', $conn);
                if (!$a) {
                    return $a;
                }
            }
            // REST support upgrade
            if (extension_loaded('mysqli')) {
                $query = @mysqli_query($conn, 'SELECT rest_support FROM channels');
            } else {
                $query = @mysql_query('SELECT rest_support FROM channels', $conn);
            }
            if (!$query) {
                $a = $this->updateDatabase(
                    '@data-dir@/Chiara_PEAR_Server/data/restsupport-0.18.0.sql',
                    'updating database to add REST xml support', $conn);
                if (!$a) {
                    return $a;
                }
            }
            // Categories support upgrade
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
        // Check to see if given database already exists
        // FIXME: This sequence needs to be re-worked in the switch to DB or MDB2 usage
        // to avoid the double *select_db calls.
        if (extension_loaded('mysqli')) {
            $query = mysqli_select_db($conn, $answers['database']);
            if (!$query) {
                $query = mysqli_query($conn, 'CREATE DATABASE ' . $answers['database']);
                if ($query) {
                    $query = mysqli_select_db($conn, $answers['database']);
                }
            }
        } else {
            $query = mysql_select_db($answers['database'], $conn);
            if (!$query) {
                $query = mysql_query('CREATE DATABASE ' . $answers['database'], $conn);
                if ($query) {
                    $query = mysql_select_db($answers['database'], $conn);
                }
            }
        }
        if ($query) {
            if (extension_loaded('mysqli')) {
                $query = mysqli_select_db($conn, $answers['database']);
            } else {
                $query = mysql_select_db($answers['database'], $conn);
            }
            if ($query) {
                $a = $this->updateDatabase('@data-dir@/Chiara_PEAR_Server/data/pearserver.sql',
                    'Creating Chiara_PEAR_Server database structure...', $conn);
                if (!$a) {
                    $this->closeDB($conn);
                    return false;
                }
                return true;
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
        $dbo->channel = $this->channel;
        if ($dbo->find()) {
            $update = true;
        } else {
            $update = false;
        }
        $dbo->summary = $answers['summary'];
        $dbo->alias = $answers['alias'];
        if (!$dbo->find()) {
            $dbo->channel = $this->channel;
            $dbo->summary = $answers['summary'];
            $dbo->alias = $answers['alias'];
            $result = ($update ? $dbo->update() : $dbo->insert());
            if (!$result) {
                $this->_ui->outputData('Creation of channel failed');
                return false;
            }
        }
        return true;
    }

    function setupAdministrator($answers)
    {
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
        $dbo = DB_DataObject::factory('handles');
        $dbo->handle = $this->handle;
        $dbo->channel = $this->channel;
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);
        if (!$dbo->find()) {
            $this->_ui->outputData('Add the primary administrator');
            $dbo->name = $answers['name'];
            $dbo->email = $answers['email'];
            $dbo->password = md5($answers['password']);
            $dbo->admin = 1;
            $dbo->channel = $this->channel;
            $a = $dbo->insert();
        } else {
            $dbo->name = $answers['name'];
            $dbo->email = $answers['email'];
            $dbo->password = md5($answers['password']);
            $dbo->admin = 1;
            $dbo->channel = $this->channel;
            $this->_ui->outputData('Update the primary administrator');
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
        $this->webroot = $answers['docroot'];
        $this->ssl = ($answers['ssl'] == 'https');
        $this->port = $answers['port'];
        $this->frontend = $answers['frontendphp'];
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
        if ($this->fixHandles) {
            $dbo = DB_DataObject::factory('handles');
            $dbo->channel = $this->channel;
            $dbo->update();
        }
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
        $chan->setBaseURL('REST1.0', 'http://' . $dbo->channel . '/Chiara_PEAR_Server_REST/');
        if ($this->port != 80) {
            $chan->setPort($this->port);
        }
        if ($this->ssl) {
            $chan->setSSL();
        }
        if (!$this->_registry->channelExists($dbo->channel)) {
            $this->_registry->addChannel($chan);
        } else {
            $this->_registry->updateChannel($chan);
        }
        $xml = $chan->toXml();
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

        // create frontend.php
        $extraconf = '';
        if (@file_exists($this->pearconfigloc)) {
            $extraconf = "require_once 'PEAR/Config.php';\n" .
                         'PEAR_Config::singleton("' . addslashes($this->pearconfigloc) . "\",\n" .
                         '"' . addslashes($this->pearconfigloc) . "\");\n";
        }
        $type = extension_loaded('mysqli') ? 'mysqli' : 'mysql';
        $config = 'array(\'database\' => \'' .
            $type . '://' . $this->user . ':' . $this->password . '@' . $this->dbhost . '/' . $this->db . '\')';
        $contents = '<?php
' . $extraconf . 'require_once \'Chiara/PEAR/Server.php\';
require_once \'Chiara/PEAR/Server/Backend/DBDataObject.php\';
require_once \'Chiara/PEAR/Server/Frontend/HTMLQuickForm.php\';
$backend = new Chiara_PEAR_Server_Backend_DBDataObject(\'' . $this->channel . '\',
    \'' . $answers['docroot'] . DIRECTORY_SEPARATOR . 'Chiara_PEAR_Server_REST\', ' . $config . ');
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
                '@data-dir@/Chiara_PEAR_Server/data/pear_server.css'), $answers['docroot'] .
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
        $this->goREST();
        return true;
    }

    function goREST()
    {
        include_once 'System.php';
        if (!class_exists('System')) {
            $this->_ui->outputData('System class required to create REST files');
            return false;
        }
        if (!file_exists($this->webroot)) {
            System::mkdir(array('-p', $this->webroot));
        }
        $restroot = $this->webroot .  DIRECTORY_SEPARATOR . 'Chiara_PEAR_Server_REST';
        if (!file_exists($restroot)) {
            System::mkdir($restroot);
            @chmod($this->webroot . DIRECTORY_SEPARATOR . 'Chiara_PEAR_Server_REST', 0777);
        }
        require_once 'Chiara/PEAR/Server/Backend/DBDataObject.php';
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
        $this->_ui->outputData('Enabling REST for channel ' . $this->channel);
        $chan = DB_DataObject::factory('channels');
        $chan->channel = $this->channel;
        if (!$chan->find(false)) {
            $this->ui->outputData('Unknown channel: ' . $this->channel);
            return false;
        }
        $chan->rest_support = 1;
        $chan->update();
        $this->_ui->outputData('Adding REST 1.0 and REST 1.1 to channel.xml');
        $chan = $this->_registry->getChannel($this->channel);
        if (is_a($chan, 'PEAR_ChannelFile')) {
            $chan->setBaseURL('REST1.0', 'http://' . $this->channel . '/Chiara_PEAR_Server_REST/');
            $chan->setBaseURL('REST1.1', 'http://' . $this->channel . '/Chiara_PEAR_Server_REST/');
            $this->_registry->updateChannel($chan);
            file_put_contents($this->webroot . DIRECTORY_SEPARATOR . 'channel.xml',
                $chan->toXml());
        } else {
            $this->_ui->outputData('Channel is not registered in local channel database');
            return false;
        }
        $channelinfo = parse_url($this->channel);
        $backend = new Chiara_PEAR_Server_Backend_DBDataObject($this->channel, $restroot, $options);
        // package information directory
        if (!file_exists($pdir = $restroot . DIRECTORY_SEPARATOR . 'p')) {
            System::mkdir(array('-p', $restroot . DIRECTORY_SEPARATOR . 'p'));
            @chmod($pdir, 0777);
        }
        // category information directory
        if (!file_exists($cdir = $restroot . DIRECTORY_SEPARATOR . 'c')) {
            System::mkdir(array('-p', $cdir));
            @chmod($cdir, 0777);
        }
        // release listing directory
        if (!file_exists($rdir = $restroot . DIRECTORY_SEPARATOR . 'r')) {
            System::mkdir(array('-p', $rdir));
            @chmod($rdir, 0777);
        }
        $categories = $backend->listCategories();
        $this->_ui->outputData('Saving Categories REST');
        foreach ($categories as $category) {
            $this->_ui->outputData('  Category ' . $category['name']);
            $backend->saveCategoryREST($category['name']);
        }
        $backend->saveAllCategoriesREST();
        $this->_ui->outputData("Saving All Maintainers REST");
        $backend->saveAllMaintainersREST();
        $this->_ui->outputData('Saving Maintainer REST');
        $maintainers = $backend->listMaintainers();
        foreach ($maintainers as $maintainer) {
            $this->_ui->outputData(  'Maintainer ' . $maintainer->handle);
            $backend->saveMaintainerREST($maintainer);
        }
        $this->_ui->outputData('Saving Package REST');
        $backend->saveAllPackagesREST();
        $packages = $backend->listPackages(false, false, false);
        foreach ($packages as $package) {
            $this->_ui->outputData('  Package ' . $package['package']);
            $backend->savePackageREST($package['package']);
            $this->_ui->outputData('    Maintainers...');
            $backend->savePackageMaintainersREST($package['package']);
            $releases = $backend->listReleases($package['package']);
            if (count($releases)) {
                $this->_ui->outputData('    Processing releases');
                $backend->saveAllReleasesREST($package['package']);
                foreach ($releases as $version => $release) {
                    $this->_ui->outputData('     Version ' . $version);
                    $backend->saveReleaseREST($package['package'], $version);
                    $backend->savePackageDepsREST($package['package'], $version,
                        $backend->getPackageFileObject($package['package'],
                        $version)->getDependencies());
                }
            }
        }
        $this->_ui->outputData("Saving Category Package REST");
        foreach ($backend->listCategories() as $category) {
            $this->_ui->outputData("  $category[name]");
            $backend->savePackagesCategoryREST($category['name']);
        }
    }
}
?>