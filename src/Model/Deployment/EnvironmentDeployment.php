<?php

namespace Platformsh\Client\Model\Deployment;

use Platformsh\Client\Model\Resource;

/**
 * A Platform.sh environment deployment.
 *
 * @property-read Route[]   $routes
 * @property-read Service[] $services
 * @property-read WebApp[]  $webapps
 * @property-read Worker[]  $workers
 *
 * @property-read array $container_profiles
 *
 * @method Route getRoute(string $originalUrl)
 * @method Service getService(string $name)
 * @method WebApp getWebApp(string $name)
 * @method Worker getWorker(string $name)
 */
class EnvironmentDeployment extends Resource
{
    private static $types = [
        'services' => Service::class,
        'routes' => Route::class,
        'webapps' => WebApp::class,
        'workers' => Worker::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        if (isset(self::$types[$name])) {
            $className = self::$types[$name];
            return array_map(function (array $data) use ($className) {
                return $className::fromData($data);
            }, $this->data[$name]);
        }

        return parent::__get($name);
    }

    /**
     * Dynamically get an item from a list of sub-properties by name.
     *
     * This represents the getWebApp(), getService, getRoute() and
     * getWorker() methods.
     */
    public function __call($name, $arguments)
    {
        if (strpos($name, 'get') === 0) {
            $type = substr($name, 3);
            $property = strtolower($type) . 's';
            if (!isset(self::$types[$property])) {
                throw new \BadMethodCallException(sprintf('Method not found: %s()', $name));
            }
            if (count($arguments) !== 1) {
                throw new \InvalidArgumentException(sprintf('%s() expects exactly one argument', $name));
            }
            $key = $arguments[0];
            if (!isset($this->data[$property][$key])) {
                throw new \InvalidArgumentException(sprintf('%s not found: %s', $type, $key));
            }
            $className = self::$types[$property];

            return $className::fromData($this->data[$property][$key]);
        }
        throw new \BadMethodCallException(sprintf('Method not found: %s()', $name));
    }

    /**
     * Returns the runtime operations defined on all the apps in this deployment.
     *
     * To fetch a specific runtime operation use the WebApp or Worker object.
     *
     * @see AppBase::getRuntimeOperations()
     * @see AppBase::getRuntimeOperation()
     *
     * @return array<string, array<string, RuntimeOperation>>
     *     A list of runtime operations keyed by operation name and app name.
     */
    public function getRuntimeOperations()
    {
        $operations = [];
        foreach (['webapps', 'workers'] as $appType) {
            foreach ($this->$appType as $appName => $app) {
                /** @var AppBase $app */
                $operations[$appName] = $app->getRuntimeOperations();
            }
        }
        return $operations;
    }

    /**
     * Executes a runtime operation on this deployment.
     *
     * @see RuntimeOperation
     * @see AppBase::getRuntimeOperations()
     * @see AppBase::getRuntimeOperation()
     *
     * @param string $name
     *   The operation name.
     * @param string $service
     *   The name of the service or application to run the operation on.
     *
     * @return \Platformsh\Client\Model\Result
     */
    public function execRuntimeOperation($name, $service)
    {
        return $this->runOperation('operations', 'post', [
            'operation' => $name,
            'service' => $service,
        ]);
    }
}
