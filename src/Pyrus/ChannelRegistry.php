<?php
/**
 * PEAR2_Pyrus_ChannelRegistry
 *
 * PHP version 5
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version   SVN: $Id$
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */

/**
 * Base class for Pyrus.
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */
class PEAR2_Pyrus_ChannelRegistry implements ArrayAccess, IteratorAggregate, PEAR2_Pyrus_IChannelRegistry
{
    /**
     * Class to instantiate for singleton.
     *
     * This is useful for unit-testing and for extending the registry
     * @var string
     */
    static public $className = 'PEAR2_Pyrus_ChannelRegistry';
    /**
     * The parent registry
     *
     * This is used to implement cascading registries
     * @var PEAR2_Pyrus_ChannelRegistry
     */
    protected $parent;
    protected $path;
    protected $readonly;
    private $_registries = array();

    public function __construct($path, $registries = array('Sqlite', 'Xml'), $readonly = false)
    {
        $this->path = $path;
        $this->readonly = $readonly;
        $exceptions = array();
        foreach ($registries as $registry) {
            try {
                $registry = ucfirst($registry);
                $registry = "PEAR2_Pyrus_ChannelRegistry_$registry";
                if (!class_exists($registry, true)) {
                    $exceptions[] = new PEAR2_Pyrus_ChannelRegistry_Exception(
                        'Unknown channel registry type: ' . $registry);
                    continue;
                }
                $this->_registries[] = new $registry($path, $readonly);
            } catch (PEAR2_Pyrus_ChannelRegistry_Exception $e) {
                $exceptions[] = $e;
            }
        }
        if (!count($this->_registries)) {
            throw new PEAR2_Pyrus_Registry_Exception(
                'Unable to initialize registry for path "' . $path . '"',
                $exceptions);
        }
    }

    public function setParent(PEAR2_Pyrus_ChannelRegistry $parent = null)
    {
        $this->parent = $parent;
    }

    public function add(PEAR2_Pyrus_IChannel $channel)
    {
        if ($this->readonly) {
            throw new PEAR2_Pyrus_ChannelRegistry_Exception('Cannot add channel, registry is read-only');
        }
        foreach ($this->_registries as $reg) {
            $reg->add($channel);
        }
    }

    public function update(PEAR2_Pyrus_IChannel $channel)
    {
        if ($this->readonly) {
            throw new PEAR2_Pyrus_ChannelRegistry_Exception('Cannot update channel, registry is read-only');
        }
        foreach ($this->_registries as $reg) {
            $reg->update($channel);
        }
    }

    public function delete(PEAR2_Pyrus_IChannel $channel)
    {
        if ($this->readonly) {
            throw new PEAR2_Pyrus_ChannelRegistry_Exception('Cannot delete channel, registry is read-only');
        }
        foreach ($this->_registries as $reg) {
            $reg->delete($channel);
        }
    }

    public function get($channel, $strict = true)
    {
        try {
            return $this->_registries[0]->get($channel, $strict);
        } catch (Exception $e) {
            // don't fail on the default channels, these should always exist
            switch ($channel) {
                case 'pear.php.net' :
                    return $this->_registries[0]->getPearChannel();
                case 'pear2.php.net' :
                    return $this->_registries[0]->getPear2Channel();
                case 'pecl.php.net' :
                    return $this->_registries[0]->getPeclChannel();
                case '__uri' :
                    return $this->_registries[0]->getUriChannel();
            }
            throw $e;
        }
    }

    public function exists($channel, $strict = true)
    {
        if (!$this->_registries[0]->exists($channel, $strict)) {
            switch ($channel) {
                case 'pear.php.net' :
                case 'pear2.php.net' :
                case 'pecl.php.net' :
                case '__uri' :
                    return true;
            }
            if (!$strict) {
                switch ($channel) {
                    case 'pear' :
                    case 'pear2' :
                    case 'pecl' :
                        return true;
                }
            }
            return false;
        }
        return true;
    }

    public function parseName($name)
    {
        foreach ($this->_registries as $reg) {
            try {
                return $reg->parseName($name);
            } catch (Exception $e) {
                continue;
            }
        }
        if ($this->parent) {
            return $this->parent->parseName($name);
        }
        // recycle last exception
        throw new PEAR2_Pyrus_ChannelRegistry_Exception('Unable to process package name', $e);
    }

    public function parsedNameToString($name)
    {
        foreach ($this->_registries as $reg) {
            try {
                return $reg->parsedNameToString($name);
            } catch (Exception $e) {
                continue;
            }
        }
        if ($this->parent) {
            return $this->parent->parsedNameToString($name);
        }
        // recycle last exception
        throw new PEAR2_Pyrus_ChannelRegistry_Exception('Unable to convert to package name string', $e);
    }

    public function listChannels()
    {
        return $this->_registries[0]->listChannels();
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        if ($this->readonly) {
            throw new PEAR2_Pyrus_ChannelRegistry_Exception('Cannot add channel, registry is read-only');
        }
        foreach ($this->_registries as $reg) {
            $reg->add($offset, $value);
        }
    }

    public function offsetExists($offset)
    {
        return $this->exists($offset);
    }

    public function offsetUnset($offset)
    {
        if ($this->readonly) {
            throw new PEAR2_Pyrus_ChannelRegistry_Exception('Cannot delete channel, registry is read-only');
        }
        foreach ($this->_registries as $reg) {
            $reg->delete($offset);
        }
    }

    public function __call($method, $args)
    {
        return call_user_func_array(array($this->_registries[0], $method), $args);
    }

    public function getIterator()
    {
        return $this->_registries[0];
    }
}
