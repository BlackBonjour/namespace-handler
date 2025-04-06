<?php

declare(strict_types=1);

namespace BlackBonjour\NamespaceHandler;

use Composer\Autoload\ClassLoader;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

readonly class NamespaceHandler
{
    private ClassLoader $classLoader;

    /**
     * Initializes the object with a ClassLoader instance or a path to a Composer autoloader file.
     *
     * @param ClassLoader|string $classLoader Either an instance of ClassLoader or a string path to a Composer autoload file.
     *
     * @throws InvalidArgumentException If the provided file path does not exist.
     */
    public function __construct(ClassLoader|string $classLoader)
    {
        if ($classLoader instanceof ClassLoader) {
            $this->classLoader = $classLoader;
        } elseif (file_exists($classLoader) === false) {
            throw new InvalidArgumentException(sprintf('Composer autoloader file %s does not exist!', $classLoader));
        } else {
            $this->classLoader = require $classLoader;
        }
    }

    /**
     * Resolves the directory path corresponding to the given namespace.
     *
     * @param string $namespace The namespace to resolve. This should be provided as a fully qualified, absolute namespace.
     *
     * @return string|null The resolved directory path if successful, or NULL if the namespace cannot be resolved.
     * @throws RuntimeException If the real path of the directory cannot be determined.
     */
    public function getDirectory(string $namespace): ?string
    {
        $autoloadMap = $this->classLoader->getPrefixesPsr4();
        $namespace = trim($namespace, '\\');

        foreach ($autoloadMap as $prefix => $directories) {
            if (str_starts_with($namespace, $prefix)) {
                $directory = realpath(
                    sprintf('%s/%s', $directories[0], str_replace('\\', '/', substr($namespace, strlen($prefix)))),
                );

                if ($directory === false) {
                    throw new RuntimeException(sprintf('Failed to fetch real path for directory %s!', $directories[0]));
                }

                return $directory;
            }
        }

        return null;
    }

    /**
     * Retrieves all fully qualified class names within the specified namespace.
     *
     * @param string $namespace The namespace to search for classes. This should be provided as a fully qualified, absolute namespace.
     *
     * @return array<class-string> An array of fully qualified class names found within the provided namespace.
     * @throws InvalidArgumentException If the namespace is not mapped properly in the Composer autoloader.
     * @throws RuntimeException If the directory corresponding to the namespace does not exist.
     */
    public function getClassNamesByNamespace(string $namespace): array
    {
        $namespace = trim($namespace, '\\');
        $directory = $this->getDirectory($namespace);

        if (empty($directory)) {
            throw new InvalidArgumentException(
                sprintf('Namespace %s is not properly mapped in the Composer autoloader!', $namespace),
            );
        }

        if (is_dir($directory) === false) {
            throw new RuntimeException(sprintf('Directory %s does not exist!', $directory));
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $classNames = [];

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace([$directory . '/', '/', '.php'], ['', '\\', ''], $file->getRealPath());
                $className = $namespace . '\\' . $relativePath;

                if (class_exists($className)) {
                    $classNames[] = $className;
                }
            }
        }

        return $classNames;
    }
}
