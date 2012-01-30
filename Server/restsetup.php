<?php
class Server_restsetup_postinstall
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
            case 'processExistingReleases' :
                return $this->goREST($answers);
            break;
            case '_undoOnError' :
            break;
        }
    }

    function goREST($answers)
    {
        $this->dbhost = $answers['dbhost'];
        $this->db = $answers['database'];
        $this->user = $answers['user'];
        $this->password = $answers['password'];
        $this->channel = $answers['name'];
        $this->webroot = $answers['webroot'];
        include_once 'System.php';
        if (!class_exists('System')) {
            $this->_ui->outputData('System class required to create REST files');
            return false;
        }
        if (!file_exists($answers['webroot'])) {
            System::mkdir(array('-p', $answers['webroot']));
        }
        $restroot = $answers['webroot'] .  DIRECTORY_SEPARATOR . 'Chiara_PEAR_Server_REST';
        if (!file_exists($restroot)) {
            System::mkdir($restroot);
            @chmod($answers['webroot'] . DIRECTORY_SEPARATOR . 'Chiara_PEAR_Server_REST', 0777);
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
        $this->_ui->outputData('Adding REST 1.0 to channel.xml');
        $chan = $this->_registry->getChannel($this->channel);
        if (is_a($chan, 'PEAR_ChannelFile')) {
            $chan->setBaseURL('REST1.0', 'http://' . $this->channel . '/Chiara_PEAR_Server_REST/');
            $this->_registry->updateChannel($chan);
            file_put_contents($answers['webroot'] . DIRECTORY_SEPARATOR . 'channel.xml',
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
            $backend->savePackageMaintainersREST($package['package']);
            $releases = $backend->listReleases($package['package']);
            if (count($releases)) {
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
    }
}
?>