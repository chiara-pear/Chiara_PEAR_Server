<?php
class Chiara_PEAR_Server_Package implements Iterator
{
    protected $properties = array(
        'name' => '',
        'channel' => '',
        'category_id' => '',
        'description' => '',
        'summary' => '',
        'license' => '',
        'license_uri' => '',
        'parent' => '',
        'cvs_uri' => '',
        'bugs_uri' => '',
        'docs_uri' => '',
        'deprecated_channel' => '',
        'deprecated_package' => '',
    );

    function rewind() {
        reset($this->properties);
    }

    function valid() {
        return current($this->properties) !== false;
    }

    function key() {
        return key($this->properties);
    }

    function current() {
        return current($this->properties);
    }

    function next() {
        next($this->properties);
    }

    public function getMaintainers()
    {
        return array_values($this->properties['maintainers']);
    }

    public function addMaintainer($handle, $role, $email)
    {
        $this->$this->properties['maintainers'][$handle] = array($handle, $role, $email);
    }

    public function removeMaintainer($handle)
    {
        unset($this->properties['maintainers']);
    }
    
    public function __get($var)
    {
        if ($var == 'maintainers') {
            return array_values($this->properties['maintainers']);
        }
        if ($var == 'maintainer') {
            return $this->properties['maintainers'];
        }
        if (isset($this->properties[$var])) {
            return $this->properties[$var];
        }
        return null;
    }

    public function __set($var, $value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($var == 'maintainer') {
            $this->properties['maintainers'][$value[0]] = $value;
        }
        if ($var == 'maintainers') {
            $this->properties['maintainers'] = $value;
        }
        if (isset($this->properties[$var])) {
            if ($value === null) {
                $value = '';
            }
            $this->properties[$var] = $value;
        }
    }

    public function toArray()
    {
        return $this->properties;
    }
}
?>