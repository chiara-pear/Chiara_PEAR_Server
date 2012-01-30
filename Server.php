<?php
require_once 'PEAR/ErrorStack.php';
require_once 'PEAR/ChannelFile.php';
require_once 'Chiara/PEAR/Server/Release.php';
require_once 'Chiara/PEAR/Server/Package.php';
require_once 'Chiara/PEAR/Server/Maintainer.php';
require_once 'Chiara/PEAR/Server/Exception.php';
require_once 'PEAR/Config.php';
require_once 'PEAR/Common.php';
require_once 'PEAR/Registry.php';
class Chiara_PEAR_Server
{
    protected $_backend;
    protected $_output;
    protected $_index =
        array(
            'addRelease',
            'addPackage',
            'deleteRelease',
            'getDownloadURL',
            'listPackages',
            'listReleases',
            'listLatestReleases',
            'packageInfo',
            'manageCategory',
            'addCategory',
        );
    protected $_releaseDir;

    public function __construct($releasedir)
    {
        $this->_releaseDir = $releasedir;
    }

    public function getMethod($index)
    {
        if (isset($this->_index[$index])) {
            return $this->_index[$index];
        }
        return false;
    }

    public function getMethodIndex($name)
    {
        $methods = array_flip($this->_index);
        if (isset($methods[$name])) {
            return $methods[$name];
        }
        return false;
    }

    public function addMethodIndex($method)
    {
        if ($this->getMethodIndex($method) === false) {
            $this->_index[] = $method;
        }
    }

    public function run()
    {
        $this->_output->main();
        $this->_output->getOutput();
    }

    /**
     * Set the backend for handling data.  The API is
     * documented in Chiara/PEAR/Server/Backend.php
     * @param Chiara_PEAR_Server_Backend
     */
    public function setBackend($handlerobj)
    {
        $this->_backend[$handlerobj->getChannel()] = $handlerobj;
    }

    public function getBackend($channel)
    {
        if (isset($this->_backend[$channel])) {
            return $this->_backend[$channel];
        }
    }

    /**
     * Set the frontend for outputting required information
     * about packages, releases, and other information.  The
     * API is documented in Chiara/PEAR/Server/Frontend.php
     * @param Chiara_PEAR_Server_Frontend
     */
    function setFrontend($outputobj)
    {
        $this->_output = $outputobj;
        $this->_output->setServer($this);
    }

    /**
     * @param string channel name
     * @return bool
     */
    protected function _validLocalChannel($channel)
    {
        if (!isset($this->_backend[$channel])) {
            throw new Chiara_PEAR_Server_ExceptionChannelNotLocal($channel);
        }
        return true;
    }

    /**
     * @param Chiara_PEAR_Server_Release
     */
    public function addRelease($release)
    {
        if ($this->_validLocalChannel($channel = $release->getChannel())) {
            if ($this->_backend[$channel]->validPackage($release->getPackage())) {
                if ($release->validate()) {
                    // copy .tgz and .tar to destination directory
                    $archive = $release->getFilepath();
                    $new = $this->_releaseDir . DIRECTORY_SEPARATOR .
                        $release->getPackage() . '-' . $release->getVersion();
                    $gp = gzopen($archive, 'rb');
                    $contents = '';
                    while(!gzeof($gp)) {
                        $contents .= gzread($gp, filesize($archive));
                    }
                    gzclose($gp);
                    $fp = fopen($new . '.tar', 'wb');
                    fwrite($fp, $contents);
                    fclose($fp);
                    copy($release->getFilepath(), $new . '.tgz');
                    unlink($release->getFilepath());
                    // for the database, save the .tgz
                    $release->setFilepath($new . '.tgz');
                } else {
                    return $this->_output->funcReturn(false,
                        'Chiara_PEAR_Server', 'addRelease');
                }
                return $this->_output->funcReturn(
                    $this->_backend[$channel]->saveRelease($release),
                    'Chiara_PEAR_Server', 'addRelease');
            }
        } else {
            return $this->_output->funcReturn(false, __CLASS__, __FUNCTION__);
        }
    }

    /**
     * @param Chiara_PEAR_Server_Release
     */
    public function deleteRelease($channel, $package, $version)
    {
        if ($this->_validLocalChannel($channel)) {
            if ($this->_backend[$channel]->validPackage($package)) {
                $path = $this->_backend[$channel]->getReleasePath($package, $version);
                if ($ret = $this->_backend[$channel]->deleteRelease($package, $version)) {
                    unlink($path);
                    unlink(str_replace('.tgz', '.tar', $path));
                }
                return $this->_output->funcReturn(
                    $ret,
                    'Chiara_PEAR_Server', 'deleteRelease');
            }
        } else {
            return $this->_output->funcReturn(false, __CLASS__, __FUNCTION__);
        }
    }

    /**
     * @param array an array in format:
     *              array(
     *                'channel' => channel name (not used in pear),
     *                'package' => package name,
     *                ['version' => specific version to retrieve,]
     *                ['state' => specific state to retrieve,]
     *                ['bundle' => specific bundle to retrieve,]
     *              )
     * @param string preferred_state configuration value
     * @param string installed version of dependency
     * @return false|array false if no releases of this package, or
     *           an array of array(version, release info) of the latest
     *           release on error, or array(version, release info, download url)
     *           for successful retrieval
     */
    public function packageGetDownloadURL($packageinfo, $prefstate = 'stable',
                                          $installed = false)
    {
        if (!isset($packageinfo['channel']) || !isset($packageinfo['package'])) {
            throw new Chiara_PEAR_Server_ExceptionNeedChannel();
        }
        $channel = $packageinfo['channel'];
        $package = $packageinfo['package'];
        if ($this->_validLocalChannel($channel)) {
            if ($this->_backend[$channel]->validPackage($package)) {
                $package = $this->_backend[$channel]->getPackage($package)->name;
                $states = $this->_betterStates($prefstate, true);
                if (!$states) {
                    throw new Chiara_PEAR_Server_ExceptionInvalidState($prefstate);
                }
                $state = $version = null;
                $package = $packageinfo['package'];
                $state = $version = null;
                if (isset($packageinfo['state'])) {
                    $state = $packageinfo['state'];
                }
                if (isset($packageinfo['version'])) {
                    $version = $packageinfo['version'];
                }
                $channelinfo = $this->_backend[$channel]->channelInfo();
                $ret = 'http://' . $channelinfo['channel'] . '/get/' . $package . '-';
                $info = $this->_backend[$channel]->listReleases($package);
                if (!count($info)) {
                    return $this->_output->funcReturn(false, __CLASS__, __FUNCTION__);
                }
                $found = false;
                $release = false;
                foreach ($info as $ver => $release) {
                    if ($installed && version_compare($ver, $installed, '<')) {
                        continue;
                    }
                    if (isset($state)) {
                        if ($release['state'] == $state) {
                            $found = true;
                            break;
                        }
                    } elseif (isset($version)) {
                        if ($ver == $version) {
                            $found = true;
                            break;
                        }
                    } else {
                        if (in_array($release['state'], $states)) {
                            $found = true;
                            break;
                        }
                    }
                }
                if ($found) {
                    return 
                        $this->_output->funcReturn(array('version' => $ver,
                              'info' => $this->_backend[$channel]->getPackagexml($package, $ver), 
                              'url' => $ret . $ver),
                              __CLASS__, __FUNCTION__);
                } else {
                    reset($info);
                    list($ver, $release) = each($info);
                    return
                        $this->_output->funcReturn(array('version' => $ver,
                              'info' => $this->_backend[$channel]->getPackagexml($package, $ver)),
                              __CLASS__, __FUNCTION__);
                }
            }
        }
    }

    /**
     * Get a download URL for a dependency, or an array containing the
     * latest version and its release info.
     *
     * If a bundle is specified, then an array of information
     * will be returned
     * @param string package.xml version for the dependency (1.0 or 2.0)
     * @param array dependency information
     * @param array dependent package information
     * @param string preferred state
     * @param string version_compare() relation to use for checking version
     * @param string installed version of dependency
     * @return bool|array
     */
    function packageGetDepDownloadURL($channel, $xsdversion, $dependency, $deppackage,
                               $prefstate = 'stable', $installed = false)
    {
        if ($xsdversion != '2.0') {
            throw new Chiara_PEAR_Server_ExceptionXsdversion1_0;
        }
        $channel = $dependency['channel'];
        $package = $dependency['name'];
        if ($this->_validLocalChannel($channel)) {
            if ($this->_backend[$channel]->validPackage($package)) {
                $package = $this->_backend[$channel]->getPackage($package)->name;
                $states = $this->_betterStates($prefstate, true);
                if (!$states) {
                    throw new Chiara_PEAR_Server_ExceptionInvalidState($prefstate);
                }
                $channelinfo = $this->_backend[$channel]->channelInfo();
                $ret = 'http://' . $channelinfo['channel'] . '/get/' . $package . '-';
                $info = $this->_backend[$channel]->listReleases($package);
                if (!count($info)) {
                    return false;
                }
                $exclude = array();
                $min = $max = $recommended = false;
                if ($xsdversion == '2.0') {
                    $pinfo['package'] = $dependency['name'];
                    $min = isset($dependency['min']) ? $dependency['min'] : false;
                    $max = isset($dependency['max']) ? $dependency['max'] : false;
                    $recommended = isset($dependency['recommended']) ?
                        $dependency['recommended'] : false;
                    if (isset($dependency['exclude'])) {
                        if (!isset($dependency['exclude'][0])) {
                            $exclude = array($dependency['exclude']);
                        }
                    }
                }
                $found = false;
                $release = false;
                foreach ($info as $ver => $release) {
                    if (in_array($ver, $exclude)) { // skip excluded versions
                        continue;
                    }
                    // allow newer releases to say "I'm OK with the dependent package"
                    if (isset($release['compatibility'])) {
                        if (isset($release['compatibility'][$deppackage['channel']]
                              [$deppackage['package']]) && in_array($ver,
                                $release['compatibility'][$deppackage['channel']]
                                [$deppackage['package']])) {
                            $recommended = $ver;
                        }
                    }
                    if ($recommended) {
                        if ($ver != $recommended) { // if we want a specific
                            // version, then skip all others
                            continue;
                        } else {
                            if (!in_array($release['state'], $states)) {
                                // the stability is too low, but we must return the
                                // recommended version if possible
                                return 
                                    $this->_output->funcReturn(array('version' => $ver,
                                          'info' => $this->_backend[$channel]->getPackagexml($package, $ver)),
                                          __CLASS__, __FUNCTION__);
                            }
                        }
                    }
                    if ($min && version_compare($ver, $min, 'lt')) { // skip too old versions
                        continue;
                    }
                    if ($max && version_compare($ver, $max, 'gt')) { // skip too new versions
                        continue;
                    }
                    if ($installed && version_compare($ver, $installed, '<')) {
                        continue;
                    }
                    if (in_array($release['state'], $states)) { // if in the preferred state...
                        $found = true; // ... then use it
                        break;
                    }
                }
                if ($found) {
                    return 
                        $this->_output->funcReturn(array('version' => $ver,
                              'info' => $this->_backend[$channel]->getPackagexml($package, $ver), 
                              'url' => $ret . $ver),
                              __CLASS__, __FUNCTION__);
                } else {
                    reset($info);
                    list($ver, $release) = each($info);
                    return 
                        $this->_output->funcReturn(array('version' => $ver,
                              'info' => $this->_backend[$channel]->getPackagexml($package, $ver)),
                              __CLASS__, __FUNCTION__);
                }
            }
        }
    }

    /**
     * Return an array containing all of the states that are more stable than
     * or equal to the passed in state
     *
     * @param string Release state
     * @param boolean Determines whether to include $state in the list
     * @return false|array False if $state is not a valid release state
     * @access private
     */
    protected function _betterStates($state, $include = false)
    {
        static $states = array('snapshot', 'devel', 'alpha', 'beta', 'stable');
        $i = array_search($state, $states);
        if ($i === false) {
            return false;
        }
        if ($include) {
            $i--;
        }
        return array_slice($states, $i + 1);
    }

    public function packageListAll($channel, $releasedOnly = true, $onlyStable = true, $nosubpackages = true)
    {
        if ($this->_validLocalChannel($channel)) {
            if (!$releasedOnly) {
                // This kludge is forced by crappy implementation at pearweb
                $onlyStable = false;
            }
            $packages = $this->_backend[$channel]->listPackages($releasedOnly,
                $onlyStable, $nosubpackages);
            $ret = array();
            foreach ($packages as $package) {
                // also return the version number of the latest stable release
                // and the version number/state of the latest unstable release
                // iff it is newer than the stable release
                $stablereleases = $this->_backend[$channel]->listReleases($package['package'], true);
                if ($stablereleases && count($stablereleases)) {
                    list($package['stable'], $info) = each($stablereleases);
                    $package['state'] = $info['state'];
                    $package['unstable'] = false;
                }
                if (!$onlyStable) {
                    $releases = $this->_backend[$channel]->listReleases($package['package']);
                    if ($releases) {
                        foreach ($releases as $version => $info) {
                            if ($info['state'] == 'stable') {
                                break;
                            }
                            $package['unstable'] = $version;
                            // kludge to match pearweb idiocy
                            $package['stable'] = $version;
                            $package['state'] = $info['state'];
                            break;
                        }
                    }
                }
                $package['deps'] = array();
                // list deps as well, but only if releases exist
                if (isset($package['unstable'])) {
                    $temp = $this->_backend[$channel]->listDeps(
                        $package['package'],
                        $package['unstable'] ? $package['unstable'] : $package['stable']);
                    foreach ($temp as $dep) {
                        // this file location is only for the server, and
                        // is a potential security hole since the directory
                        // must be writeable by the webserver
                        unset($dep['fullpath']);
                        $package['deps'][] = $dep;
                    }
                }
                $package['category'] = 'Packages';
                $ret[$package['package']] = $package;
            }
            return $this->_output->funcReturn($ret, __CLASS__, __FUNCTION__);
        }
    }

    public function packageSearch($channel, $fragment, $summary = false, $releasedOnly = true,
                                  $onlyStable = true, $include_pecl = false, $nosubpackages = false)
    {
        if ($this->_validLocalChannel($channel)) {
            $all = $this->packageListAll($channel, $releasedOnly, $onlyStable, $include_pecl,
                $nosubpackages, true);
            $ret = array();
            foreach ($all as $name => $info) {
                $found = (!empty($fragment) && stristr($name, $fragment) !== false);
                if (!$found && !(isset($summary) && !empty($summary)
                    && (stristr($info['summary'], $summary) !== false
                        || stristr($info['description'], $summary) !== false)))
                {
                    continue;
                };
                $ret[$name] = $info;
            }
            return $ret;
        }
    }

    public function listReleases($channel, $package)
    {
        if ($this->_validLocalChannel($channel)) {
            if ($this->_backend[$channel]->validPackage($package)) {
                return $this->_output->funcReturn(
                        $this->_backend[$channel]->listReleases($package),
                    __CLASS__, __FUNCTION__);
            }
        }
    }


    /**
     * List latest releases
     *
     * @param  string Only list release with specific state
     * @return array
     */
    public function packageListLatestReleases($channel, $state = 'stable')
    {
        if ($this->_validLocalChannel($channel)) {
            $packages = $this->_backend[$channel]->listPackages(true, $state == 'stable');
            $ret = array();
            foreach ($packages as $package) {
                $release = array_shift($this->_backend[$channel]->listReleases(
                    $package['package'], $state == 'stable'));
                $release['deps'] = unserialize($release['deps']);
                $release['filesize'] = $this->_backend[$channel]->getFileSize(
                    $release['filepath']);
                unset($release['filepath']);
                $ret[$package['package']] = $release;
            }
            return $this->_output->funcReturn($ret, __CLASS__, __FUNCTION__);
        }
    }

    public function packageInfo($channel, $package, $key = null)
    {
        if ($this->_validLocalChannel($channel)) {
            if ($this->_backend[$channel]->validPackage($package)) {
                $info = $this->_backend[$channel]->packageInfo($package, $key);
                if ($key === null) {
                    foreach ($info['releases'] as $i => $release) {
                        unset($info['releases'][$i]['packagexml']);
                        unset($info['releases'][$i]['fullpath']);
                    }
                }
                return $this->_output->funcReturn(
                    $info,
                    __CLASS__, __FUNCTION__);
            }
        }
    }

    public function channelListAll()
    {
        foreach ($this->_backend as $channel => $backend) {
            $ret[] = array($channel);
        }
        return $this->_output->funcReturn($ret, __CLASS__, __FUNCTION__);
    }
}
?>