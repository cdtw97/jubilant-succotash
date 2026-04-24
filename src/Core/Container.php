<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use Closure;
use MyFrancis\Core\Exceptions\ContainerException;
use ReflectionClass;
use ReflectionNamedType;

final class Container
{
    /** @var array<string, mixed> */
    private array $entries = [];

    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
    }

    /**
     * @param Closure(self): mixed $factory
     */
    public function factory(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries)
            || array_key_exists($id, $this->factories)
            || class_exists($id);
    }

    /**
     * @template T
     *
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->entries)) {
            return $this->entries[$id];
        }

        if (array_key_exists($id, $this->factories)) {
            $factory = $this->factories[$id];
            $this->entries[$id] = $factory($this);

            return $this->entries[$id];
        }

        if (! class_exists($id)) {
            throw new ContainerException(sprintf('Service [%s] is not defined.', $id));
        }

        $this->entries[$id] = $this->make($id);

        return $this->entries[$id];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     * @return T
     */
    public function make(string $id): object
    {
        if (! class_exists($id)) {
            throw new ContainerException(sprintf('Class [%s] does not exist.', $id));
        }

        $reflectionClass = new ReflectionClass($id);

        if (! $reflectionClass->isInstantiable()) {
            throw new ContainerException(sprintf('Class [%s] is not instantiable.', $id));
        }

        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return new $id();
        }

        /** @var list<mixed> $arguments */
        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ContainerException(sprintf(
                'Unable to resolve constructor parameter [%s] for [%s].',
                $parameter->getName(),
                $id,
            ));
        }

        return $reflectionClass->newInstanceArgs($arguments);
    }
}
