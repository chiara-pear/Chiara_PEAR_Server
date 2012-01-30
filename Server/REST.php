<?php
require_once 'Chiara/PEAR/Server/Backend.php';
/**
 * REST implementation class (backend-independent)
 * @author Gregory Beaver <cellog@php.net>
 */
class Chiara_PEAR_Server_REST
{
    protected $_channel;
    protected $_restdir;
    /**
     * Backend for data management
     *
     * @var Chiara_PEAR_Server_Backend
     */
    protected $_backend;

    /**
     * @param Chiara_PEAR_Server_Backend $backend backend used for this process
     * @param string path to REST files
     */
    public function __construct($backend, $restdir)
    {
        $this->_channel = $backend->getChannel();
        $this->_backend = $backend;
        $this->_restdir = $restdir;
    }

    /**
     * @param string
     * @param string
     */
    public function saveReleaseREST($package, $version)
    {
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        $releases = $this->_backend->listReleases($package);
        foreach ($releases as $testversion => $release) {
            if ($testversion == $version) {
                break;
            }
            unset ($release);
        }
        if (!isset($release)) {
            throw new PEAR_Server_ExceptionReleaseNotFound($package, $version);
        }
        $rdir = $this->_restdir . DIRECTORY_SEPARATOR . 'r';

        if (!file_exists($rdir . DIRECTORY_SEPARATOR . strtolower($package))) {
            System::mkdir(array('-p', $rdir . DIRECTORY_SEPARATOR . strtolower($package)));
            @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($package), 0777);
        }

        $info = '<?xml version="1.0" encoding="iso-8859-1" ?>
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
            $version . '.xml', $this->_backend->getPackageXml($release['package'], $version));
        @chmod($rdir . DIRECTORY_SEPARATOR . strtolower($release['package']) .
            DIRECTORY_SEPARATOR . 'package.' . $version . '.xml', 0666);
    }

    function _getAllReleasesRESTProlog($package)
    {
        return '<?xml version="1.0" encoding="UTF-8" ?>' . "\n" .
'<a xmlns="http://pear.php.net/dtd/rest.allreleases"' . "\n" .
'    xsi:schemaLocation="http://pear.php.net/dtd/rest.allreleases' . "\n" .
'    http://pear.php.net/dtd/rest.allreleases.xsd">' . "\n" .
' <p>' . htmlspecialchars($package) . '</p>' . "\n" .
' <c>' . htmlspecialchars($this->_channel) . '</c>' . "\n";
    }

    /**
     * @param string
     */
    public function saveAllReleasesREST($package)
    {
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        $rdir = $this->_restdir . DIRECTORY_SEPARATOR . 'r';
        $releases = $this->_backend->listReleases($package);
        if (!$releases) {
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'allreleases.xml');
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'latest.txt');
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'stable.txt');
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'beta.txt');
            @unlink($rdir . DIRECTORY_SEPARATOR . strtolower($package) .
                DIRECTORY_SEPARATOR . 'alpha.txt');
            return;
        }
        $info = $this->_getAllReleasesRESTProlog($package);
        foreach ($releases as $release) {
            if (!class_exists('PEAR_PackageFile_Parser_v2')) {
                require_once 'PEAR/PackageFile/Parser/v2.php';
            }
            if (!class_exists('PEAR/Config.php')) {
                require_once 'PEAR/Config.php';
            }
            $pkg = new PEAR_PackageFile_Parser_v2;
            $c = PEAR_Config::singleton();
            $pkg->setConfig($c);
            $pf = $pkg->parse($release['packagexml'], '');
            if ($compat = $pf->getCompatible()) {
                if (!isset($compat[0])) {
                    $compat = array($compat);
                }
                foreach ($compat as $entry) {
                    $extra .= '<co><c>' . $entry['channel'] . '</c>' .
                        '<p>' . $entry['name'] . '</p>' .
                        '<min>' . $entry['min'] . '</min>' .
                        '<max>' . $entry['max'] . '</max>';
                    if (isset($entry['exclude'])) {
                        if (!is_array($entry['exclude'])) {
                            $entry['exclude'] = array($entry['exclude']);
                        }
                        foreach ($entry['exclude'] as $exclude) {
                            $extra .= '<x>' . $exclude . '</x>';
                        }
                    }
                    $extra .= '</co>
';
                }
            }
            if (!isset($latest)) {
                $latest = $release['version'];
            }
            if ($release['state'] == 'stable' && !isset($stable)) {
                $stable = $release['version'];
            }
            if ($release['state'] == 'beta' && !isset($beta)) {
                $beta = $release['version'];
            }
            if ($release['state'] == 'alpha' && !isset($alpha)) {
                $alpha = $release['version'];
            }
            $info .= ' <r><v>' . $release['version'] . '</v><s>' . $release['state'] . '</s></r>
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

    /**
     * @param string
     */
    public function savePackageREST($package)
    {
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        if (!$this->_backend->packageExists($package)) {
            throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($package, $this->_channel);
        }
        $package = $this->_backend->packageInfo($package);

        $pdir = $this->_restdir . DIRECTORY_SEPARATOR . 'p';
        if (!file_exists($pdir . DIRECTORY_SEPARATOR . strtolower($package['package']))) {
            System::mkdir(array('-p', $pdir . DIRECTORY_SEPARATOR .
                strtolower($package['package'])));
            @chmod($pdir . DIRECTORY_SEPARATOR . strtolower($package['package']), 0777);
        }
        $catinfo = $this->_backend->categoryInfo(
            $this->_backend->categoryFromId($package['category_id']));
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
        $info = $this->_getPackageRESTProlog() . '
 <n>' . $package['package'] . '</n>
 <c>' . $package['channel'] . '</c>
 <ca xlink:href="' . $extra . 'c/' . urlencode($catinfo['name']) . '">' .
            htmlspecialchars($catinfo['name']) . '</ca>
 <l>' . $package['license'] . '</l>' . ($package['licenseuri'] ? '
 <lu>' . $package['licenseuri'] . '</lu>
' : '') . '
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

    /**
     * @param string
     * @param string
     */
    public function deletePackageREST($package, $category)
    {
        // remove all package and release info for the package
        System::rm(array('-r', $this->_restdir . DIRECTORY_SEPARATOR . 'p'
            . DIRECTORY_SEPARATOR . strtolower($package)));
        System::rm(array('-r', $this->_restdir . DIRECTORY_SEPARATOR . 'r'
            . DIRECTORY_SEPARATOR . strtolower($package)));
        // reset categories info
        $this->saveCategoryREST($category);
    }

    /**
     * @param string
     */
    public function saveCategoryREST($category)
    {
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        $cdir = $this->_restdir . DIRECTORY_SEPARATOR . 'c';
        $category = $this->_backend->categoryInfo($category);
        if (!file_exists($cdir . DIRECTORY_SEPARATOR . urlencode($category['name']))) {
            System::mkdir(array('-p', $cdir . DIRECTORY_SEPARATOR . urlencode($category['name'])));
            @chmod($cdir . DIRECTORY_SEPARATOR . urlencode($category['name']), 0777);
        }
        $info = '<?xml version="1.0" encoding="iso-8859-1" ?>
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
        $list = '<?xml version="1.0" encoding="iso-8859-1" ?>
<l xmlns="http://pear.php.net/dtd/rest.categorypackages"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.categorypackages
    http://pear.php.net/dtd/rest.categorypackages.xsd">
';
        foreach ($this->_backend->listPackagesInCategory($category['name']) as $package) {
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

    private function _getPackageRESTProlog()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n" .
"<p xmlns=\"http://pear.php.net/dtd/rest.package\"" .
"    xsi:schemaLocation=\"http://pear.php.net/dtd/rest.package" .
'    http://pear.php.net/dtd/rest.package.xsd">';
    }

    public function savePackagesCategoryREST($category)
    {
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        $cdir = $this->_restdir . DIRECTORY_SEPARATOR . 'c';
        if (!is_dir($cdir)) {
            return;
        }
        $pdir = $this->_restdir . DIRECTORY_SEPARATOR . 'p';
        $rdir = $this->_restdir . DIRECTORY_SEPARATOR . 'r';
        $packages = $this->_backend->listPackagesInCategory($category);
        $fullpackageinfo = '<?xml version="1.0" encoding="UTF-8" ?>
<f xmlns="http://pear.php.net/dtd/rest.categorypackageinfo"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.categorypackageinfo
    http://pear.php.net/dtd/rest.categorypackageinfo.xsd">
';
        clearstatcache();
        foreach ($packages as $package) {
            if (!file_exists($pdir . DIRECTORY_SEPARATOR . strtolower($package['package']) .
                    DIRECTORY_SEPARATOR . 'info.xml')) {
                continue;
            }
            $fullpackageinfo .= '<pi>
';
            $fullpackageinfo .= str_replace($this->_getPackageRESTProlog(), '<p>',
                file_get_contents($pdir . DIRECTORY_SEPARATOR . strtolower($package['package']) .
                    DIRECTORY_SEPARATOR . 'info.xml'));
            if (file_exists($rdir . DIRECTORY_SEPARATOR . strtolower($package['package']) .
                    DIRECTORY_SEPARATOR . 'allreleases.xml')) {
                $fullpackageinfo .= str_replace(
                    $this->_getAllReleasesRESTProlog($package['package']), '
<a>
',
                    file_get_contents($rdir . DIRECTORY_SEPARATOR .
                        strtolower($package['package']) . DIRECTORY_SEPARATOR .
                        'allreleases.xml'));
                $dirhandle = opendir($rdir . DIRECTORY_SEPARATOR .
                    strtolower($package['package']));
                $depinfo = array();
                while (false !== ($entry = readdir($dirhandle))) {
                    if (strpos($entry, 'deps.') === 0) {
                        $version = str_replace(array('deps.', '.txt'),
                                               array('', ''), $entry);
                        $dep = htmlspecialchars(utf8_encode(file_get_contents($rdir .
                            DIRECTORY_SEPARATOR .
                            strtolower($package['package']) . DIRECTORY_SEPARATOR .
                            $entry)));
                        $depinfo[$version] = $dep;
                    }
                }
                uksort($depinfo, create_function('$a,$b',
                    'return - version_compare($a, $b);'));
                foreach ($depinfo as $version => $dep) {
                        $fullpackageinfo .= '
<deps>
 <v>' . $version . '</v>
 <d>' . $dep . '</d>
</deps>
';
                }
            }
            $fullpackageinfo .= '</pi>
';
        }
        $fullpackageinfo .= '</f>';
        // list packages in a category
        file_put_contents($cdir . DIRECTORY_SEPARATOR . urlencode($category) .
            DIRECTORY_SEPARATOR . 'packagesinfo.xml', $fullpackageinfo);
        @chmod($cdir . DIRECTORY_SEPARATOR . urlencode($category) .
            DIRECTORY_SEPARATOR . 'packagesinfo.xml', 0666);
    }

    /**
     * @param string
     */
    public function deleteCategoryREST($category)
    {
        $cdir = $this->_restdir . DIRECTORY_SEPARATOR . 'c';
        if (!file_exists($cdir . DIRECTORY_SEPARATOR . urlencode($category))) {
            return;
        }
        // remove all category info
        System::rm(array('-r', $this->_restdir . DIRECTORY_SEPARATOR . 'c'
            . DIRECTORY_SEPARATOR . urlencode($category)));
    }

    public function saveAllCategoriesREST()
    {
        require_once 'System.php';
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        $cdir = $this->_restdir . DIRECTORY_SEPARATOR . 'c';
        if (!is_dir($cdir)) {
            System::mkdir(array('-p', $cdir));
            @chmod($cdir, 0777);
        }

        $categories = $this->_backend->listCategories();
        $info = '<?xml version="1.0" encoding="UTF-8" ?>
<a xmlns="http://pear.php.net/dtd/rest.allcategories"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.allcategories
    http://pear.php.net/dtd/rest.allcategories.xsd">
<ch>' . $this->_channel . '</ch>
';
        foreach ($categories as $category)
        {
            $info .= ' <c xlink:href="' . $extra . 'c/' .
                urlencode(urlencode($category['name'])) .
                '/info.xml">' .
                htmlspecialchars(utf8_encode($category['name'])) . '</c>
';
        }
        $info .= '</a>';
        file_put_contents($cdir . DIRECTORY_SEPARATOR . 'categories.xml', $info);
        @chmod($cdir . DIRECTORY_SEPARATOR . 'categories.xml', 0666);
    }

    /**
     * @param string $package name of the package
     * @param string
     */
    public function deleteReleaseREST($package, $version)
    {
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

    /**
     * Serialize dependencies REST for a release
     *
     * @param string $package
     * @param string $version
     * @param array $deps
     */
    public function savePackageDepsREST($package, $version, $deps)
    {
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

    public function saveAllPackagesREST()
    {
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        $pdir = $this->_restdir . DIRECTORY_SEPARATOR . 'p';

        $info = '<?xml version="1.0" encoding="iso-8859-1" ?>
<a xmlns="http://pear.php.net/dtd/rest.allpackages"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.allpackages
    http://pear.php.net/dtd/rest.allpackages.xsd">
 <c>' . htmlspecialchars($this->_channel) . '</c>
';
        foreach ($this->_backend->listPackages(false, false, false) as $package)
        {
            $info .= ' <p>' . $package['package'] . '</p>
';
        }
        $info .= '</a>';
        file_put_contents($pdir . DIRECTORY_SEPARATOR . 'packages.xml', $info);
        @chmod($pdir . DIRECTORY_SEPARATOR . 'packages.xml', 0666);
    }

    public function savePackageMaintainersREST($package)
    {
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        $maintainers = $this->_backend->listPackageMaintainers($package);
        if (count($maintainers)) {
            $pdir = $this->_restdir . DIRECTORY_SEPARATOR . 'p';
            if (!file_exists($pdir . DIRECTORY_SEPARATOR . strtolower($package))) {
                System::mkdir(array('-p', $pdir . DIRECTORY_SEPARATOR . strtolower($package)));
                @chmod($pdir . DIRECTORY_SEPARATOR . strtolower($package), 0777);
            }
            $info = '<?xml version="1.0" encoding="iso-8859-1" ?>
<m xmlns="http://pear.php.net/dtd/rest.packagemaintainers"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.packagemaintainers
    http://pear.php.net/dtd/rest.packagemaintainers.xsd">
 <p>' . $package . '</p>
 <c>' . htmlspecialchars($this->_channel) . '</c>
';
            foreach ($maintainers as $maintainer) {
                $info .= ' <m><h>' . $maintainer->handle . '</h><a>' .
                    ($maintainer->active ? '1' : '0') .
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

    public function saveAllMaintainersREST()
    {
        $channelinfo = parse_url($this->_channel);
        if (isset($channelinfo['host'])) {
            $extra = $channelinfo['path'] . '/Chiara_PEAR_Server_REST/';
        } else {
            $extra = '/Chiara_PEAR_Server_REST/';
        }
        $maintainers = $this->_backend->listMaintainers();
        $info = '<?xml version="1.0" encoding="UTF-8" ?>
<m xmlns="http://pear.php.net/dtd/rest.allmaintainers"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.allmaintainers
    http://pear.php.net/dtd/rest.allmaintainers.xsd">' . "\n";
        // package information
        foreach ($maintainers as $maintainer) {
            $info .= ' <h xlink:href="' . $extra . 'm/' . $maintainer->handle . '">' .
                $maintainer->handle . '</h>' . "\n";
        }
        $info .= '</m>';
        $mdir = $this->_restdir . DIRECTORY_SEPARATOR . 'm';
        if (!is_dir($mdir)) {
            System::mkdir(array('-p', $mdir));
            @chmod($mdir, 0777);
        }
        file_put_contents($mdir . DIRECTORY_SEPARATOR . 'allmaintainers.xml', $info);
        @chmod($mdir . DIRECTORY_SEPARATOR . 'allmaintainers.xml', 0666);
    }

    public function saveMaintainerREST(Chiara_PEAR_Server_Maintainer $maintainer)
    {
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
        $info = '<?xml version="1.0" encoding="iso-8859-1" ?>
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

    public function deleteMaintainerREST(Chiara_PEAR_Server_Maintainer $maintainer)
    {
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
?>