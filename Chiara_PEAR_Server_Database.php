<?php

require_once 'MDB2.php';
require_once 'MDB2/Schema.php';
require_once 'DB/DataObject.php';


class Chiara_PEAR_Server_Database_postinstall
{
    var $_pkg;
    var $_ui;
    var $_config;
    var $_registry;
    var $dsn;
    var $db;
    var $dbtype;
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
        if (!$this->validPreviousVersion()) {
            $this->outputData('Your last version was '.$this->lastversion.'.
Database upgrades cannot be performed on versions less than 0.18.7.
You should upgrade to 0.18.7, first with:
pear upgrade -f chiara/Chiara_PEAR_Server-0.18.7
and run the post install script, then pear up chiara/Chiara_PEAR_Server.');
            return false;
        }
        return true;
    }
    
    function validPreviousVersion()
    {
        if (isset($this->lastversion)) {
            return !version_compare($this->lastversion,'0.18.7','<');
        } else {
            // New Install
            return true;
        }
    }
    
    function initDBDO()
    {
        $phpdir = str_replace('\\', '/', '@php-dir@');
        $datadir = str_replace('\\', '/', '@data-dir@');
        $options = &PEAR::getStaticProperty('DB_DataObject','options');
        $options = array(
            'database'         => $this->dsn,
            'schema_location'  => $datadir . '/Chiara_PEAR_Server/data/DBDataObject',
            'class_location'   => $phpdir . '/Chiara/PEAR/Server/Backend/DBDataObject/',
            'require_prefix'   => 'Chiara/PEAR/Server/Backend/DBDataObject/',
            'class_prefix'     => 'Chiara_PEAR_Server_Backend_DBDataObject_',
            'db_driver'        => 'MDB2',
        );
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
                $this->checkSetup($answers);
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
                /*
                foreach ($answers as $group) {
                    switch ($group) {
                        case 'questionCreate' :
                        break;
                        case 'databaseCreate' :
                            if ($this->lastversion || $this->databaseExists) {
                                // don't uninstall the database if it had already existed
                                break;
                            }
                            $mdb2 =& $this->getDBConnection();
                            if (!$mdb2) {
                                break;
                            }
                            $res = $mdb2->execute('DROP DATABASE ' . $this->db);
                        break;
                        case 'channelCreate' :
                            $mdb2 = $this->getDBConnection();
                            if (!$mdb2) {
                                break;
                            }
                            $res = $mdb2->execute('DELETE FROM channels WHERE channel="' .
                                        $this->channel . '"');
                        break;
                        case 'administrator' :
                            $mdb2 = $this->getDBConnection();
                            if (!$mdb2) {
                                break;
                            }
                            $res = $mdb2->execute('DELETE FROM handles WHERE handle="' .
                                        $this->handle . '"');
                        break;
                    }
                }
                */
            break;
        }
    }
    
    function getDBConnection($answers)
    {
        $this->dbtype     = $answers['dbtype'];
        $this->dbhost     = $answers['dbhost'];
        $this->db         = $answers['database'];
        $this->user       = $answers['user'];
        $this->password   = $answers['password'];
        $this->channel    = $answers['name'];
        $this->handle     = $answers['handle'];
        $this->dsn        = $answers['dbtype'].'://'.$answers['user'].':'.$answers['password'].'@'.$answers['dbhost'].'/'.$answers['database'];
        $mdb2 =& MDB2::connect($this->dsn);
        if (PEAR::isError($mdb2)) {
            $this->outputData('Connection to database failed:'.$mdb2->getMessage());
            return false;
        } else {
            return $mdb2;
        }
    }
    
    function checkSetup($answers)
    {
        $mdb2 =& $this->getDBConnection($answers);
        if (!$mdb2) {
            return false;
        }
        $this->initDBDO();
        $handles = DB_DataObject::factory('handles');
        $handles->handle = $this->handle;
        $handles->channel = $this->channel;
        if ($handles->count()) {
            $this->_ui->skipParamGroup('administrator');
            $this->outputData('Administrator already exists.');
        }
        $channels = DB_DataObject::factory('channels');
        $channels->channel = $this->channel;
        if ($channels->count()) {
            $this->_ui->skipParamGroup('channelCreate');
            $this->outputData('Channel has already been created.');
        }
        return true;
    }
    
    function updateDatabase($answers)
    {
        
        $this->outputData('Preparing table operations...');
        
        $db = $this->getDBConnection($answers);
        
        if (PEAR::isError($db)) {
            $this->outputData('Could not create database connection. "'.$db->getMessage().'"');
            $this->noDBsetup = true;
            return false;
        }
        
        $data_dir = '@data-dir@/Chiara_PEAR_Server';
        
        if ($answers['database'] != 'pearserver') {
            $a = self::file_str_replace('<name>pearserver</name>',
                                        '<name>'.$answers['database'].'</name>',
                                        $data_dir.'/database.xml');
            if ($a != true) {
                $this->noDBsetup = true;
                return $a;
            }
            
            $this->outputData('Copying DB_DataObject config file to "' .
                $data_dir . '/data/DBDataObject/' . $this->db . '.ini');
            copy($data_dir . '/data/DBDataObject/pearserver.ini',
                 $data_dir . '/data/DBDataObject/' . $this->db . '.ini');
        }
        
        $db->setOption('seqcol_name', 'id');
        $manager =& MDB2_Schema::factory($db);
        
        if (PEAR::isError($manager)) {
            $this->outputData($manager->getMessage() . ' ' . $manager->getUserInfo());
            $this->noDBsetup = true;
            return false;
        } else {
            
            $new_definition_file = $data_dir . '/database.xml';
            $old_definition_file = $data_dir . '/database.old';
            
            if (!file_exists($old_definition_file)
                && isset($this->lastversion)
                && $this->lastversion == '0.18.7'
                ) {
                // Previous database definition does not exist. Supply one for the 0.18.7 database.
                self::file_str_replace('<name>pearserver</name>',
                                       '<name>'.$answers['database'].'</name>',
                                       $data_dir . '/database-0.18.7.xml');
                copy($data_dir . '/database-0.18.7.xml', $old_definition_file);
            }
            
            if (file_exists($old_definition_file)) {
                $operation = $manager->updateDatabase($new_definition_file, $old_definition_file);
            } else {
                //Attempt an update of the existing database?
                $previous_definition = $manager->getDefinitionFromDatabase();
                $operation = $manager->updateDatabase($new_definition_file, $previous_definition);
            }
            
            
            if (PEAR::isError($operation)) {
                $this->outputData('There was an error updating the database.');
                $this->outputData($operation->getMessage() . ' ' . $operation->getDebugInfo());
                $this->noDBsetup = true;
            } else {
                $this->outputData('Successfully connected and created '.$this->dsn."\n");
                copy($new_definition_file, $old_definition_file);
                return true;
            }
        }
    }
    
    function createDatabase($answers)
    {
        
        $db = $this->getDBConnection($answers);
        
        if (PEAR::isError($db)) {
            $this->outputData('Could not create database connection. "'.$db->getMessage().'"');
            $this->noDBsetup = true;
            return false;
        }
        $this->outputData('Checking for existing database. . .');
        $sql = 'CREATE DATABASE IF NOT EXISTS '.$answers['database'];
        $result = $db->exec($sql);
        
        if (PEAR::isError($result)) {
            $this->outputData('Could not create database. "'.$result->getMessage().'" '.$result->getUserInfo().' '.$result->getDebugInfo());
            $this->noDBsetup = true;
            return false;
        }
        
        return $this->updateDatabase($answers);
    }
    
    /**
     * checks if the database exists already, or not
     *
     * @param string $db_name Database name
     * 
     * @return bool
     */
    function databaseExists($db_name)
    {
        $this->outputData('Checking for existing database, '.$db_name.'. . .');
        
        $db =& MDB2::factory($this->dsn);

        if (PEAR::isError($db)) {
            $this->outputData('There was an error connecting, you must resolve this issue before installation can complete.');
            $this->outputData($db->getUserinfo());
            die();
        }
        
        $exists = $db->databaseExists($db_name);
        if (PEAR::isError($exists)) {
            if ($exists->getMessage() == "MDB2 Error: no such database") {
                return false;
            }
            $this->outputData('There was an error checking the database, you must resolve this issue before installation can complete.');
            $this->outputData($exists->getUserinfo());
            die();
        } else {
            if ($exists) {
                return true;
            } else {
                return false;
            }
        }
    }
    
    function createChannel($answers)
    {
        $this->alias = $answers['alias'];
        $this->initDBDO();
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
            $dbo->rest_support = 1;
            $result = ($update ? $dbo->update() : $dbo->insert());
            if (!$result) {
                $this->outputData('Creation of channel failed');
                return false;
            }
        }
        return true;
    }
    
    function setupAdministrator($answers)
    {
        include_once 'DB/DataObject.php';
        if (!class_exists('DB_DataObject')) {
            $this->outputData('DB_DataObject is required to use Chiara_PEAR_Server');
            return false;
        }
        $this->initDBDO();
        $dbo = DB_DataObject::factory('handles');
        $dbo->handle = $this->handle;
        $dbo->channel = $this->channel;
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);
        if (!$dbo->find()) {
            $this->outputData('Add the primary administrator');
            $dbo->name     = $answers['name'];
            $dbo->email    = $answers['email'];
            $dbo->password = md5($answers['password']);
            $dbo->admin    = 1;
            $dbo->channel  = $this->channel;
            $a = $dbo->insert();
        } else {
            $dbo->name     = $answers['name'];
            $dbo->email    = $answers['email'];
            $dbo->password = md5($answers['password']);
            $dbo->admin    = 1;
            $dbo->channel  = $this->channel;
            $this->outputData('Update the primary administrator');
            $a = $dbo->update();
        }
        PEAR::popErrorHandling();
        if ($a === false || PEAR::isError($a)) {
            $this->outputData('Creation of admin user failed');
            if (PEAR::isError($a)) {
                PEAR::raiseError($a);
            }
            return false;
        } else {
            $this->outputData('Creation of admin user succeeded');
            return true;
        }
    }

    function createServerFiles($answers)
    {
        $this->pearconfigloc = $answers['pearconfigloc'];
        $this->webroot       = $answers['docroot'];
        $this->ssl           = ($answers['ssl'] == 'https');
        $this->port          = $answers['port'];
        $this->frontend      = $answers['frontendphp'];
        
        require_once 'DB/DataObject.php';
        
        $this->initDBDO();
        
        if ($this->fixHandles) {
            $dbo = DB_DataObject::factory('handles');
            $dbo->channel = $this->channel;
            $dbo->update();
        }
        
        $dbo = DB_DataObject::factory('channels');
        $dbo->channel = $this->channel;
        if (!$dbo->find(true)) {
            $this->outputData('Channel "' . $this->channel . '" does not exist in database?');
            return false;
        }
        $dbo->fetch();
        include_once 'PEAR/ChannelFile.php';
        
        $this->outputData('Adding channel to local PEAR registry. . .');
        
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
        
        if (!$chan->validate()) {
            $this->outputData('Sorry, the channel information you provided will not validate! '.
                'You most likely used an invalid character in your channel name. Here\'s the ' .
                'error information:');
            foreach ($chan->getErrors() as $error) {
                $this->outputData($error->message);
            }
            return false;
        }
        
        if (!$this->_registry->channelExists($dbo->channel)) {
            $this->_registry->addChannel($chan);
            $this->outputData('Channel added to local PEAR registry.');
        } else {
            $this->_registry->updateChannel($chan);
            $this->outputData('Channel in local PEAR registry updated.');
        }
        
        $xml = $chan->toXml();
        
        if (!file_exists($answers['docroot'])) {
            $this->outputData('Creating local document root.');
            mkdir($answers['docroot'], 0755, true);
        }
        
        $fp = @fopen($answers['docroot'] . DIRECTORY_SEPARATOR . 'channel.xml', 'w');
        if ($fp) {
            fwrite($fp, $xml);
            fclose($fp);
            $this->outputData('Saved ' . $answers['docroot'] . DIRECTORY_SEPARATOR . 'channel.xml');
        } else {
            $this->outputData('Cannot open "' . $answers['docroot'] . DIRECTORY_SEPARATOR .
                'channel.xml" for writing');
            return false;
        }
        
        if (!file_exists($answers['docroot'] . DIRECTORY_SEPARATOR . 'get')) {
            mkdir($answers['docroot'] . DIRECTORY_SEPARATOR . 'get', 0777);
            @chmod($answers['docroot'] . DIRECTORY_SEPARATOR . 'get', 0777);
            $this->outputData('Created /get/ directory.');
        }

        // create frontend.php
        $extraconf = '';
        if (@file_exists($this->pearconfigloc)) {
            $extraconf = "require_once 'PEAR/Config.php';\n" .
                         'PEAR_Config::singleton("' . addslashes($this->pearconfigloc) . "\",\n" .
                         '"' . addslashes($this->pearconfigloc) . "\");\n";
        }
        $config = 'array(\'database\'         => \'' .
            $this->dbtype . '://' . $this->user . ':' . $this->password . '@' . $this->dbhost .
            '/' . $this->db . '\')';
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
            $this->outputData('Successfully created ' .
                $answers['docroot'] . DIRECTORY_SEPARATOR . $answers['frontendphp']);
        } else {
            $this->outputData('Could not create ' .
                $answers['docroot'] . DIRECTORY_SEPARATOR . $answers['frontendphp']);
            return false;
        }
        if (!file_exists($answers['docroot'] . DIRECTORY_SEPARATOR . 'pear_server.css')) {
            $b = @copy(str_replace(array('/', '\\'), array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR),
                '@data-dir@/Chiara_PEAR_Server/data/pear_server.css'), $answers['docroot'] .
                DIRECTORY_SEPARATOR . 'pear_server.css');
            if ($b) {
                $this->outputData('Successfully created ' .
                    $answers['docroot'] . DIRECTORY_SEPARATOR . 'pear_server.css');
            } else {
                $this->outputData('Could not create ' .
                    $answers['docroot'] . DIRECTORY_SEPARATOR . 'pear_server.css');
                return false;
            }
        } else {
            $this->outputData(
                $answers['docroot'] . DIRECTORY_SEPARATOR . 'pear_server.css already exists');
        }
        $this->goREST();
        return true;
    }

    function goREST()
    {
        if (!file_exists($this->webroot)) {
            mkdir($this->webroot, NULL, true);
        }
        $restroot = $this->webroot .  DIRECTORY_SEPARATOR . 'Chiara_PEAR_Server_REST';
        if (!file_exists($restroot)) {
            mkdir($restroot, 0777, true);
            @chmod($restroot, 0777);
        }
        require_once 'Chiara/PEAR/Server/Backend/DBDataObject.php';
        $this->initDBDO();
        $options = &PEAR::getStaticProperty('DB_DataObject','options');
        $this->outputData('Enabling REST for channel ' . $this->channel);
        $chan = DB_DataObject::factory('channels');
        $chan->channel = $this->channel;
        if (!$chan->find()) {
            $this->ui->outputData('Unknown channel: ' . $this->channel);
            return false;
        }
        $chan->fetch();
        $chan->rest_support = 1;
        $chan->update();
        $this->outputData('Adding REST 1.0 and REST 1.1 to channel.xml');
        $chan = $this->_registry->getChannel($this->channel);
        if ($chan instanceof PEAR_ChannelFile) {
            $chan->setBaseURL('REST1.0', 'http://' . $this->channel . '/Chiara_PEAR_Server_REST/');
            $chan->setBaseURL('REST1.1', 'http://' . $this->channel . '/Chiara_PEAR_Server_REST/');
            $this->_registry->updateChannel($chan);
            file_put_contents($this->webroot . DIRECTORY_SEPARATOR . 'channel.xml',
                $chan->toXml());
        } else {
            $this->outputData('Channel is not registered in local channel database');
            return false;
        }
        $channelinfo = parse_url($this->channel);
        $backend = new Chiara_PEAR_Server_Backend_DBDataObject($this->channel, $restroot, $options);
        // package information directory
        if (!file_exists($pdir = $restroot . DIRECTORY_SEPARATOR . 'p')) {
            mkdir( $pdir, 0777);
            @chmod($pdir, 0777);
        }
        // category information directory
        if (!file_exists($cdir = $restroot . DIRECTORY_SEPARATOR . 'c')) {
            mkdir($cdir, 0777);
            @chmod($cdir, 0777);
        }
        // release listing directory
        if (!file_exists($rdir = $restroot . DIRECTORY_SEPARATOR . 'r')) {
            mkdir($rdir);
            @chmod($rdir, 0777);
        }
        $categories = $backend->listCategories();
        $this->outputData('Saving Categories REST');
        foreach ($categories as $category) {
            $this->outputData('  Category ' . $category['name']);
            $backend->saveCategoryREST($category['name']);
        }
        $backend->saveAllCategoriesREST();
        $this->outputData('Saving All Maintainers REST');
        $backend->saveAllMaintainersREST();
        $this->outputData('Saving Maintainer REST');
        $maintainers = $backend->listMaintainers();
        foreach ($maintainers as $maintainer) {
            $this->outputData(  'Maintainer ' . $maintainer->handle);
            $backend->saveMaintainerREST($maintainer);
        }
        $this->outputData('Saving Package REST');
        $backend->saveAllPackagesREST();
        $packages = $backend->listPackages(false, false, false);
        foreach ($packages as $package) {
            $this->outputData('  Package ' . $package['package']);
            $backend->savePackageREST($package['package']);
            $this->outputData('    Maintainers...');
            $backend->savePackageMaintainersREST($package['package']);
            $releases = $backend->listReleases($package['package']);
            if (count($releases)) {
                $this->outputData('    Processing releases');
                $backend->saveAllReleasesREST($package['package']);
                foreach ($releases as $version => $release) {
                    $this->outputData('     Version ' . $version);
                    $backend->saveReleaseREST($package['package'], $version);
                    $backend->savePackageDepsREST($package['package'], $version,
                        $backend->getPackageFileObject($package['package'],
                        $version)->getDependencies());
                }
            }
        }
        $this->outputData('Saving Category Package REST');
        foreach ($backend->listCategories() as $category) {
            $this->outputData("  $category[name]");
            $backend->savePackagesCategoryREST($category['name']);
        }
    }
    
    /**
     * takes in a string and sends it to the client.
     */
    function outputData($msg)
    {
        if (isset($this->_ui)) {
            $this->_ui->outputData($msg);
        } else {
            echo $msg;
        }
    }
    
    function file_str_replace($search,$replace,$file)
    {
        $a = true;
        if (is_array($file)) {
            foreach ($file as $f) {
                $a = self::file_str_replace($search,$replace,$f);
                if ($a != true) {
                    return $a;
                }
            }
        } else {
            if (file_exists($file)) {
                $contents = file_get_contents($file);
                $contents = str_replace($search,$replace,$contents);
    
                $fp = fopen($file, 'w');
                $a = fwrite($fp, $contents, strlen($contents));
                fclose($fp);
                if ($a) {
                    $this->outputData($file);
                    return true;
                } else {
                    $this->outputData('Could not update ' . $file);
                    return false;
                }
            } else {
                $this->outputData($file.' does not exist!');
            }
        }
    }
}
?>
