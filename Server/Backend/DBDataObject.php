<?php
require_once 'DB/DataObject.php';
require_once 'Chiara/PEAR/Server/Backend.php';
require_once 'Chiara/PEAR/Server/Exception.php';
/**
 * @version $Id: DBDataObject.php 309 2009-04-28 16:10:04Z  $
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
        $handles->channel = $this->_channel;
        $handles->handle = $user;
        $handles->admin = 1;
        return $handles->find();
    }

    public function isDeprecatedPackage($package)
    {
        $packages = DB_DataObject::factory('packages');
        $packages->channel = $this->_channel;
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
        $handles->channel = $this->_channel;
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
        $package = DB_DataObject::factory('packages');
        $package->channel = $this->_channel;
        $package->package = $packagename;
        $package->find(true);
        if (!$package->category_id) {
            $category = 'Default';
        } else {
            $cat = DB_DataObject::factory('categories');
            $cat->id = $package->category_id;
            $cat->channel = $this->_channel;
            $cat->find(true);
            $category = $cat->name;
        }
        if ($ret) {
            $this->deleteReleaseREST($packagename, $version);
            clearstatcache();
            $this->savePackagesCategoryREST($category);
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
        if (!$package->find(true)) {
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
        $existing_maintainers = array();
        while ($maintainers->fetch()) {
            if ($maintainers->handle == $release->getReleasingMaintainer() && $maintainers->role != 'lead') {
                throw new Chiara_PEAR_Server_ExceptionMaintainerNotLead($maintainers->handle, $package->package, $this->_channel);
            }
            $existing_maintainers[$maintainers->handle] = $maintainers->toArray();
        }
        foreach ($release->getMaintainers() as $maintainer) {
            // add or update all active maintainers
            $maintainers = DB_DataObject::factory('maintainers');
            $maintainers->handle = $maintainer['handle'];
            $maintainers->package = $package->package;
            $maintainers->channel = $this->_channel;
            if (isset($existing_maintainers[$maintainer['handle']])
                    && $maintainers->find()) {
                // Maintainer exists already, update details.
                $maintainers->fetch();
                $existrole = $existing_maintainers[$maintainer['handle']]['role'];
                unset($existing_maintainers[$maintainer['handle']]);
                if ($existrole != 'lead') {
                    // do not allow demotion through a release, only through web frontend
                    $maintainers->role = $maintainer['role'];
                }
                $maintainers->active = 1;
                $maintainers->update();
            } else {
                // Maintainer does not exist yet.
                $handles = DB_DataObject::factory('handles');
                $handles->handle = $maintainer['handle'];
                if (!$handles->find()) {
                    throw new Chiara_PEAR_Server_ExceptionMaintainerDoesntExist(
                        $maintainer['handle']);
                }
                $maintainers->active = 1;
                $maintainers->role = $maintainer['role'];
                $maintainers->insert();
            }
        }
        /* 
         * Now remove any maintainers that were existing before, but not present in this release.
         * This means the maintainers were removed from package.xml, so mark them as inactive.
         */
        foreach ($existing_maintainers as $maintainer) {
            $maintainers = DB_DataObject::factory('maintainers');
            $maintainers->handle = $maintainer['handle'];
            $maintainers->package = $package->package;
            $maintainers->channel = $this->_channel;
            if ($maintainers->find() && $maintainers->fetch()) {
                $maintainers->active = 0;
                $maintainers->role = $maintainer['role'];
                $maintainers->update();
            }
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
        if (!$package->category_id) {
            $category = 'Default';
        } else {
            $cat = DB_DataObject::factory('categories');
            $cat->id = $package->category_id;
            $cat->channel = $this->_channel;
            $cat->find(true);
            $category = $cat->name;
        }
        if ($ret) {
            $this->savePackageDepsREST($releasedata->package, $releasedata->version,
                $release->getDependencies());
            $this->savePackageREST($releasedata->package);
            $this->savePackageMaintainersREST($releasedata->package);
            $this->savePackageMaintainersWithRoleREST($releasedata->package);
            $this->saveReleaseREST($releasedata->package, $releasedata->version);
            $this->saveAllReleasesREST($releasedata->package);
            $this->savePackagesCategoryREST($category);
        }
        return $ret;
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
        $category_check = $category->find(true);
        if (0 === $category_check || $id === $category->id) {
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
        $package->licenseuri = $pkg->license_uri;
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
        $package->license_uri = $p->licenseuri;
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
        if ($key && isset($package->$key)) {
            return $package->$key;
        }
        if ($key === null) {
            $ret = $package->toArray();
            $ret['name'] = $ret['package'];
            $ret['category'] = $this->categoryFromId($ret['category_id']);
            $ret['releases'] = $this->listReleases($pkg);
            if (count($ret['releases'])) {
                $temp = array_shift($a = array_values($ret['releases']));
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

    public function packageExists($package)
    {
        $packages = DB_DataObject::factory('packages');
        $packages->channel = $this->_channel;
        $packages->package = $package;
        return $packages->find(true);
    }

    public function deleteMaintainer($maintainer)
    {
        $handle = DB_DataObject::factory('handles');
        $handle->channel = $this->_channel;
        $handle->handle = $maintainer->handle;
        if (!$handle->find(true)) {
            throw new Chiara_PEAR_Server_ExceptionMaintainerDoesntExist($handle->handle);
        }
        $packages = DB_DataObject::factory('maintainers');
        $packages->channel = $channel;
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

    /**
     * @param Chiara_PEAR_Server_Maintainer
     */
    public function addMaintainer(Chiara_PEAR_Server_Maintainer $maintainer)
    {
        $handle = DB_DataObject::factory('handles');
        $handle->channel = $this->_channel;
        $handle->handle = $maintainer->handle;
        if ($handle->find()) {
            throw new Chiara_PEAR_Server_ExceptionMaintainerExists($handle->handle);
        }
        $handle->admin = $maintainer->admin;
        $handle->email = $maintainer->email;
        $handle->name = $maintainer->name;
        $handle->password = md5($maintainer->password);
        $ret = $handle->insert();
        if ($ret) {
            $this->saveMaintainerREST($maintainer);
        }
        return $ret;
    }

    /**
     * @param Chiara_PEAR_Server_Maintainer
     */
    public function updateMaintainer(Chiara_PEAR_Server_Maintainer $maintainer)
    {
        $handle = DB_DataObject::factory('handles');
        $handle->channel = $this->_channel;
        $handle->handle = $maintainer->handle;
        if (!$handle->find()) {
            throw new Chiara_PEAR_Server_ExceptionMaintainerDoesntExist($handle->handle);
        }
        $handle->fetch();
        $handle->email = $maintainer->email;
        $handle->name = $maintainer->name;
        $handle->uri = $maintainer->uri;
        $handle->description = $maintainer->description;
        $handle->wishlist = $maintainer->wishlist;
        if (strlen($maintainer->password) >= 6) {
            $handle->password = md5($maintainer->password);
        }
        $ret = $handle->update() !== false;
        if ($ret) {
            $this->saveMaintainerREST($maintainer);
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
        $handle->channel = $this->_channel;
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
        $maintainers->channel = $this->_channel;
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
        $maintainers->channel = $this->_channel;
        $handle = DB_DataObject::factory('handles');
        $ret = array();
        if ($maintainers->find()) {
            while ($maintainers->fetch()) {
                $handle->channel = $this->_channel;
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
