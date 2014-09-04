<?php

namespace mindplay\session;

use Closure;
use ReflectionFunction;

/**
 * This class implements a type-safe session object container.
 *
 * Your session objects must have an empty constructor, and will be constructed for you.
 *
 * Only one instance of each type of session object can be kept in the same container - if
 * you need multiple instances of something, put those instances in an array in the session
 * object itself.
 *
 * Session object types must be able to serialize() and unserialize().
 *
 * Keep as little information as possible in session objects - for example, keep an active
 * user ID; do not keep the entire User object.
 */
class SessionContainer
{
    /**
     * @var string root session-variable name
     */
    protected $root;

    /**
     * @var (object|null)[] map where type-name => object (or NULL, if the object has been removed)
     */
    protected $cache = array();

    /**
     * @param string|null root session-variable name (or NULL to use class-name as a default)
     */
    public function __construct($root = null)
    {
        $this->root = $root ? : get_class($this);
    }

    /**
     * Access one or more objects in this container.
     *
     * @param Closure $func function(MyType $object...) { ... }
     *
     * @return void
     */
    public function update(Closure $func)
    {
        $reflection = new ReflectionFunction($func);

        $params = $reflection->getParameters();

        $args = array();

        foreach ($params as $param) {
            $type = $param->getClass()->getName();

            $args[] = $this->fetch($type) ?: $this->create($type);
        }

        call_user_func_array($func, $args);
    }

    /**
     * Remove and object from this session container.
     *
     * Note that the change is not effective until you call commit()
     *
     * @param object $object
     *
     * @return void
     */
    public function remove($object)
    {
        $this->cache[get_class($object)] = null;
    }

    /**
     * Commit any changes made to objects in this session container.
     */
    public function commit()
    {
        foreach ($this->cache as $type => $object) {
            if ($object === null) {
                unset($_SESSION[$this->root][$type]);
            } else {
                $_SESSION[$this->root][$type] = $this->serialize($object);
            }
        }
    }

    /**
     * Destroy all objects in this session store.
     *
     * Note that this change is effective immediately - whether you call commit() or not
     *
     * @return void
     */
    public function clear()
    {
        $this->cache = array();

        unset($_SESSION[$this->root]);
    }

    /**
     * @param string $type fully-qualified class name
     *
     * @return object|null
     */
    protected function fetch($type)
    {
        if (!isset($this->cache[$type])) {
            if (!isset($_SESSION[$this->root][$type])) {
                return null;
            }

            $this->cache[$type] = $this->unserialize($_SESSION[$this->root][$type]);
        }

        return $this->cache[$type];
    }

    /**
     * @param string $type fully-qualified class name
     *
     * @return object session variable
     */
    protected function create($type)
    {
        $object = new $type();

        $this->cache[$type] = $object;

        return $object;
    }

    /**
     * @param $object
     *
     * @return string serialized object
     */
    protected function serialize($object)
    {
        return serialize($object);
    }

    /**
     * @param string $str serialized object
     *
     * @return object
     */
    protected function unserialize($str)
    {
        return unserialize($str);
    }
}