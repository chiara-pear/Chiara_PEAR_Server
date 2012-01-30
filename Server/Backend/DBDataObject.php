<?php
require_once 'DB/DataObject.php';
require_once 'Chiara/PEAR/Server/Backend.php';
require_once 'Chiara/PEAR/Server/Exception.php';
/**
 * @version $Id: DBDataObject.php,v 1.40 2005/05/02 17:38:56 cellog Exp $
 * @author Greg Beaver <cellog@php.net>
 */
class Chiara_PEAR_Server_Backend_DBDataObject extends Chiara_PEAR_Server_Backend
{
    public function __construct($channel, $restdir = false, $config = array())
    {
        parent::__construct($channel, $restdir);
        $options = &PEAR::getStaticProperty('DB_DataObject','options');
        $options = array_merge(array(
            'database'         => 'mysql://pear:pear@localhost/pearserver',
            'schema_location'  => '@data-dir@/Chiara_PEAR_Server/data/DBDataObject',
            'class_location'   => '@php-dir@/Chiara/PEAR/Server/Backend/DBDataObject/',
            'require_prefix'   => 'Chiara/PEAR/Server/Backend/DBDataObject/',
            'class_prefix'     => 'Chiara_PEAR_Server_Backend_DBDataObject_',
        ), $config);
    }

    public function isAdmin($user)
    {
        $handles = DB_DataObject::factory('handles');
        $handles->handle = $user;
        $handles->admin = 1;
        return $handles->find();
    }

    public function isDeprecatedPackage($channel, $package)
    {
        $packages = DB_DataObject::factory('packages');
        $packages->channel = $channel;
        $packages->package = $package;
        if ($packages->find(true)) {
            if ($packages->deprecated_package) {
                return array('channel' => $packages->deprecated_channel,
                             'package' => $packages->deprecated_package);
            }
        } else {
            throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($package, $channel);
        }
    }

    public function validLogin($user, $password)
    {
        $handles = DB_DataObject::factory('handles');
        $handles->handle = $user;
        $handles->password = md5($password);
        return $handles->find();
    }

    public function channelInfo()
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        if (!$channel->find(true)) {
            throw new Chiara_PEAR_Server_ExceptionChannelSetup($channel);
        }
        return $channel->toArray();
    }

    public function getFilesize($fullpath)
    {
        return (int)filesize($fullpath);
    }


    public function validPackage($packagename)
    {
        $package = DB_DataObject::factory('packages');
        $package->channel = $this->_channel;
        $package->package = $packagename;
        return $package->find();
    }

    public function getReleasePath($packagename, $version)
    {
        $releases = DB_DataObject::factory('releases');
        $releases->package = $packagename;
        $releases->channel = $this->_channel;
        $releases->version = $version;
        if (!$releases->find(true)) {
            throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($packagename, $this->_channel);
        }
        return $releases->filepath;
    }

    public function deleteRelease($packagename, $version)
    {
        $releases = DB_DataObject::factory('releases');
        $releases->package = $packagename;
        $releases->channel = $this->_channel;
        if (!$releases->find()) {
            throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($packagename, $this->_channel);
        }
        $releases->version = $version;
        if (!$releases->find()) {
            return false;
        }
        $ret = ($releases->delete() !== false);
        if ($ret) {
            $this->deleteReleaseREST($packagename, $version);
        }
        return $ret;
    }

    public function saveRelease(Chiara_PEAR_Server_Release $release)
    {
        if ($release->getChannel() != $this->_channel) {
            return false;
        }
        $package = DB_DataObject::factory('packages');
        $package->channel = $this->_channel;
        $package->package = $release->getPackage();
        if (!$package->find()) {
            return false;
        }
        $maintainers = DB_DataObject::factory('maintainers');
        $maintainers->handle = $release->getReleasingMaintainer();
        $maintainers->package = $package->package;
        $maintainers->channel = $this->_channel;
        if (!$maintainers->find()) {
            throw new Chiara_PEAR_Server_ExceptionDoesntMaintain($maintainers->handle, $package->package, $this->_channel);
        }
        unset($maintainers->handle); // retrieve all maintainers
        $maintainers->find();
        $test = array();
        while ($maintainers->fetch()) {
            if ($maintainers->handle == $release->getReleasingMaintainer() && $maintainers->role != 'lead') {
                throw new Chiara_PEAR_Server_ExceptionMaintainerNotLead($maintainers->handle, $package->package, $this->_channel);
            }
            $test[$maintainers->handle] = $maintainers->toArray();
        }
        foreach ($release->getMaintainers() as $maintainer) {
            // add or update all active maintainers
            $maintainers->handle = $maintainer['handle'];
            $maintainers->name = $maintainer['name'];
            $maintainers->email = $maintainer['email'];
            $maintainers->active = 1;
            if (isset($test[$maintainer['handle']])) {
                $existrole = $test[$maintainer['handle']]['role'];
                unset($test[$maintainer['handle']]);
                if ($existrole != 'lead') {
                    // do not allow demotion through a release, only through web frontend
                    $maintainers->role = $maintainer['role'];
                }
                $maintainers->update();
            } else {
                $maintainers->role = $maintainer['role'];
                $maintainers->insert();
            }
        }
        foreach ($test as $maintainer) {
            // mark all maintainers removed from a package.xml as inactive
            $maintainers->handle = $maintainer['handle'];
            $maintainers->name = $maintainer['name'];
            $maintainers->email = $maintainer['email'];
            $maintainers->active = 0;
            $maintainers->role = $maintainer['role'];
            $maintainers->update();
        }
        $releasedata = DB_DataObject::factory('releases');
        $releasedata->channel = $this->_channel;
        $releasedata->package = $release->getPackage();
        $releasedata->version = $release->getVersion();
        $releasedata->state = $release->getState();
        $releasedata->maintainer = $release->getReleasingMaintainer();
        $releasedata->license = $release->getLicense();
        $releasedata->summary = $release->getSummary();
        $releasedata->description = $release->getDescription();
        $releasedata->releasenotes = $release->getNotes();
        $releasedata->releasedate = date('Y-m-d H:i:s');
        $releasedata->filepath = $release->getFilePath();
        $releasedata->deps = serialize($release->getDeps(false));
        $releasedata->packagexml = $release->getXml();
        $ret = $releasedata->insert();
        if ($ret) {
            $this->savePackageDepsREST($releasedata->package, $releasedata->version,
                $release->getDependencies());
            $this->savePackageREST($releasedata->package);
            $this->savePackageMaintainersREST($releasedata->package);
            $this->saveReleaseREST($releasedata->package, $releasedata->version);
            $this->saveAllReleasesREST($releasedata->package);
        }
        return $ret;
    }

    public function savePackageDepsREST($package, $version, $deps)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $rdir = $this->_restdir . DIRECTORY_SEPARATOR . 'r';
            if (!file_exists($rdir . DIRECTORY_SEPARATOR . strtolower($package))) {
                System::mkdir(array('-p', $rdir . DIRECTORY_SEPARATOR . strtolower($package)));
                @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package), 0777);
            }

            file_put_contents($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'deps.' . $version . '.txt', serialize($deps));
            @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'deps.' . $version . '.txt', 0666);
        }
    }

    public function saveAllPackagesREST()
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $pdir = $this->_restdir . DIRECTORY_SEPARATOR . 'p';

            $info = '<?xml version="1.0"?>
<a xmlns="http://pear.php.net/dtd/rest.allpackages"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.allpackages
    http://pear.php.net/dtd/rest.allpackages.xsd">
 <c>' . htmlspecialchars($this->_channel) . '</c>
';
            foreach ($this->listPackages(false, false, false) as $package)
            {
                $info .= ' <p>' . $package['package'] . '</p>
';
            }
            $info .= '</a>';
            file_put_contents($pdir . DIRECTORY_SEPARATOR . 'packages.xml', $info);
            @chmod($pdir . DIRECTORY_SEPARATOR . 'packages.xml', 0666);
        }
    }

    public function savePackageMaintainersREST($package)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $maintainers = DB_DataObject::factory('maintainers');
            $maintainers->package = $package;
            $maintainers->channel = $this->_channel;
            if ($maintainers->find(false)) {
                $pdir = $this->_restdir . DIRECTORY_SEPARATOR . 'p';
                if (!file_exists($pdir . DIRECTORY_SEPARATOR . strtolower($package))) {
                    System::mkdir(array('-p', $pdir . DIRECTORY_SEPARATOR . strtolower($package)));
                    @chmod($pdir . DIRECTORY_SEPARATOR . strtolower($package), 0777);
                }
                $info = '<?xml version="1.0"?>
<m xmlns="http://pear.php.net/dtd/rest.packagemaintainers"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.packagemaintainers
    http://pear.php.net/dtd/rest.packagemaintainers.xsd">
 <p>' . $package . '</p>
 <c>' . htmlspecialchars($this->_channel) . '</c>
';
                while ($maintainers->fetch()) {
                    $info .= ' <m><h>' . $maintainers->handle . '</h><a>' . $maintainers->active .
                        '</a></m>';
                }
                $info .= '</m>';
                file_put_contents($pdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'maintainers.xml', $info);
                @chmod($pdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'maintainers.xml', 0666);
            } else {
                @unlink($pdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'maintainers.xml', $info);
            }
        }
    }

    public function saveAllReleasesREST($package)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $releasedata = DB_DataObject::factory('releases');
            $releasedata->channel = $this->_channel;
            $releasedata->package = $package;
            $releasedata->orderby('releasedate DESC');
            $rdir = $this->_restdir . DIRECTORY_SEPARATOR . 'r';
            if (!$releasedata->find(false)) {
                // remove stragglers if no releases are found
                @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package));
                return;
            }
            $info = '<?xml version="1.0"?>
<a xmlns="http://pear.php.net/dtd/rest.allreleases"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.allreleases
    http://pear.php.net/dtd/rest.allreleases.xsd">
 <p>' . $package . '</p>
 <c>' . $this->_channel . '</c>
';
            while ($releasedata->fetch()) {
                if (!isset($latest)) {
                    $latest = $releasedata->version;
                }
                if ($releasedata->state == 'stable' && !isset($stable)) {
                    $stable = $releasedata->version;
                }
                if ($releasedata->state == 'beta' && !isset($beta)) {
                    $beta = $releasedata->version;
                }
                if ($releasedata->state == 'alpha' && !isset($alpha)) {
                    $alpha = $releasedata->version;
                }
                $info .= ' <r><v>' . $releasedata->version . '</v><s>' . $releasedata->state . '</s></r>
';
            }
            $info .= '</a>';
            if (!file_exists($rdir . DIRECTORY_SEPARATOR . strtolower($package))) {
                System::mkdir(array('-p', $rdir . DIRECTORY_SEPARATOR . strtolower($package)));
                @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package), 0777);
            }
            file_put_contents($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'allreleases.xml', $info);
            @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'allreleases.xml', 0666);

            file_put_contents($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'latest.txt', $latest);
            @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'latest.txt', 0666);
            // remove .txt in case all releases of this stability were deleted
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'stable.txt');
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'beta.txt');
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'alpha.txt');
            if (isset($stable)) {
                file_put_contents($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'stable.txt', $stable);
                @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'stable.txt', 0666);
            }
            if (isset($beta)) {
                file_put_contents($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'beta.txt', $beta);
                @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'beta.txt', 0666);
            }
            if (isset($alpha)) {
                file_put_contents($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'alpha.txt', $alpha);
                @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                    DIRECTORY_SEPARATOR . 'alpha.txt', 0666);
            }
        }
    }

    public function deleteReleaseREST($package, $version)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $rdir = $this->_restdir . DIRECTORY_SEPARATOR . 'r';
            if (!file_exists($rdir . DIRECTORY_SEPARATOR . strtolower($package))) {
                return;
            }
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . $version . '.xml');
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'deps.' . $version . '.txt');
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'package.' . $version . '.xml');
            $this->saveAllReleasesREST($package);
        }
    }

    public function saveReleaseREST($package, $version)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $releasedata = DB_DataObject::factory('releases');
            $releasedata->channel = $this->_channel;
            $releasedata->package = $package;
            $releasedata->version = $version;
            if (!$releasedata->find(true)) {
                throw new PEAR_Server_ExceptionReleaseNotFound($package, $version);
            }
            $release = $releasedata->toArray();
            $rdir = $this->_restdir . DIRECTORY_SEPARATOR . 'r';

            if (!file_exists($rdir . DIRECTORY_SEPARATOR . strtolower($package))) {
                System::mkdir(array('-p', $rdir . DIRECTORY_SEPARATOR . strtolower($package)));
                @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package), 0777);
            }

            $info = '<?xml version="1.0"?>
<r xmlns="http://pear.php.net/dtd/rest.release"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.release
    http://pear.php.net/dtd/rest.release.xsd">
 <p xlink:href="' . $extra . 'p/' . strtolower($release['package']) . '">' . $release['package'] . '</p>
 <c>' . $release['channel'] . '</c>
 <v>' . $version . '</v>
 <st>' . $release['state'] . '</st>
 <l>' . $release['license'] . '</l>
 <m>' . $release['maintainer'] . '</m>
 <s>' . htmlspecialchars($release['summary']) . '</s>
 <d>' . htmlspecialchars($release['description']) . '</d>
 <da>' . $release['releasedate'] . '</da>
 <n>' . htmlspecialchars($release['releasenotes']) . '</n>
 <f>' . filesize($release['filepath']) . '</f>
 <g>http://' . $this->_channel . '/get/' . $release['package'] . '-' . $release['version'] . '</g>
 <x xlink:href="package.' . $version . '.xml"/>
</r>';
            file_put_contents($rdir . DIRECTORY_SEPARATOR . strtolower($release['package']) .
                DIRECTORY_SEPARATOR . $version . '.xml', $info);
            @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($release['package']) .
                DIRECTORY_SEPARATOR . $version . '.xml', 0666);
            file_put_contents($rdir . DIRECTORY_SEPARATOR . strtolower($release['package']) .
                DIRECTORY_SEPARATOR . 'package.' .
                $version . '.xml', $this->getPackageXml($release['package'], $version));
            @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($release['package']) .
                DIRECTORY_SEPARATOR . 'package.' . $version . '.xml', 0666);
        }
    }

    public function releaseExists(Chiara_PEAR_Server_Release $release)
    {
        if ($release->getChannel() != $this->_channel) {
            return false;
        }
        $releasedata = DB_DataObject::factory('releases');
        $releasedata->channel = $this->_channel;
        $releasedata->package = $release->getPackage();
        $releasedata->version = $release->getVersion();
        return $releasedata->find();
    }

    public function getPackageXml($package, $version)
    {
        $releases = DB_DataObject::factory('releases');
        $releases->channel = $this->_channel;
        $releases->package = $package;
        $releases->version = $version;
        if ($releases->find(true)) {
            if (strpos($releases->packagexml, 'version=\\"1.0\\"')) {
                $releases->packagexml = stripslashes($releases->packagexml);
            }
            return $releases->packagexml;
        }
    }

    public function getPackageFileObject($package, $version)
    {
        require_once 'PEAR/PackageFile.php';
        $config = PEAR_Config::singleton();
        $pkg = new PEAR_PackageFile($config);
        return $pkg->fromXmlString($this->getPackageXml($package, $version), PEAR_VALIDATE_DOWNLOADING,
            '');
    }

    public function listPackages($releasedOnly = true, $onlyStable = true, $nosubpackages = true)
    {
        $packages = DB_DataObject::factory('packages');
        $packages->channel = $this->_channel;
        $packages->orderby('package');
        $packages->find(false);
        $ret = array();
        while ($packages->fetch()) {
            if ($releasedOnly) {
                if (!$packages->hasReleases()) {
                    continue;
                }
                if ($onlyStable) {
                    $ok = false;
                    if (!isset($releasedata)) {
                        $save = $releasedata = DB_DataObject::factory('releases');
                    } else {
                        $releasedata = $save;
                    }
                    $releasedata->channel = $this->_channel;
                    $releasedata->package = $packages->package;
                    $releasedata->orderby('releasedate');
                    if (!$releasedata->find()) {
                        continue;
                    }
                    if ($releasedata->state != 'stable') {
                        while ($releasedata->fetch()) {
                            if ($releasedata->state == 'stable') {
                                $ok = true;
                                break;
                            }
                        }
                        if (!$ok) {
                            continue;
                        }
                    }
                }
            }
            if ($nosubpackages) {
                if ($packages->parent) {
                    continue;
                }
            }
            $ret[] = $packages->toArray();
        }
        return $ret;
    }

    public function deleteCategory($category)
    {
        if ($category == 'Default') {
            return false;
        }
        $categories = DB_DataObject::factory('categories');
        $categories->name = $category;
        $categories->channel = $this->_channel;
        if (!$categories->find()) {
            throw new Chiara_PEAR_Server_ExceptionCategoryDoesntExist($category, $this->_channel);
        }
        $ret = ($categories->delete() !== false);
        if ($ret) {
            $this->deleteCategoryREST($category);
        }
        return $ret;
    }

    public function categoryFromId($id)
    {
        $categories = DB_DataObject::factory('categories');
        $categories->channel = $this->_channel;
        $categories->id = $id;
        if ($categories->find(true)) {
            return $categories->name;
        } else {
            return 'Default';
        }
    }

    public function categoryInfo($category)
    {
        $categories = DB_DataObject::factory('categories');
        $categories->channel = $this->_channel;
        $categories->name = $category;
        if ($categories->find(true)) {
            return $categories->toArray();
        } else {
            if ($category == 'Default') {
                return array(
                    'id' => 0,
                    'channel' => $this->_channel,
                    'name' => 'Default',
                    'description' => 'Default Category',
                    'alias' => 'Default',
                );
            }
            throw new Chiara_PEAR_Server_ExceptionCategoryDoesntExist($category, $channel);
        }
    }

    public function listPackagesInCategory($category)
    {
        $info = $this->categoryInfo($category);
        $packages = DB_DataObject::factory('packages');
        $packages->channel = $this->_channel;
        $packages->category_id = $info['id'];
        $packages->find(false);
        $ret = array();
        while ($packages->fetch()) {
            $ret[] = $packages->toArray();
        }
        return $ret;
    }

    public function listCategories()
    {
        $categories = DB_DataObject::factory('categories');
        $categories->channel = $this->_channel;
        $categories->orderby('name');
        $categories->find(false);
        $ret = array(array(
                    'id' => 0,
                    'channel' => $this->_channel,
                    'name' => 'Default',
                    'description' => 'Default Category',
                    'alias' => 'Default',
                ));
        while ($categories->fetch()) {
            $ret[] = $categories->toArray();
        }
        return $ret;
    }

    public function addCategory($cat)
    {
        if ($cat->name == 'Default') {
            throw new Chiara_PEAR_Server_ExceptionCategoryExists($cat->name, $this->_channel);
        }
        $category = DB_DataObject::factory('categories');
        $category->channel = $this->_channel;
        $category->name = $cat->name;
        if ($category->find()) {
            throw new Chiara_PEAR_Server_ExceptionCategoryExists($cat->name, $this->_channel);
        }
        $category->description = $cat->description;
        $category->alias = $cat->alias;
        $ret = $category->insert();
        if ($ret) {
            $this->saveCategoryREST($cat->name);
        }
        return $ret;
    }

    public function deleteCategoryREST($category)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $cdir = $this->_restdir . DIRECTORY_SEPARATOR . 'c';
            if (!file_exists($cdir . DIRECTORY_SEPARATOR . urlencode($category))) {
                return;
            }
            // remove all category info
            System::rm(array('-r', $this->_restdir . DIRECTORY_SEPARATOR . 'c'
                . DIRECTORY_SEPARATOR . urlencode($category)));
        }
    }

    public function saveCategoryREST($category)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $cdir = $this->_restdir . DIRECTORY_SEPARATOR . 'c';
            $category = $this->categoryInfo($category);
            if (!file_exists($cdir . DIRECTORY_SEPARATOR . urlencode($category['name']))) {
                System::mkdir(array('-p', $cdir . DIRECTORY_SEPARATOR . urlencode($category['name'])));
                @chmod($cdir . DIRECTORY_SEPARATOR . urlencode($category['name']), 0777);
            }
            $info = '<?xml version="1.0"?>
<c xmlns="http://pear.php.net/dtd/rest.category"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.category
    http://pear.php.net/dtd/rest.category.xsd">
 <n>' . htmlspecialchars($category['name']) . '</n>
 <c>' . $category['channel'] . '</c>
 <a>' . $category['alias'] . '</a>
 <d>' . htmlspecialchars($category['description']) . '</d>
</c>';
            // category info
            file_put_contents($cdir . DIRECTORY_SEPARATOR . urlencode($category['name']) .
                DIRECTORY_SEPARATOR . 'info.xml', $info);
            @chmod($cdir . DIRECTORY_SEPARATOR . urlencode($category['name']) .
                DIRECTORY_SEPARATOR . 'info.xml', 0666);
            $list = '<?xml version="1.0"?>
<l xmlns="http://pear.php.net/dtd/rest.categorypackages"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.categorypackages
    http://pear.php.net/dtd/rest.categorypackages.xsd">
';
            foreach ($this->listPackagesInCategory($category['name']) as $package) {
                $list .= ' <p xlink:href="' . $extra . 'p/' . strtolower($package['package']) . '">' .
                    $package['package'] . '</p>
';
            }
            $list .= '</l>';
            // list packages in a category
            file_put_contents($cdir . DIRECTORY_SEPARATOR . urlencode($category['name']) .
                DIRECTORY_SEPARATOR . 'packages.xml', $list);
            @chmod($cdir . DIRECTORY_SEPARATOR . urlencode($category['name']) .
                DIRECTORY_SEPARATOR . 'packages.xml', 0666);
        }
    }

    public function deletePackageREST($package, $category)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            // remove all package and release info for the package
            System::rm(array('-r', $this->_restdir . DIRECTORY_SEPARATOR . 'p'
                . DIRECTORY_SEPARATOR . strtolower($package)));
            System::rm(array('-r', $this->_restdir . DIRECTORY_SEPARATOR . 'r'
                . DIRECTORY_SEPARATOR . strtolower($package)));
            // reset categories info
            $this->saveCategoryREST($category);
        }
    }

    public function savePackageREST($package)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $packages = DB_DataObject::factory('packages');
            $packages->channel = $this->_channel;
            $packages->package = $package;
            if (!$packages->find(true)) {
                throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($package, $this->_channel);
            }
            $package = $packages->toArray();

            $pdir = $this->_restdir . DIRECTORY_SEPARATOR . 'p';
            if (!file_exists($pdir . DIRECTORY_SEPARATOR . strtolower($package['package']))) {
                System::mkdir(array('-p', $pdir . DIRECTORY_SEPARATOR .
                    strtolower($package['package'])));
                @chmod($pdir . DIRECTORY_SEPARATOR . strtolower($package['package']), 0777);
            }
            $catinfo = $this->categoryInfo($this->categoryFromId($package['category_id']));
            if ($package['parent']) {
                $parent = '<pa xlink:href="' . $extra . 'p/' . $package['parent'] . '">' . 
                    $package['parent'] . '</pa>
';
            } else {
                $parent = '';
            }
            if ($package['deprecated_package']) {
                if ($package['deprecated_channel'] == $this->_channel) {
                    $deprecated = '<dc>' . $package['deprecated_channel'] . '</dc>
 <dp href="' . $extra . 'p/' . $package['deprecated_package'] . '"> ' .
                    $package['deprecated_package'] . '</dp>
';
                } else {
                    $deprecated = '<dc>' . $package['deprecated_channel'] . '</dc>
 <dp> ' . $package['deprecated_package'] . '</dp>
';
                }
            } else {
                $deprecated = '';
            }
            $info = '<?xml version="1.0"?>
<p xmlns="http://pear.php.net/dtd/rest.package"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.package
    http://pear.php.net/dtd/rest.package.xsd">
 <n>' . $package['package'] . '</n>
 <c>' . $package['channel'] . '</c>
 <ca xlink:href="' . $extra . 'c/' . urlencode($catinfo['name']) . '">' .
            htmlspecialchars($catinfo['name']) . '</ca>
 <l>' . $package['license'] . '</l>' . ($package['licenseuri'] ? '
 <lu>' . $package['licenseuri'] . '</lu>
' : '
') . '
 <s>' . htmlspecialchars($package['summary']) . '</s>
 <d>' . htmlspecialchars($package['description']) . '</d>
 <r xlink:href="' . $extra . 'r/' . $package['package'] . '"/>
 ' . $parent . $deprecated . '
</p>';
            // package information
            file_put_contents($pdir . DIRECTORY_SEPARATOR . strtolower($package['package']) .
                DIRECTORY_SEPARATOR . 'info.xml', $info);
            @chmod($pdir . DIRECTORY_SEPARATOR . strtolower($package['package']) .
                DIRECTORY_SEPARATOR . 'info.xml', 0666);
        }
    }

    public function updateCategory($cat)
    {
        if ($cat->name == 'Default') {
            return true;
        }
        $category = DB_DataObject::factory('categories');
        $category->channel = $this->_channel;
        $category->name = $cat->managecategory;
        if (!$category->find(true)) {
            throw new Chiara_PEAR_Server_ExceptionCategoryDoesntExist($cat->managecategory, $this->_channel);
        }
        $category->name = $cat->name;
        $id = $category->id;
        unset($category->id);
        if (!$category->find(true)) {
            $category->id = $id;
            $category->description = $cat->description;
            $category->alias = $cat->alias;
            $result = $category->update();
        } else {
            throw new Chiara_PEAR_Server_ExceptionCategoryExists($category->name, $this->_channel);
        }
        $this->saveCategoryREST($cat->name);
        return $result;
    }

    public function getCategory($cat)
    {
        
        $p = DB_DataObject::factory('categories');
        $p->channel = $this->_channel;
        $p->name = $cat;
        $row = $p->find(true);
        if (!$row) {
            throw new Chiara_PEAR_Server_ExceptionCategoryDoesntExist($cat, $this->_channel);
        }
        
        return $p;
    }

    public function listReleases($package, $stableonly = false)
    {
        $packages = DB_DataObject::factory('packages');
        $packages->channel = $this->_channel;
        $packages->package = $package;
        if (!$packages->find()) {
            return false;
        }
        $releasedata = DB_DataObject::factory('releases');
        $releasedata->channel = $this->_channel;
        $releasedata->package = $package;
        if ($stableonly) {
            $releasedata->state = 'stable';
        }
        $releasedata->orderby('releasedate desc');
        if (!$releasedata->find(false)) {
            return array();
        }
        $ret = array();
        while ($releasedata->fetch()) {
            $arr = $releasedata->toArray();
            $ret[$arr['version']] = $arr;
        }
        return $ret;
    }

    public function deletePackage($packagename)
    {
        $packages = DB_DataObject::factory('packages');
        $packages->channel = $this->_channel;
        $packages->package = $packagename;
        if (!$packages->find(true)) {
            throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($package, $this->_channel);
        }
        if ($packages->category_id === '0') {
            $catname = 'Default';
        } else {
            $categories = DB_DataObject::factory('categories');
            $categories->channel = $this->_channel;
            $categories->id = $packages->category_id;
            $categories->find(true);
            $catname = $categories->name;
        }
        $releases = DB_DataObject::factory('releases');
        $releases->channel = $this->_channel;
        $releases->package = $packagename;
        if ($releases->find()) {
            throw new Chiara_PEAR_Server_ExceptionCannotDeleteHasReleases($packagename, $this->_channel);
        }
        $ret = $packages->delete() !== false;
        if ($ret) {
            $this->saveAllPackagesREST();
            $this->deletePackageREST($packagename, $catname);
        }
        return $ret;
    }

    public function addPackage($pkg)
    {
        $package = DB_DataObject::factory('packages');
        $packages_extras = DB_DataObject::factory('package_extras');
        $package->channel = $this->_channel;
        $save = clone $package;
        if ($pkg->parent) {
            $package->package = $pkg->parent;
            if (!$package->find()) {
                throw new Chiara_PEAR_Server_ExceptionParentPackageDoesntExist($package->parent, $this->_channel);
            }
        }
        $package = $save;

        $package->package = $pkg->name;
        if ($package->find()) {
            throw new Chiara_PEAR_Server_ExceptionPackageExists($pkg->name, $this->_channel);
        }
        if ($pkg->parent) {
            $package->parent = $pkg->parent;
        }
        $package->category_id = $pkg->category_id;
        $package->license = $pkg->license;
        $package->description = $pkg->description;
        $package->summary = $pkg->summary;
        
        $packages_extras->channel = $this->_channel;
        $packages_extras->package = $pkg->name;
        $packages_extras->cvs_uri = $pkg->cvs_uri;
        $packages_extras->bugs_uri = $pkg->bugs_uri;
        $packages_extras->docs_uri = $pkg->docs_uri;
        $packages_extras->insert();
        $ret = $package->insert();
        if ($ret) {
            $this->savePackageREST($pkg->name);
            $this->saveAllPackagesREST();
            $category = DB_DataObject::factory('categories');
            $category->id = $pkg->category_id;
            if ($category->find(true)) {
                $this->saveCategoryREST($category->name);
            }
        }
        return $ret;
    }

    /**
     * @param Chiara_PEAR_Server_Package
     */
    public function updatePackage(Chiara_PEAR_Server_Package $pkg)
    {
        $package = DB_DataObject::factory('packages');
        $package_extras = DB_DataObject::factory('package_extras');
        $package->channel = $this->_channel;
        $package->package = $pkg->name;
        if (!$package->find()) {
            throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($pkg->name, $this->_channel);
        }
        $package_extras->channel = $this->_channel;
        $package_extras->package = $pkg->name;
        
        $package_extras_exists = false;
        
        if ($package_extras->find(true)) {
            $package_extras_exists = true;
        }
        
        $package_extras->cvs_uri = $pkg->cvs_uri;
        $package_extras->bugs_uri = $pkg->bugs_uri;
        $package_extras->docs_uri = $pkg->docs_uri;
        
        $package->category_id = $pkg->category_id;
        $package->license = $pkg->license;
        $package->licenseuri = $pkg->license_uri;
        $package->description = $pkg->description;
        $package->summary = $pkg->summary;
        $package->deprecated_channel = $pkg->deprecated_channel;
        $package->deprecated_package = $pkg->deprecated_package;
        $pkg->parent ? ($package->parent = $pkg->parent) : null;
        
        if ($package_extras_exists) {
            $package_extras->update();
        } else {
            $package_extras->insert();
        }
        
        $ret = $package->update();
        if ($ret) {
            $this->savePackageREST($pkg->name);
            $category = DB_DataObject::factory('categories');
            $category->id = $pkg->category_id;
            if ($category->find(true)) {
                $this->saveCategoryREST($category->name);
            }
        }
        return $ret;
    }

    public function getPackage($pkg)
    {
        $p = DB_DataObject::factory('packages');
        $p->channel = $this->_channel;
        $p->package = $pkg;
        if (!$p->find(true)) {
            throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($pkg, $this->_channel);
        }
        $package = new Chiara_PEAR_Server_Package;
        $package->name = $pkg;
        $package->channel = $this->_channel;
        $package->category_id = $p->category_id;
        $package->license = $p->license;
        $package->description = $p->description;
        $package->summary = $p->summary;
        $package->parent = $p->parent;
        $package->deprecated_channel = $p->deprecated_channel;
        $package->deprecated_package = $p->deprecated_package;
        return $package;
    }
    
    public function getPackageExtras($pkg)
    {
        
        $extras = DB_DataObject::factory('package_extras');
        $extras->channel = $this->_channel;
        $extras->package = $pkg;
        if (!$extras->find(true)) {
            return array();
        }
        return $extras->toArray();
    }

    public function listDeps($package, $version)
    {
        $ret = array();
        $rel = DB_DataObject::factory('releases');
        $rel->channel = $this->_channel;
        $rel->package = $package;
        $rel->version = $version;
        if (!$rel->find(true)) {
            return array();
        }
        $ret = unserialize($rel->deps);
        return $ret;
    }

    public function packageInfo($pkg, $key = null)
    {
        $package = DB_DataObject::factory('packages');
        $package->channel = $this->_channel;
        $package->package = $pkg;
        if (!$package->find(true)) {
            return false;
        }
        if (isset($package->$key)) {
            return $package->$key;
        }
        if ($key === null) {
            $ret = $package->toArray();
            $ret['name'] = $ret['package'];
            $ret['category'] = 'Packages';
            $ret['releases'] = $this->listReleases($pkg);
            if (count($ret['releases'])) {
                $temp = array_shift(array_values($ret['releases']));
                // it's not really necessarily stable - this is crap legacy from pearweb
                $ret['stable'] = $temp['version'];
            } else {
                $ret['stable'] = '';
            }
            foreach ($ret['releases'] as $i => $rel) {
                $ret['releases'][$i]['deps'] = $this->listDeps($pkg, $rel['version']);
            }
            return $ret;
        }
        if ($key == 'releases') {
            return $this->listReleases($pkg);
        }
        if ($key == 'deps') {
            return $this->listDeps($pkg, $rel['version']);
        }
    }

    public function deleteMaintainer($maintainer)
    {
        $handle = DB_DataObject::factory('handles');
        $handle->handle = $maintainer->handle;
        if (!$handle->find(true)) {
            throw new Chiara_PEAR_Server_ExceptionMaintainerDoesntExist($handle->handle);
        }
        $packages = DB_DataObject::factory('maintainers');
        $packages->handle = $handle->handle;
        if ($packages->find()) {
            throw new Chiara_PEAR_Server_ExceptionCannotDeleteMaintainer($handle->handle);
        }
        $ret = $packages->delete() !== false;
        if ($ret) {
            $this->deleteMaintainerREST($handle->handle);
        }
        return $ret;
    }

    public function deleteMaintainerREST(Chiara_PEAR_Server_Maintainer $maintainer)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $mdir = $this->_restdir . DIRECTORY_SEPARATOR . 'm';
            if (file_exists($mdir . DIRECTORY_SEPARATOR . $maintainer->handle)) {
                System::rm(array('-r', $mdir . DIRECTORY_SEPARATOR . $maintainer->handle));
            }
        }        
    }

    /**
     * @param Chiara_PEAR_Server_Maintainer
     */
    public function addMaintainer(Chiara_PEAR_Server_Maintainer $maintainer)
    {
        $handle = DB_DataObject::factory('handles');
        $handle->handle = $maintainer->handle;
        if ($handle->find()) {
            throw new Chiara_PEAR_Server_ExceptionMaintainerExists($handle->handle);
        }
        $handle->email = $maintainer->email;
        $handle->name = $maintainer->name;
        $handle->password = md5($maintainer->password);
        $ret = $handle->insert();
        if ($ret) {
            $this->saveMaintainerREST($maintainer);
        }
        return $ret;
    }

    public function saveMaintainerREST(Chiara_PEAR_Server_Maintainer $maintainer)
    {
        $channel = DB_DataObject::factory('channels');
        $channel->channel = $this->_channel;
        $channel->find(true);
        if ($channel->rest_support) {
            $channelinfo = parse_url($this->_channel);
            if (isset($channelinfo['host'])) {
                $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
            } else {
                $extra = '/Chiara_PEAR_Server_REST/';
            }
            $mdir = $this->_restdir . DIRECTORY_SEPARATOR . 'm';
            if (!file_exists($mdir . DIRECTORY_SEPARATOR . $maintainer->handle)) {
                System::mkdir(array('-p', $mdir . DIRECTORY_SEPARATOR . $maintainer->handle));
                @chmod($mdir . DIRECTORY_SEPARATOR . $maintainer->handle, 0777);
            }
            if ($maintainer->uri) {
                $uri = ' <u>' . htmlspecialchars($maintainer->uri) . '</u>
';
            } else {
                $uri = '';
            }
            $info = '<?xml version="1.0"?>
<m xmlns="http://pear.php.net/dtd/rest.maintainer"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.maintainer
    http://pear.php.net/dtd/rest.maintainer.xsd">
 <h>' . $maintainer->handle . '</h>
 <n>' . htmlentities($maintainer->name) . '</n>
' . $uri . '</m>';
            // package information
            file_put_contents($mdir . DIRECTORY_SEPARATOR . $maintainer->handle .
                DIRECTORY_SEPARATOR . 'info.xml', $info);
            @chmod($mdir . DIRECTORY_SEPARATOR . $maintainer->handle .
                DIRECTORY_SEPARATOR . 'info.xml', 0666);
        }
    }

    /**
     * @param Chiara_PEAR_Server_Maintainer
     */
    public function updateMaintainer(Chiara_PEAR_Server_Maintainer $maintainer)
    {
        $handle = DB_DataObject::factory('handles');
        $handle->handle = $maintainer->handle;
        if (!$handle->find()) {
            throw new Chiara_PEAR_Server_ExceptionMaintainerDoesntExist($handle->handle);
        }
        $handle->email = $maintainer->email;
        $handle->name = $maintainer->name;
        $ret = $handle->update() !== false;
        if ($ret) {
            $this->saveMaintainerREST();
        }
        return $ret;
    }

    /**
     * @param string
     * @return Chiara_PEAR_Server_Maintainer
     */
    public function getMaintainer($maintainer)
    {
        $handle = DB_DataObject::factory('handles');
        $handle->handle = $maintainer;
        if (!$handle->find(true)) {
            throw new Chiara_PEAR_Server_ExceptionMaintainerDoesntExist($handle->handle);
        }
        $maint = new Chiara_PEAR_Server_Maintainer($handle->toArray());
        return $maint;
    }

    /**
     * @return array array of Chiara_PEAR_Server_Maintainer objects
     */
    public function listMaintainers($returnhandles = false)
    {
        $maintainers = DB_DataObject::factory('handles');
        $ret = array();
        if ($maintainers->find()) {
            while ($maintainers->fetch()) {
                if ($returnhandles) {
                    $ret[] = $maintainers->handle;
                } else {
                    $ret[] = new Chiara_PEAR_Server_Maintainer($maintainers->toArray());
                }
            }
        }
        return $ret;
    }

    /**
     * @param string
     * @return array array of Chiara_PEAR_Server_MaintainerPackage objects
     */
    public function listPackageMaintainers($package)
    {
        $maintainers = DB_DataObject::factory('maintainers');
        $maintainers->package = $package;
        $handle = DB_DataObject::factory('handles');
        $ret = array();
        if ($maintainers->find()) {
            while ($maintainers->fetch()) {
                $handle->handle = $maintainers->handle;
                $handle->name = null;
                $handle->email = null;
                $handle->password = null;
                $handle->admin = null;
                $handle->uri = null;
                $handle->find(true);
                $maintainer = $maintainers->toArray();
                $maintainer['name'] = $handle->name;
                $ret[] = new Chiara_PEAR_Server_MaintainerPackage($maintainer);
            }
        }
        return $ret;
    }

    /**
     * @param string
     * @param string
     * @return bool
     */
    public function packageLead($package, $handle)
    {
        $maintainers = DB_DataObject::factory('maintainers');
        $maintainers->package = $package;
        $maintainers->handle = $handle;
        $maintainers->role = 'lead';
        return $maintainers->find();
    }

    public function updatePackageMaintainer(Chiara_PEAR_Server_MaintainerPackage $maintainer)
    {
        $maintainers = DB_DataObject::factory('maintainers');
        $maintainers->package = $maintainer->package;
        $maintainers->channel = $maintainer->channel;
        $maintainers->handle = $maintainer->handle;
        if (!$maintainers->find()) {
            throw new Chiara_PEAR_Server_ExceptionPackageMaintainerDoesntExist($maintainer->package, $maintainer->channel, $maintainer->handle);
        }
        $maintainers->role = $maintainer->role;
        $maintainers->active = $maintainer->active;
        if ($maintainers->find()) {
            return true;
        }
        return $maintainers->update();
    }

    public function addPackageMaintainer(Chiara_PEAR_Server_MaintainerPackage $maintainer)
    {
        $maintainers = DB_DataObject::factory('maintainers');
        $maintainers->package = $maintainer->package;
        $maintainers->channel = $maintainer->channel;
        $maintainers->handle = $maintainer->handle;
        if ($maintainers->find()) {
            throw new Chiara_PEAR_Server_ExceptionPackageMaintainerExists($maintainer->package, $maintainer->channel, $maintainer->handle);
        }
        $maintainers->role = $maintainer->role;
        $maintainers->active = $maintainer->active;
        return $maintainers->insert();
    }
}
?>