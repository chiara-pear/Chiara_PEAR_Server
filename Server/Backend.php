<?php
require_once 'Chiara/PEAR/Server/Maintainer.php';
abstract class Chiara_PEAR_Server_Backend
{
    protected $_channel;
    protected $_restdir;

    public function __construct($channel, $restdir = false)
    {
        $this->_channel = $channel;
        $this->_restdir = $restdir;
    }

    public function getChannel()
    {
        return $this->_channel;
    }

    /**
     * @param string
     */
    public function validPackage($packagename)
    {
        return false;
    }

    abstract public function saveReleaseREST($package, $version);

    abstract public function saveAllReleasesREST($package);

    abstract public function savePackageREST($package);

    abstract public function deletePackageREST($package, $category);

    abstract public function saveCategoryREST($category);

    abstract public function deleteCategoryREST($category);

    abstract public function deleteRelease($packagename, $version);

    abstract public function deleteReleaseREST($packagename, $version);

    abstract public function getReleasePath($packagename, $version);

    /**
     * @param Chiara_PEAR_Server_Release
     */
    abstract public function saveRelease(Chiara_PEAR_Server_Release $release);

    abstract public function validLogin($user, $password);

    abstract public function isAdmin($user);

    abstract public function isDeprecatedPackage($channel, $package);

    abstract public function releaseExists(Chiara_PEAR_Server_Release $release);

    /**
     * @return array
     */
    abstract public function channelInfo();

    /**
     * @param string full path to the file
     */
    abstract public function getFilesize($fullpath);

    abstract public function listPackages($releasedOnly = true, $onlyStable = true, $nosubpackages = true);

    abstract public function listPackagesInCategory($category);

    abstract public function listCategories();

    abstract public function addCategory($cat);

    abstract public function updateCategory($cat);

    abstract public function getCategory($cat);

    abstract public function deleteCategory($cat);

    abstract public function categoryFromId($cat);

    abstract public function categoryInfo($cat);

    abstract public function listDeps($package, $version);

    /**
     * @param string Package Name
     */
    abstract public function listReleases($package);

    /**
     * @param Chiara_PEAR_Server_Package
     */
    abstract public function addPackage($package);

    abstract public function deletePackage($packagename);

    /**
     * @param Chiara_PEAR_Server_Package
     */
    abstract public function updatePackage(Chiara_PEAR_Server_Package $package);

    /**
     * @param string
     * @return Chiara_PEAR_Server_Package
     */
    abstract public function getPackage($package);

    abstract public function packageInfo($package, $key = null);

    abstract public function addMaintainer(Chiara_PEAR_Server_Maintainer $maintainer);

    abstract public function updateMaintainer(Chiara_PEAR_Server_Maintainer $maintainer);

    abstract public function updatePackageMaintainer(Chiara_PEAR_Server_MaintainerPackage $maintainer);

    abstract public function addPackageMaintainer(Chiara_PEAR_Server_MaintainerPackage $maintainer);

    /**
     * @param string
     * @return Chiara_PEAR_Server_Maintainer
     */
    abstract public function getMaintainer($maintainer);

    /**
     * @param bool if true, returns an array of handles
     * @return array array of Chiara_PEAR_Server_Maintainer objects
     */
    abstract public function listMaintainers($returnhandles = false);

    /**
     * @param string
     * @return array array of Chiara_PEAR_Server_MaintainerPackage objects
     */
    abstract public function listPackageMaintainers($package);

    /**
     * @param string
     * @param string
     * @return bool
     */
    abstract public function packageLead($package, $handle);

    /**
     * Return an array containing all of the states that are more stable than
     * or equal to the passed in state
     *
     * @param string Release state
     * @param boolean Determines whether to include $state in the list
     * @return false|array False if $state is not a valid release state
     */
    public function betterStates($state, $include = false)
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
}
?>