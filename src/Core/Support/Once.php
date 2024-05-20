<?php

namespace Core\Support;

use Closure;
use Core\Facades\App;
use ReflectionFunction;
use WeakMap;

class Once
{
    /**
     * @var WeakMap<object, array<string, mixed>> $values
     */
    protected $values;

    /**
     * Create a new once instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->values = new WeakMap();
    }

    /**
     * Get the value of the callable.
     *
     * @param array<int, array<string, mixed>> $trace
     * @param callable $callable
     * @return mixed
     */
    public function &value(array $trace, callable $callable): mixed
    {
        if (str_contains($trace[0]['file'] ?? '', 'eval()\'d code')) {
            return null;
        }

        $uses = array_map(
            fn (mixed $argument): mixed => is_object($argument) ? spl_object_hash($argument) : $argument,
            $callable instanceof Closure ? (new ReflectionFunction($callable))->getClosureUsedVariables() : [],
        );

        $hash = crc32(sprintf(
            '%s@%s%s:%s',
            $trace[0]['file'],
            isset($trace[1]['class']) ? ($trace[1]['class'] . '@') : '',
            $trace[1]['function'],
            $trace[0]['line']
        ));

        $fn = sprintf('%d(%d)', $hash, crc32(serialize($uses)));

        $objectName = $trace[1]['object'] ?? $this;
        $object = App::get()->singleton($objectName::class);

        if (isset($this->values[$object][$fn])) {
            return $this->values[$object][$fn];
        }

        if (!isset($this->values[$object])) {
            $this->values[$object] = [];
        }

        $this->values[$object][$fn] = call_user_func($callable);

        return $this->values[$object][$fn];
    }
}
