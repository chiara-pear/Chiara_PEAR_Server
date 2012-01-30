<?php
require_once 'Chiara/PEAR/Server/Frontend.php';
require_once 'HTML/QuickForm.php';
class Chiara_PEAR_Server_Frontend_HTMLQuickForm extends Chiara_PEAR_Server_Frontend
{

    /**
     * @var HTML_QuickForm
     */
    protected $_quickForm;
    protected $_index;
    protected $_tmpdir;
    protected $_user = false;
    protected $_admin = false;
    public function __construct($channel, $qf, $index, $dir)
    {
        parent::__construct($channel);
        $this->_quickForm = $qf;
        $this->_index = $index;
        $this->_tmpdir = $dir;
    }

    public function sessionStart()
    {
        session_start();
        if (session_is_registered('_currentUser') &&
              is_array($_SESSION['_currentUser']) &&
              isset($_SESSION['_currentUser'][$this->_channel])) {
            $this->_user = $_SESSION['_currentUser'][$this->_channel];
            $this->_admin = $_SESSION['_currentUserAdmin'][$this->_channel];
        } else {
            $this->_user = false;
            $this->_admin = false;
            @session_register('_currentUser');
            @session_register('_currentUserAdmin');
            if (!is_array($_SESSION['_currentUser'])) {
                $_SESSION['_currentUser'] = array();
            }
            $_SESSION['_currentUser'][$this->_channel] = false;
            if (!is_array($_SESSION['_currentUserAdmin'])) {
                $_SESSION['_currentUserAdmin'] = array();
            }
            $_SESSION['_currentUserAdmin'][$this->_channel] = false;
        }
    }

    public function setServer($server)
    {
        parent::setServer($server);
        $this->_server->addMethodIndex('managePackage');
        $this->_server->addMethodIndex('manageMaintainer');
        $this->_server->addMethodIndex('addMaintainer');
        $this->_server->addMethodIndex('managePackageMaintainers');
        $this->_server->addMethodIndex('login');
        $this->_server->addMethodIndex('myAccount');
    }

    /**
     * Return output to the user from a function
     * @param mixed
     */
    public function funcReturn($output, $class, $method)
    {
        return $output;
    }

    public function main()
    {
        if (isset($_REQUEST['logout'])) {
            $this->_user = false;
            session_destroy();
        }
        if (!$this->_user) {
            return $this->doMainMenu('doLogin', false);
        }
        $func = $this->_server->getMethod(@$_REQUEST['f']);
        if ($func !== false) {
            $this->parseInput($func);
        } else {
            if (isset($_REQUEST['f'])) {
                //error
            } else {
                return $this->doMenu();
            }
        }
    }

    public function parseInput($func)
    {
        switch ($func) {
            case 'managePackage' :
                $this->doMainMenu('doManagePackage', $func, $_REQUEST['managepackage']);
            break;
            case 'managePackageMaintainers' :
                $this->doMainMenu('doManagePackageMaintainers', $func, $_REQUEST['managepackage']);
            break;
            case 'manageMaintainer' :
                $this->doMainMenu('doManageMaintainer', $func, $_REQUEST['managemaintainer']);
            break;
            case 'manageCategory' :
                $this->doMainMenu('doManageCategory', $func, $_REQUEST['managecategory']);
            break;
            case 'addMaintainer' :
                $this->doMainMenu('doAddMaintainer', $func);
            break;
            case 'addCategory' :
                $this->doMainMenu('doAddCategory', $func);
            break;
            case 'addRelease' :
                $this->doMainMenu('doAddRelease', $func);
            break;
            case 'deleteRelease' :
                $this->doMainMenu('doDeleteRelease', $func);
            break;
            case 'getDownloadURL' :
            case 'addPackage' :
                $this->doMainMenu('doAddPackage', $func);
            break;
            case 'myAccount' :
                $this->doMainMenu('doMyAccount', $func);
                break;
            case 'listPackages' :
            	$this->doMainMenu('doListPackages', $func);
            	break;
            case 'listReleases' :
            case 'listLatestReleases' :
            case 'packageInfo' :
            default:
                return $this->doMenu();
            break;
        }
    }

    public function doLogin()
    {
        $this->_quickForm->addElement('header', '', 'Log in');
        $this->_quickForm->addElement('text', 'user', 'User Name');
        $this->_quickForm->addElement('password', 'password', 'Password');
        $this->_quickForm->addElement('submit', 'login', 'Submit');
        $this->_quickForm->addRule('user', 'Required', 'required');
        $this->_quickForm->addRule('password', 'Required', 'required');
        if (isset($_REQUEST['login'])) {
            if ($this->_quickForm->validate()) {
                if ($this->_backend->validLogin(
                trim(strtolower($this->_quickForm->getSubmitValue('user'))),
                trim($this->_quickForm->getSubmitValue('password')))) {
                    $_SESSION['_currentUser'][$this->_channel] = trim(strtolower($this->_quickForm->getSubmitValue('user')));
                    session_write_close();
                    $this->_user = trim(strtolower($this->_quickForm->getSubmitValue('user')));
                    if ($this->_backend->isAdmin($this->_user)) {
                        $this->_admin = true;
                    } else {
                        $this->_admin = false;
                    }
                    $_SESSION['_currentUserAdmin'][$this->_channel] = $this->_admin;
                    header('Location: ' .$this->_index);
                    return true;
                } else {
                    echo "<p><strong class='error'>Invalid Login</strong></p>";
                }
            }
        }
        echo $this->_quickForm->toHtml();
        echo '<p>You will need cookies</p>';
        echo '<a onclick="history.go(-1);">Go Back</a>';
    }

    public function doMenu()
    {
        return $this->doMainMenu('welcome', false);
    }

    public function welcome()
    {
        ?>
        <h2>Welcome</h2>
        <p>
            Welcome to the channel administration for <strong><?php echo $this->_channel; ?></strong>.
        </p>
        <p>
        <?php
        if ($this->_backend->isAdmin($this->_user)) {
                ?>
                From here, you will be able to control your channel server. This includes creating and maintaining
                package categories, adding and maintaining new packages, uploading releases, adding and maintaining package
                developers and more.
                <?php
        } else {
                ?>
                From here you will be able to maintain your packages by using the menu on the left.
                <?php
        }
        ?>
        </p>
        <?php
    }

    public function doMainMenu($menu, $func, $param = null)
    {
        ob_start();
        $this->$menu($func, $param);
        $content = ob_get_contents();
        ob_end_clean();
        ?>
        <html>
            <head>
                <title>Channel <?php echo $this->_channel; ?> Server Administration</title>
                <link rel="stylesheet" type="text/css" href="pear_server.css" />
            </head>
            <body>
                <div id="top">
                    <h1><a href="<?php echo $this->_index; ?>">Channel Administration</a></h1>
                    <?php
                    if ($this->_user) {
                            ?>
                    <p>
                        Logged in as <strong><?php echo $this->_user; ?></strong> | <a href="<?php echo $this->_index; ?>?f=<?php echo $this->_server->getMethodIndex('myAccount'); ?>">My Account</a> | <a href="<?php echo $this->_index; ?>?logout=1">Logout</a> <br /> Managing Channel<strong> <a href="http://<?php
                        echo $this->_channel; ?>"><?php echo $this->_channel; ?></a></strong>
                    </p>
                            <?php
                    }
                    ?>
                </div>
                <div id="menu">
                    <?php $this->adminMenu(); ?>
                </div>
                <div id="content">
                    <?php echo $content; ?>
                </div>
            </body>
        </html>
        <?php
    }

    public function userMenu()
    {
        ?>
        <ul id="nav">
            <li><a href="<?php echo $this->_index; ?>?f=<?php echo $this->_server->getMethodIndex('addRelease'); ?>">Upload a Release</a></li>
        </ul>
        <?php
        echo '<h2><a href="'.$this->_index. '?f=' .$this->_server->getMethodIndex('listPackages').'">Manage Packages</a></h2>';
        
    }

    public function adminMenu()
    {
        if (!$this->_backend->isAdmin($this->_user) && $this->_user) {
            return $this->userMenu();
        } elseif (!$this->_user) {
            return;
        }

        ?>
        <ul id="nav">
            <li><a href="<?php echo $this->_index; ?>?f=<?php echo $this->_server->getMethodIndex('addCategory'); ?>">Create a Category</a></li>
            <li><a href="<?php echo $this->_index; ?>?f=<?php echo $this->_server->getMethodIndex('addPackage'); ?>">Create a Package</a></li>
            <li><a href="<?php echo $this->_index; ?>?f=<?php echo $this->_server->getMethodIndex('addRelease'); ?>">Upload a Release</a></li>
            <li><a href="<?php echo $this->_index; ?>?f=<?php echo $this->_server->getMethodIndex('addMaintainer'); ?>">Add a Maintainer</a></li>
        </ul>
        <h2>Manage Categories</h2>
        <ul>
        <?php
        foreach ($this->_backend->listCategories() as $category) {
            echo '<li><a href="' .$this->_index. '?f=' .$this->_server->getMethodIndex('manageCategory') .'&amp;managecategory=' .$category['name']. '">'. $category['name'] . "</a></li>";
        }
        $url = $this->_index. '?f=' .$this->_server->getMethodIndex('listPackages');
        ?>
        </ul>
        <h2><a href="<?php echo $url; ?>">Manage Packages</a></h2>
        <h2>Manage Maintainers</h2>
        <ul>
        <?php
        foreach ($this->_backend->listMaintainers() as $maintainer) {
            echo '<li><a href="' . $this->_index . '?f=' . $this->_server->getMethodIndex('manageMaintainer') . '&managemaintainer=' . $maintainer->handle . '">' . $maintainer->name . ' (' . $maintainer->handle . ')' . "</a></li>";
        }
        ?>
        </ul>
        <?php
    }

    public function doAddRelease($func)
    {
        $this->_quickForm->setDefaults(array(
        'submitted' => 1,
        'f' => $_REQUEST['f'],
        ));
        $this->_quickForm->setConstants(array('f' => $this->_server->getMethodIndex($func)));
        $this->_quickForm->addElement('header', '', 'Upload a Package Release');
        $this->_quickForm->addElement('file', 'release', '.tgz release');
        if ($this->_backend->isAdmin($this->_user)) {
            //only channel admins may automatically add packages and users
            $this->_quickForm->addElement('checkbox', 'createpackage', 'Create package in database if missing');
            $this->_quickForm->addElement('checkbox', 'createuser', 'Create users in database if missing');
        }
        $this->_quickForm->addElement('hidden', 'submitted', '1');
        $this->_quickForm->addElement('hidden', 'f', 'f');
        $this->_quickForm->addElement('submit', 'Submit', 'Submit');
        $this->_quickForm->addRule('release', 'Required', 'required');
        $this->_quickForm->addRule('release', 'Must be a valid file on your computer', 'uploadedfile');
        if (isset($_REQUEST['submitted']) && $this->_quickForm->validate()) {
            $file = $this->_quickForm->getElement('release');
            if ($this->_backend->isAdmin($this->_user)) {
                $createpackage = $this->_quickForm->getElementValue('createpackage');
                $createuser    = $this->_quickForm->getElementValue('createuser');
            } else {
                $createpackage = false;
                $createuser    = false;
            }
            if ($file->isUploadedFile()) {
                $fullpath = str_replace(array('/', '\\'), array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR),
                tempnam($this->_tmpdir, 'upl'));
                $file->moveUploadedFile($this->_tmpdir, basename($fullpath));
                $release = new Chiara_PEAR_Server_Release(
                    $fullpath, $this->_user, PEAR_Config::singleton(), $this->_tmpdir
                );
                try {
                    if ($this->_server->addRelease($release, true, $createpackage, $createuser)) {
                        echo 'Release successfully saved<br />';
                    } else {
                        echo '<strong>Error:</strong> Saving release failed<br />';
                        echo $this->_quickForm->toHtml();
                    }
                } catch (Chiara_PEAR_Server_Exception $e) {
                    echo '<strong>Error:</strong> ' . $e->getMessage() . '<br />';
                    echo $this->_quickForm->toHtml();
                }
            } else {
                echo '<strong>Error:</strong> Not uploaded file<br />';
                echo $this->_quickForm->toHtml();
            }
            return;
        } else {
            if (isset($_FILES['release']['error'])) {
                switch ($_FILES['release']['error']) {
                    case UPLOAD_ERR_OK:
                        $error = 'No upload error';
                        break;
                    case UPLOAD_ERR_INI_SIZE:
                        $error = 'File size exceeds the upload_max_filesize directive in php.ini';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = 'File size exceeds the MAX_FILE_SIZE directive in the form';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error = 'File has been uploaded partially only';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error = 'No file has been uploaded';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error = 'No temporary directory';
                        break;
                    case 7://UPLOAD_ERR_CANT_WRITE: (since 5.1.0 only)
                        $error = 'Failed to write file to disk';
                        break;
                    case 8://UPLOAD_ERR_EXTENSION: (since 5.2.0 only)
                        $error = 'File upload stopped by extension';
                        break;
                    default:
                        $error = 'Unknown upload error';
                        break;
                }
                echo '<strong>Error:</strong> ' . $error . '<br />';
            }
            echo $this->_quickForm->toHtml();
        }
    }

    public function doAddCategory($fn, $category)
    {
        $defaults = array();
        $defaults['f'] = $_REQUEST['f'];
        $defaults['submitted'] = 1;
        $defaults['channel'] = $this->_channel;
        $this->_quickForm->setDefaults($defaults);
        $this->_quickForm->setConstants(array('f' => $this->_server->getMethodIndex($fn)));
        $this->_quickForm->addElement('header', '', 'Add a New Category');
        $this->_quickForm->addElement('static', 'channel', 'Channel');
        $this->_quickForm->addElement('text', 'name', 'Name');
        $this->_quickForm->addElement('text', 'description', 'Category Description');
        $this->_quickForm->addElement('text', 'alias', 'Category Alias');
        $this->_quickForm->addElement('hidden', 'f', 'f');
        $this->_quickForm->addElement('hidden', 'submitted', '1');
        $this->_quickForm->addElement('submit', 'save', 'Save Changes');
        $this->_quickForm->addRule('name', 'Required', 'required');
        if (isset($_REQUEST['submitted'])) {
            if ($this->_quickForm->validate()) {
                try {
                    $spack->name = trim($this->_quickForm->getSubmitValue('name'));
                    $spack->channel = $this->_channel;
                    $spack->description = trim($this->_quickForm->getSubmitValue('description'));
                    $spack->alias = trim($this->_quickForm->getSubmitValue('alias'));
                    $result = $this->_backend->addCategory($spack);
                    if ($result) {
                        $this->_quickForm->removeElement('save');
                        $this->_quickForm->freeze();
                        echo '<strong>Category Successfully Added</strong>';
                    } else {
                        echo '<strong>Warning: adding category failed';
                    }
                } catch (Chiara_PEAR_Server_Exception $e) {
                    echo "<strong>Error</strong> " . $e->getMessage();
                }
            }
        }
        echo $this->_quickForm->toHtml();
    }

    public function doManagePackage($fn, $package)
    {
        try {
            if (isset($_REQUEST['deleteRelease'])) {
                if ($this->_server->deleteRelease($this->_channel, $package, $_REQUEST['deleteRelease']));
            }
            if (isset($_REQUEST['deletePackage'])) {
                if (!$this->_backend->deletePackage($package)) {
                    throw new Chiara_PEAR_Server_ExceptionPackageDoesntExist($package, $this->_channel);
                }
                $this->_quickForm->addElement('header', '', 'Package "' . $package
                    . '" deleted successfully');
                echo $this->_quickForm->toHtml();
                return;
            }
            $delete = '<a href="' . $this->_index . '?f=' .
            $this->_server->getMethodIndex('deletePackage') . '&managepackage=' . $package;
            $self = '<a href="' . $this->_index . '?f=' .
            $this->_server->getMethodIndex('managePackage') . '&managepackage=' . $package;
            $info = $this->_backend->getPackage($package);
            foreach ($this->_backend->getPackageExtras($package) as $key => $value) {
                $info->{$key} = $value;
            }
            if (isset($_REQUEST['submitted'])) {
                $stuff = $this->_quickForm->getSubmitValues();
                foreach ($stuff as $name => $value) {
                    $info->$name = $value;
                }
                $this->_backend->updatePackage($info);
                $this->_quickForm->addElement('header', '', 'Data Saved');
            }

            $defaults = $info->toArray();
            $defaults['managepackage'] = $info->name;
            $defaults['f'] = $_REQUEST['f'];
            $this->_quickForm->setDefaults($defaults);
            $this->_quickForm->addElement('header', '', 'Edit Package Information');
            $this->_quickForm->addElement('static', 'channel', 'Channel');
            $this->_quickForm->addElement('text', 'name', 'Name');
            $c = $this->_backend->listCategories();
            $categories = array('');
            foreach ($c as $category) {
                $categories[$category['id']] = $category['name'];
            }
            $select =& $this->_quickForm->addElement('select', 'category_id', 'Category', $categories);
            $select->setValue($defaults['category_id']);
            $this->_quickForm->addElement('text', 'license', 'License');
            $this->_quickForm->addElement('text', 'license_uri', 'License URL');
            $this->_quickForm->addElement('hidden', 'f', 'License');
            $this->_quickForm->addElement('hidden', 'managepackage', 'License');
            $this->_quickForm->addElement('text', 'summary', 'Summary', array('size' => 40));
            $this->_quickForm->addElement('textarea', 'description', 'Description', array('cols' => 40, 'rows' => 5));
            $p = $this->_backend->listPackages(false, false, false);
            $packages = array('');
            foreach ($p as $pack) {
                if ($package['package'] == $defaults['name']) {
                    continue 2;
                }
                $packages[$pack['package']] = $pack['package'];
            }
            $this->_quickForm->addElement('select', 'parent', 'Parent Package', $packages);
            $this->_quickForm->getElement('parent')->setSelected($info->parent);
            $this->_quickForm->addElement('text', 'cvs_uri', 'Web CVS URL');
            $this->_quickForm->addElement('text', 'bugs_uri', 'Bug Tracker URL');
            $this->_quickForm->addElement('text', 'docs_uri', 'Documentation URL');
            $this->_quickForm->addElement('header', 'depheader', 'Deprecated in Favor of');
            $this->_quickForm->addElement('text', 'deprecated_channel', 'New Package Channel');
            $this->_quickForm->addElement('text', 'deprecated_package', 'New Package Name');
            $this->_quickForm->addElement('submit', 'submitted', 'Save Changes');
            $this->_quickForm->addElement('submit', 'deletePackage', 'Delete Package');
            $this->_quickForm->addRule('name', 'Required', 'required');
            $this->_quickForm->addRule('license', 'Required', 'required');
            $this->_quickForm->addRule('summary', 'Required', 'required');
            $this->_quickForm->addRule('description', 'Required', 'required');
            echo $this->_quickForm->toHtml();
            $releases = $this->_backend->listReleases($package);
            if (count($releases)) {
                echo "Delete Existing Releases:<br />\n";
                array_reverse($releases);
                foreach ($releases as $version => $info) {
                    echo $self . '&deleteRelease=' . urlencode($version) . '">X</a> Version ' .
                    $version . ', Released on ' . $info['releasedate'] . ' by ' . $info['maintainer'] . "<br />\n";
                }
            }
        } catch (Chiara_PEAR_Server_Exception $e) {
            echo "<strong>Error</strong> " . $e->getMessage();
        }
    }

    public function doListPackages()
    {
        require_once 'HTML/Table.php';
        echo '<p>Choose a package to manage</p>';
    	$table = new HTML_Table();
        $table->addRow(array('Package Name','Maintainers'),array(),'TH');
        foreach ($this->_backend->listPackages(false, false, false) as $package) {
            if (!$this->_backend->packageLead($package['package'], $this->_user)) {
                continue;
            }
            $table->addRow(array('<a href="' . $this->_index . '?f=' . $this->_server->getMethodIndex('managePackage') . '&amp;managepackage=' . $package['package'] . '">' . $package['package'] . '</a>',
            	' <a href="' . $this->_index . '?f=' . $this->_server->getMethodIndex('managePackageMaintainers') . '&amp;managepackage=' . $package['package'] .
            '">(Maintainers)</a>'));
        }
        echo $table->toHtml();
        if ($this->_backend->isAdmin($this->_user)) {
            echo '<p><a href="' . $this->_index . '?f=' . $this->_server->getMethodIndex('addPackage') .'">Create a Package</a></p>';
        }
    }
    
    public function doManageCategory($fn, $category)
    {
        if ($category == "Default") {
            $defaults = array("channel" => $this->_channel, "name" => "Default", "description" => "System Default Category. This Category cannot be edited.", "alias" => "");
            $this->_quickForm->setDefaults($defaults);
            $this->_quickForm->setConstants(array('managecategory' => "Default"));
            $this->_quickForm->addElement('header', '', 'Category Information');
            $this->_quickForm->addElement('static', 'channel', 'Channel');
            $this->_quickForm->addElement('static', 'name', 'Name');
            $this->_quickForm->addElement('static', 'description', 'Description');
            $this->_quickForm->addElement('hidden', 'f', 'f');
            $this->_quickForm->addElement('hidden', 'managecategory', "Manage Category");
            echo $this->_quickForm->toHtml();
            return;
        }
        try {
            if (isset($_REQUEST['deleteCategory'])) {
                if (!$this->_backend->deleteCategory($category)) {
                    throw new Chiara_PEAR_Server_ExceptionCategoryDoesntExist($_REQUEST, $this->_channel);
                }
                $this->_quickForm->addElement('header', '', 'Category "' . $category.
                    '" deleted successfully');
                echo $this->_quickForm->toHtml();
                return;
            }
            $self = '<a href="' . $this->_index . '?f=' .
            $this->_server->getMethodIndex('managePackage') . '&managepackage=' . $category;
            $info = $this->_backend->getCategory($category);
            if (isset($_REQUEST['submitted'])) {
                $stuff = $this->_quickForm->getSubmitValues();
                foreach ($stuff as $name => $value) {
                    $info->$name = $value;
                }
                $result = $this->_backend->updateCategory($info);
                if ($result === false) {
                    throw new Chiara_PEAR_Server_ExceptionCategoryNoUpdate($stuff['name'], $this->_channel);
                } elseif ($result !== 0) {
                    $this->_quickForm->addElement('header', '', 'Category Updated');
                }
            }

            $defaults = $info->toArray();
            $defaults['f'] = $_REQUEST['f'];
            $this->_quickForm->setDefaults($defaults);
            $this->_quickForm->setConstants(array('managecategory' => $info->name));
            $this->_quickForm->addElement('header', '', 'Edit Category Information');
            $this->_quickForm->addElement('static', 'channel', 'Channel');
            $this->_quickForm->addElement('text', 'name', 'Name');
            $this->_quickForm->addElement('text', 'description', 'Description');
            $this->_quickForm->addElement('text', 'alias', 'Alias');
            $this->_quickForm->addElement('hidden', 'f', 'f');
            $this->_quickForm->addElement('hidden', 'managecategory', "Manage Category");
            $this->_quickForm->addElement('submit', 'submitted', 'Save Changes');
            $this->_quickForm->addElement('submit', 'deleteCategory', 'Delete Category');
            $this->_quickForm->addRule('name', 'Required', 'required');
            echo $this->_quickForm->toHtml();
        } catch (Chiara_PEAR_Server_Exception $e) {
            echo "<strong>Error</strong> " . $e->getMessage();
        }
    }

    public function doManagePackageMaintainers($fn, $package)
    {
        try {
            $self = '<a href="' . $this->_index . '?f=' .
            $this->_server->getMethodIndex('managePackageMaintainers') . '&managepackage=' . $package;
            $this->_quickForm->addElement('static', 'channel', 'Channel');
            $this->_quickForm->addElement('static', 'package', 'Package Name');
            $this->_quickForm->addElement('header', '', 'Add Maintainer To Package');
            $oldroles = PEAR_Common::getUserRoles();
            $roles = array();
            foreach ($oldroles as $role) {
                $roles[$role] = $role;
            }
            $oldmaints = $this->_backend->listMaintainers(true);
            $maints = array('' => '');
            foreach ($oldmaints as $om) {
                $maints[$om] = $om;
            }
            $this->_quickForm->addElement('select', 'newhandle', 'New Maintainer', $maints);
            array_unshift($roles, '');
            $this->_quickForm->addElement('select', 'newrole', 'Role', $roles);
            $this->_quickForm->addElement('select', 'newactive', 'Active Maintainer?', array('' => '', 0 => "No", 1=> "Yes"));
            $this->_quickForm->addElement('submit', 'addmaintainer', 'Add Maintainer');
            $this->_quickForm->addFormRule(array($this, 'checkEmpty'));
            if (isset($_REQUEST['submitted'])) {
                if ($this->_quickForm->getSubmitValue('savechanges') == 'Save Changes') {
                    $submitted = $this->_quickForm->getSubmitValues();
                    foreach ($this->_backend->listPackageMaintainers($package) as $maintainer) {
                        $groupname = 'group' . $maintainer->handle;
                        $values = $submitted[$groupname];
                        $info = new Chiara_PEAR_Server_MaintainerPackage($values);
                        $info->handle = $maintainer->handle;
                        $info->channel = $this->_channel;
                        $info->package = $package;
                        if ($this->_backend->updatePackageMaintainer($info)) {
                            $this->_quickForm->addElement('header', '', 'Changes to ' . $info->handle . ' Saved');
                        } else {
                            $this->_quickForm->addElement('header', '', 'Changes to ' . $info->handle . ' Failed!');
                        }
                    }
                } elseif ($this->_quickForm->getSubmitValue('addmaintainer') == 'Add Maintainer') {
                    if ($this->_quickForm->validate()) {
                        $info = new Chiara_PEAR_Server_MaintainerPackage;
                        $info->channel = $this->_channel;
                        $info->package = $package;
                        $info->handle = $this->_quickForm->getSubmitValue('newhandle');
                        $info->role = $this->_quickForm->getSubmitValue('newrole');
                        $info->active = $this->_quickForm->getSubmitValue('newactive');
                        try {
                            if ($this->_backend->addPackageMaintainer($info)) {
                                $this->_quickForm->addElement('header', '', 'Addition of ' . $info->handle . ' to Package Succeeded');
                            } else {
                                $this->_quickForm->addElement('header', '', 'Addition of ' . $info->handle . ' Failed!');
                            }
                        } catch (Chiara_PEAR_Server_Exception $e) {
                            echo "<strong>Error</strong> " . $e->getMessage() . '<br />';
                        }
                    }
                }
            }
            $defaults = array();
            $defaults['channel'] = $this->_channel;
            $defaults['submitted'] = 1;
            $defaults['package'] = $package;
            $defaults['managepackage'] = $package;
            $defaults['f'] = $_REQUEST['f'];
            $this->_quickForm->setDefaults($defaults);
            $this->_quickForm->addElement('header', '', 'Edit Package Maintainers');
            $this->_quickForm->addElement('hidden', 'submitted', '');
            $this->_quickForm->addElement('hidden', 'f', 'License');
            $this->_quickForm->addElement('hidden', 'managepackage', 'License');
            $this->_quickForm->addElement('header', '', '<div align="right">Role | Active</div>');
            $genericrole = HTML_QuickForm::createElement('select', 'role', '', $roles);
            $genericactive = HTML_QuickForm::createElement('select', 'active', '', array(1 => "Yes", 0 => "No" ));
            foreach ($this->_backend->listPackageMaintainers($package) as $maintainer) {
                $role = clone $genericrole;
                $active = clone $genericactive;
                $role->setSelected($maintainer->role);
                $active->setSelected($maintainer->active);
                $this->_quickForm->addGroup(array($role, $active), 'group' . $maintainer->handle, $maintainer->handle, ' | ');
            }
            $this->_quickForm->addElement('submit', 'savechanges', 'Save Changes');
            echo $this->_quickForm->toHtml();
        } catch (Chiara_PEAR_Server_Exception $e) {
            echo "<strong>Error</strong> " . $e->getMessage();
        }
    }

    public function checkEmpty($handle)
    {
        if ($_REQUEST['addmaintainer'] == 'Add Maintainer') {
            $ret = array();
            if (trim($handle['newhandle']) == '' ||
            trim($handle['newactive'] == '') ||
            trim($handle['newrole'] == '')) {
                if (trim($handle['newhandle']) == '') {
                    $ret['newhandle'] = 'Required';
                }
                if (trim($handle['newactive']) == '') {
                    $ret['newactive'] = 'Required';
                }
                if (trim($handle['newrole']) == '') {
                    $ret['newrole'] = 'Required';
                }
                return $ret;
            }
        }
        return true;
    }

    public function doManageMaintainer($fn, $maintainer)
    {
        try {
            $info = $this->_backend->getMaintainer($maintainer);
            if (isset($_REQUEST['deleteMaintainer'])) {
                if (!$this->_backend->deleteMaintainer($info)) {
                    throw new Chiara_PEAR_Server_ExceptionMaintainerDoesntExist($maintainer);
                }
                $this->_quickForm->addElement('header', '', 'Maintainer "' . $maintainer
                    . '" deleted successfully');
                echo $this->_quickForm->toHtml();
                return;
            }
            if (isset($_REQUEST['submitted'])) {
                if ($this->_quickForm->validate()) {
                    $stuff = $this->_quickForm->getSubmitValues();
                    foreach ($stuff as $name => $value) {
                        $info->$name = $value;
                    }
                    $this->_backend->updateMaintainer($info);
                    $this->_quickForm->addElement('header', '', 'Data Saved');
                }
            }
            $defaults = $info->toArray();
            $defaults['managemaintainer'] = $info->handle;
            $defaults['f'] = $_REQUEST['f'];
            $defaults['password'] = '';
            $this->_quickForm->setDefaults($defaults);
            $this->_quickForm->addElement('header', '', 'Edit Maintainer Information');
            $this->_quickForm->addElement('static', 'handle', 'Handle');
            $this->_quickForm->addElement('text', 'name', 'Name', array('size' => 40));
            $this->_quickForm->addElement('hidden', 'f', 'License');
            $this->_quickForm->addElement('hidden', 'managemaintainer', 'License');
            $this->_quickForm->addElement('text', 'email', 'Email', array('size' => 40));
            $this->_quickForm->addElement('checkbox', 'admin', 'Channel Administrator');
            if ($this->_backend->isAdmin($this->_user)) {
                //admins may change the dev's password
                $this->_quickForm->addElement('password', 'password', 'Password');
                $this->_quickForm->addElement('password', 'confirm_password', 'Confirm Password');
                $this->_quickForm->addRule('password', 'Password must be at least 6 characters long', 'minlength', 6, 'client');
                $this->_quickForm->addRule(array('password', 'confirm_password'), "The passwords do not match", 'compare', null, 'client');
            }
            $this->_quickForm->addElement('submit', 'submitted', 'Save Changes');
            $this->_quickForm->addRule('name', 'Required', 'required');
            $this->_quickForm->addRule('email', 'Required', 'required');
            echo $this->_quickForm->toHtml();
        } catch (Chiara_PEAR_Server_Exception $e) {
            echo "<strong>Error</strong> " . $e->getMessage();
        }
    }

    public function doMyAccount($fn)
    {
        try {
            $channel_usernames = array_values($_SESSION['_currentUser']);
            $info = $this->_backend->getMaintainer($channel_usernames[0]);
            if (isset($_REQUEST['submitted'])) {
                if ($this->_quickForm->validate()) {
                    $stuff = $this->_quickForm->getSubmitValues();
                    foreach ($stuff as $name => $value) {
                        $info->$name = $value;
                    }
                    $this->_backend->updateMaintainer($info);
                    $this->_quickForm->addElement('header', '', 'Data Saved');
                }
            }
            $defaults = $info->toArray();
            $defaults['managemaintainer'] = $info->handle;
            $defaults['password'] = "";
            $defaults['f'] = $_REQUEST['f'];
            $this->_quickForm->setDefaults($defaults);
            $this->_quickForm->addElement('header', '', 'My Account');
            $this->_quickForm->addElement('static', 'handle', 'Handle');
            $this->_quickForm->addElement('text', 'name', 'Name', array('size' => 40));
            $this->_quickForm->addElement('hidden', 'f', 'myAccount');
            $this->_quickForm->addElement('hidden', 'myaccount', 'License');
            $this->_quickForm->addElement('text', 'email', 'Email', array('size' => 40));
            $this->_quickForm->addElement('text', 'uri', 'Website', array('size' => 40));
            $this->_quickForm->addElement('text', 'wishlist', 'Wishlist', array('size' => 40));
            $this->_quickForm->addElement('textarea', 'description', 'About', array('cols' => 40, "rows" => 15));
            $this->_quickForm->addElement('password', 'password', 'Password');
            $this->_quickForm->addElement('password', 'confirm_password', 'Confirm Password');
            $this->_quickForm->addRule('password', 'Password must be at least 6 characters long', 'minlength', 6, 'client');
            $this->_quickForm->addRule(array('password', 'confirm_password'), "The passwords do not match", 'compare', null, 'client');
            $this->_quickForm->addElement('submit', 'submitted', 'Save Changes');
            $this->_quickForm->addRule('name', 'Name Required', 'required', null, 'client');
            $this->_quickForm->addRule('email', 'E-Mail Required', 'required', null, 'client');
            echo $this->_quickForm->toHtml();
        } catch (Chiara_PEAR_Server_Exception $e) {
            echo "<strong>Error</strong> " . $e->getMessage();
        }
    }

    public function doAddPackage($func)
    {
        $defaults = array();
        $defaults['f'] = $_REQUEST['f'];
        $defaults['submitted'] = 1;
        $defaults['channel'] = $this->_channel;
        $this->_quickForm->setDefaults($defaults);
        $this->_quickForm->addElement('header', '', 'Add a New Package');
        $this->_quickForm->addElement('static', 'channel', 'Channel');
        $this->_quickForm->addElement('text', 'name', 'Name');
        $c = $this->_backend->listCategories();
        $categories = array('');
        foreach ($c as $category) {
            $categories[$category['id']] = $category['name'];
        }
        $this->_quickForm->addElement('select', 'category', 'Category', $categories);
        $this->_quickForm->addElement('text', 'license', 'License');
        $this->_quickForm->addElement('text', 'license_uri', 'License URL');
        $this->_quickForm->addElement('hidden', 'f', 'License');
        $this->_quickForm->addElement('hidden', 'submitted', '1');
        $this->_quickForm->addElement('text', 'summary', 'Summary', array('size' => 40));
        $this->_quickForm->addElement('textarea', 'description', 'Description', array('cols' => 40, 'rows' => 5));
        $p = $this->_backend->listPackages(false, false, false);
        $packages = array('');
        foreach ($p as $package) {
            $packages[$package['package']] = $package['package'];
        }
        $this->_quickForm->addElement('select', 'parent', 'Parent Package', $packages);
        $this->_quickForm->addElement('text', 'cvs_uri', 'Web CVS URL');
        $this->_quickForm->addElement('text', 'bugs_uri', 'Bug Tracker URL');
        $this->_quickForm->addElement('text', 'docs_uri', 'Documentation URL');
        $this->_quickForm->addElement('submit', 'save', 'Save Changes');
        $this->_quickForm->addRule('name', 'Required', 'required');
        $this->_quickForm->addRule('license', 'Required', 'required');
        $this->_quickForm->addRule('summary', 'Required', 'required');
        $this->_quickForm->addRule('description', 'Required', 'required');
        if (isset($_REQUEST['submitted'])) {
            if ($this->_quickForm->validate()) {
                try {
                    $spack = new Chiara_PEAR_Server_Package;
                    $spack->name = trim($this->_quickForm->getSubmitValue('name'));
                    $spack->channel = $this->_channel;
                    $spack->license = trim($this->_quickForm->getSubmitValue('license'));
                    $spack->license_uri = trim($this->_quickForm->getSubmitValue('license_uri'));
                    $spack->summary = trim($this->_quickForm->getSubmitValue('summary'));
                    $spack->description = trim($this->_quickForm->getSubmitValue('description'));
                    $spack->parent = trim($this->_quickForm->getSubmitValue('parent'));
                    $spack->category_id = trim($this->_quickForm->getSubmitValue('category'));
                    $spack->bugs_uri = trim($this->_quickForm->getSubmitValue('bugs_uri'));
                    $spack->docs_uri = trim($this->_quickForm->getSubmitValue('docs_uri'));
                    $spack->cvs_uri = trim($this->_quickForm->getSubmitValue('cvs_uri'));
                    if ($this->_backend->addPackage($spack)) {
                        $this->_quickForm->removeElement('save');
                        $this->_quickForm->freeze();
                        echo '<strong>Package Successfully Added</strong>';
                    } else {
                        echo '<strong>Warning: adding package failed';
                    }
                } catch (Chiara_PEAR_Server_Exception $e) {
                    echo "<strong>Error</strong> " . $e->getMessage();
                }
            }
        }
        echo $this->_quickForm->toHtml();
    }

    public function doAddMaintainer($func)
    {
        $defaults = array();
        $defaults['f'] = $_REQUEST['f'];
        $defaults['submitted'] = 1;
        $defaults['channel'] = $this->_channel;
        $this->_quickForm->setDefaults($defaults);
        $this->_quickForm->addElement('header', '', 'Add a New Maintainer');
        $this->_quickForm->addElement('text', 'handle', 'Handle');
        $this->_quickForm->addElement('text', 'name', 'Name');
        $this->_quickForm->addElement('text', 'email', 'Email');
        $this->_quickForm->addElement('password', 'password', 'Password');
        $this->_quickForm->addElement('hidden', 'f', 'License');
        $this->_quickForm->addElement('hidden', 'submitted', '1');
        $this->_quickForm->addElement('submit', 'save', 'Save Changes');
        $this->_quickForm->addRule('name', 'Required', 'required');
        $this->_quickForm->addRule('handle', 'Required', 'required');
        $this->_quickForm->addRule('email', 'Required', 'required');
        $this->_quickForm->addRule('password', 'Required', 'required');
        $this->_quickForm->addRule('handle', 'Maximum length 20 characters', 'maxlength', 20);
        $this->_quickForm->addRule('password', 'Minimum length 6 characters', 'minlength', 6);
        $this->_quickForm->addRule('name', 'Maximum length 255 characters', 'maxlength', 255);
        $this->_quickForm->addRule('email', 'Maximum length 255 characters', 'maxlength', 255);
        $this->_quickForm->addRule('email', 'Invalid Email Address', 'email');
        if (isset($_REQUEST['submitted'])) {
            if ($this->_quickForm->validate()) {
                try {
                    $spack = new Chiara_PEAR_Server_Maintainer;
                    $spack->handle = trim($this->_quickForm->getSubmitValue('handle'));
                    $spack->name = trim($this->_quickForm->getSubmitValue('name'));
                    $spack->email = trim($this->_quickForm->getSubmitValue('email'));
                    $spack->password = trim($this->_quickForm->getSubmitValue('password'));
                    if ($this->_backend->addMaintainer($spack)) {
                        $this->_quickForm->removeElement('save');
                        $this->_quickForm->freeze();
                        echo '<strong>Maintainer Successfully Added</strong>';
                    } else {
                        echo '<strong>Warning: adding maintainer failed';
                    }
                } catch (Chiara_PEAR_Server_Exception $e) {
                    echo "<strong>Error</strong> " . $e->getMessage();
                }
            }
        }
        echo $this->_quickForm->toHtml();
    }

    public function getProtocols()
    {
        return array();
    }

    public function getOutput()
    {
    }
}
?>