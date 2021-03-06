<?php
namespace Nod;

use josegonzalez\Dotenv;
use MarkWilson\ArrayKeyFilter;
use MarkWilson\ArrayFiltering\ArrayFiltering;
use InvalidArgumentException;
use ReflectionClass;

class Environment
{
    //TODO                               : Imrove documentation
    public $dir;
    public $excludeKey = 'EXCLUDE';
    public $environments = array(
        'nod'         => '.env.nod',
        'default'     => '.env',
        'development' => '.env.development',
        'test'        => '.env.test',
        'production'  => '.env.production',
        'local'       => '.env.local'
    );
    public $settings = array(
        'load'      => true,
        'validate'  => true,
        'quiet'     => false,
        'overwrite' => true,
        'define'    => array(
            'putenv'   => true,
            'env'      => false,
            'server'   => false,
            'constant' => false
        )
    );
    public $exclude = array();
    protected $validate = array(
        'nod'         => array(),
        'default'     => array(),
        'development' => array(),
        'test'        => array(),
        'production'  => array(),
        'local'       => array()
    );
    protected $loaded = array();

    /**
     * Loads and parses given environment into context
     * @param string $name Name of environment
     */
    public function add($name = 'default')
    {
        $env = $this->environments[$name];
        $path = $this->dir . DIRECTORY_SEPARATOR . $env;
        if (file_exists($path)) {
            return $this->loaded[$name] = (new Dotenv\Loader($path))
                ->parse();
        }
        return false;
    }

    /**
     * Removes environment from context
     * @param string $name Name of the environment
     * @return array Current loaded environments
     */
    public function remove($name = null)
    {
        if ($name === null) {
            return false;
        }
        if (!empty($this->environments[$name])) {
            unset($this->environments[$name]);
        }
        if (!empty($this->validate[$name])) {
            unset($this->validate[$name]);
        }
        if (!empty($this->loaded[$name])) {
            unset($this->loaded[$name]);
        }
        return $this->loaded;
    }

    /**
     * Returns a loaded environment
     * @param string $name Name of environment
     * @return bool,object Environment if exists otherwise false
     */
    public function getLoaded($name = 'default')
    {
        return empty($this->loaded[$name]) ?
            false : $this->loaded[$name];
    }

    /**
     * Returns environment values
     * @param array $loaded Optional loaded environments
     */
    public function getValues(Array $loaded = array())
    {
        if (empty($loaded)) {
            $loaded = $this->loaded;
        }
        $environment = array();
        foreach (array_keys($loaded) as $name) {
            $environment = array_merge(
                $environment,
                $this
                    ->getLoaded($name)
                    ->toArray()
            );
        }
        return $environment;
    }

    /**
     * @param string $key
     */
    public function getVal($key = null)
    {
        if ($key === null) {
            return $this->raise(
                InvalidArgumentException,
                'Name argument is missing'
            );
        }
        if ($this->settings['define']['putenv']) {
            return getenv($key);
        }
        if ($this->settings['define']['env']) {
            return filter_input(INPUT_ENV, $key);
        }
        if ($this->settings['define']['server']) {
            return filter_input(INPUT_SERVER, $key);
        }
        if ($this->settings['define']['constant']) {
            return constant($key);
        }
        $environment = $this->getValues();
        return empty($environment[$key]) ?
            false                        : $environment[$key];
    }

    /**
     * Excludes specified variables
     * @param array $environment Optional environment variables
     * @return array $environment Environment variables
     */
    public function exclude(Array $environment = array())
    {
        $environment = empty($environment) ?
            $this->getValues()           : $environment;

        $excludes = $this->getVal($this->excludeKey);
        $excludes = explode(',', $excludes);
        $excludes = !$excludes ? array() : $excludes;
        array_push($excludes, $this->excludeKey);

        foreach ($excludes as $exclude) {
            $filtering = new ArrayFiltering($environment);
            $exclude = new ArrayKeyFilter\KeyPatternFilter("/^((?!{$exclude}).)*$/");
            $environment = $filtering->filterBy($exclude);
        }

        return $environment;
    }

    public function toJson(Array $loaded = array(), $attribute = null)
    {
        $environment = $this->getValues($loaded);
        $environment = $this->exclude($environment);
        return json_encode($environment, $attribute);
    }

    /**
     * @param string $message
     */
    protected function raise($exception, $message)
    {
        $quiet = $this->settings['quiet'];
        $quiet = (bool) $this->settings['quiet'];
        if ($quiet === false) {
            throw new $exception($message);
        }
        return false;
    }

    /**
    * Detects and loads available environments
     * @param array $environments Environments to load
     * @return array Loaded environments
     */
    protected function load(Array $environments = array())
    {
        if (empty($environments)) {
            $environments = $this->environments;
        }
        // TODO                          : Add optional hostname check to detection
        $loaded = array();
        foreach ($this->environments as $name => $env) {
            if ($this->add($name, $env)) {
                $added = $this->add($name, $env);
                array_push($loaded, $added);
            }
        }
        return $loaded;
    }

    /**
     * Defines given or loaded environments
     * @param array $loaded Loaded environments
     * @return array $defined Defined environments
     */
    public function define(Array $loaded = array())
    {
        if (empty($loaded)) {
            $loaded = $this->loaded;
        }
        $overwrite = (bool) $this->settings['overwrite'];
        $defined = array();
        foreach ($loaded as $environment) {
            if (!$environment instanceof Dotenv\Loader) {
                continue;
            }
            if ($this->settings['define']['putenv']) {
                foreach ($environment->toArray() as $key => $val) {
                    if ($overwrite === false) {
                        if (getenv($key) !== false) {
                            continue;
                        }
                    }
                    putenv("{$key}={$val}");
                }
            }
            if ($this->settings['define']['env']) {
                $environment->toEnv($overwrite);
            }
            if ($this->settings['define']['server']) {
                $environment->toServer($overwrite);
            }
            if ($this->settings['define']['constant']) {
                $environment->define();
            }
            array_push($defined, $environment);
        }
        return $defined;
    }

    /**
     * Check whether expected variables in environment or not
     * @param array $environments
     * @return boolean
     */
    public function validate(Array $environments = array())
    {
        if (empty($environments)) {
            $environments = $this->environments;
        }

        $valid = true;
        foreach (array_keys($environments) as $name) {
            if (empty($this->validate[$name])) {
                continue;
            }
            $validate = $this->validate[$name];
            $environment = $this->getLoaded($name);
            if (!$environment) {
                continue;
            }

            if (!$environment->expect($validate)) {
                return false;
            }
        }

        return $valid;
    }

    public function __construct(
        $environments = array(),
        $validate = array(),
        $settings = array(),
        $dir = null
    ) {
        $this->dir = $dir !== null ? $dir : dirname(debug_backtrace()[0]['file']);
        $this->environments = array_merge($this->environments, $environments);
        $this->validate     = array_merge_recursive($this->validate, $validate);
        $this->settings     = array_merge_recursive($this->settings, $settings);

        if (!$this->settings['load']) {
            return $this;
        }
        $loaded = $this->load();
        $this->define($loaded);
        if (!$this->settings['validate']) {
            return $this;
        }
        if (!$this->validate($this->environments)) {
            return false;
        }
        return $this;
    }
}
