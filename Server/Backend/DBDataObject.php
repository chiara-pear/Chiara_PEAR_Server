<?php
require_once 'DB/DataObject.php';
require_once 'Chiara/PEAR/Server/Backend.php';
require_once 'Chiara/PEAR/Server/Exception.php';
class Chiara_PEAR_Server_Backend_DBDataObject extends Chiara_PEAR_Server_Backend
{
    public function __construct($channel, $config = array())
    {
        parent::__construct($channel);
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
        return $releases->delete() !== false;
    }
    
    public function deleteCategory($category)
    {
        $releases = DB_DataObject::factory('categories');
        $releases->name = $category;
        $releases->channel = $this->_channel;
        if (!$releases->find()) {
            throw new Chiara_PEAR_Server_ExceptionCategoryDoesntExist($category, $this->_channel);
        }
        return $releases->delete() !== false;
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
        return $releasedata->insert();
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
    
    public function listCategories()
    {
        $categories = DB_DataObject::factory('categories');
        $categories->channel = $this->_channel;
        $categories->orderby('name');
        $categories->find(false);
        $ret = array();
        while ($categories->fetch()) {
            $ret[] = $categories->toArray();
        }
        return $ret;
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

    public function addPackage($pkg)
    {
        $package = DB_DataObject::factory('packages');
        $category = DB_DataObject::factory('categories');
        $packages_extras = DB_DataObject::factory('package_extras');
        $package->channel = $this->_channel;
        $save = clone $package;
        if ($pkg->parent) {
            $package->parent = $pkg->parent;
            if (!$package->find()) {
                throw new Chiara_PEAR_Server_ExceptionParentPackageDoesntExist($package->parent, $this->_channel);
            }
        }
        $package = $save;

        $package->package = $pkg->name;
        if ($package->find()) {
            throw new Chiara_PEAR_Server_ExceptionPackageExists($pkg->name, $this->_channel);
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
        return $package->insert();
    }
    
    public function addCategory($cat)
    {
        $category = DB_DataObject::factory('categories');
        $category->channel = $this->_channel;
        $save = clone $category;
        $category = $save;
        $category->name = $cat->name;
        if ($category->find()) {
            throw new Chiara_PEAR_Server_ExceptionCategoryExists($cat->name, $this->_channel);
        }
        $category->description = $cat->description;
        $category->alias = $cat->alias;
        return $category->insert();
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
        
        return $package->update();
    }
    
    public function updateCategory($cat)
    {
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
        return $result;
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
        return $handle->insert();
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
        return $handle->update();
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