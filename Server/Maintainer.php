<?php
class Chiara_PEAR_Server_Maintainer implements Iterator
{
    protected $properties = array(
        'name' => '',
        'handle' => '',
        'email' => '',
        'uri' => '',
        'password' => '',
        'wishlist' => '',
        'description' => '',
        'admin' => '',
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

    public function __construct($arr = null)
    {
        if (is_array($arr)) {
            foreach ($arr as $name => $value) {
                $this->$name = $value;
            }
        }
    }

    public function __get($var)
    {
        if (isset($this->properties[$var])) {
            return $this->properties[$var];
        }
        return null;
    }

    public function __set($var, $value)
    {
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

class Chiara_PEAR_Server_MaintainerPackage extends Chiara_PEAR_Server_Maintainer
{
    protected $properties = array(
        'name' => '',
        'handle' => '',
        'email' => '',
        'role' => '',
        'active' => '',
        'package' => '',
        'channel' => '',
    );

    public function __set($var, $value)
    {
        if (isset($this->properties[$var])) {
            if ($value === null) {
                $value = '';
            }
            if ($var == 'active') {
                $value += 0;
            }
            $this->properties[$var] = $value;
        }
    }
}
?>