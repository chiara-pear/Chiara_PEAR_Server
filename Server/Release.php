<?php
require_once 'PEAR/PackageFile.php';
require_once 'Archive/Tar.php';
class Chiara_PEAR_Server_Release
{
    protected $_packageFile;
    /**
     * @var PEAR_PackageFile
     */
    protected $_packageInfo;
    /**
     * @var string
     */
    protected $_releaser;
    /**
     * package.xml contents
     * @var string
     */
    protected $_xml;
    
    public function Chiara_PEAR_Server_Release($path, $releaser, $conf, $tmpdir = false)
    {
        $this->_packageFile = $path;
        $pkg = new PEAR_PackageFile($conf, false, $tmpdir);
        $this->_packageInfo = $pkg->fromTgzFile($path, PEAR_VALIDATE_NORMAL);
        if (class_exists('PEAR_Error') && $this->_packageInfo instanceof PEAR_Error) {
            throw new Chiara_PEAR_Server_ExceptionInvalidRelease($this->_packageInfo);
        }
        $tar = new Archive_Tar($path);
        if ($a = $tar->extractInString('package2.xml')) {
            $this->_xml = $a;
        } else {
            $this->_xml = $tar->extractInString('package.xml');
        }
        $this->_releaser = $releaser;
    }

    public function validate()
    {
        return $this->_packageInfo->validate();
    }

    public function getXml()
    {
        return $this->_xml;
    }

    public function getChannel()
    {
        return $this->_packageInfo->getChannel();
    }

    public function getPackage()
    {
        return $this->_packageInfo->getPackage();
    }

    public function getState()
    {
        return $this->_packageInfo->getState();
    }

    public function getLicense()
    {
        return $this->_packageInfo->getLicense();
    }

    public function getSummary()
    {
        return $this->_packageInfo->getSummary();
    }

    public function getVersion()
    {
        return $this->_packageInfo->getVersion();
    }

    public function getNotes()
    {
        return $this->_packageInfo->getNotes();
    }

    public function getDescription()
    {
        return $this->_packageInfo->getDescription();
    }

    public function getDate()
    {
        return $this->_packageInfo->getDate();
    }

    public function getFilepath()
    {
        return $this->_packageFile;
    }

    public function setFilepath($path)
    {
        $this->_packageFile = $path;
    }

    public function getMaintainers()
    {
        return $this->_packageInfo->getMaintainers();
    }
    
    public function getReleasingMaintainer()
    {
        return $this->_releaser;
    }

    public function getDependencies()
    {
        return $this->_packageInfo->getDependencies();
    }

    public function getDeps($v2deps = true)
    {
        if ($v2deps) {
            $deps = $this->_packageInfo->getDeps(true);
            foreach ($deps['required'] as $i => $dep) {
                if (!isset($dep[0])) {
                    $deps['required'][$i] = array($dep);
                }
            }
            if (isset($deps['optional'])) {
                foreach ($deps['optional'] as $i => $dep) {
                    if (!isset($dep[0])) {
                        $deps['optional'][$i] = array($dep);
                    }
                }
            }
            if (isset($deps['group'])) {
                if (!isset($deps['group'][0])) {
                    $deps['group'] = array($deps['group']);
                }
                foreach ($deps['group'] as $j => $g) {
                    foreach ($g as $i => $dep) {
                        if (!isset($dep[0])) {
                            $deps['group'][$j][$i] = array($dep);
                        }
                    }
                }
            }
        } else {
            $deps = $this->_packageInfo->getDeps();
        }
        return $deps;
    }
}
?>