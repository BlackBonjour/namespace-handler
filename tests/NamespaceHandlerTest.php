<?php

declare(strict_types=1);

namespace BlackBonjourTest\NamespaceHandler;

use BlackBonjour\NamespaceHandler\NamespaceHandler;
use Composer\Autoload\ClassLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class NamespaceHandlerTest extends TestCase
{
    /**
     * Verifies that the constructor accepts a valid ClassLoader instance and initializes successfully.
     *
     * @throws Throwable
     */
    public function testConstructorWithClassLoader(): void
    {
        $handler = new NamespaceHandler($this->createMock(ClassLoader::class));

        self::assertInstanceOf(NamespaceHandler::class, $handler);
    }

    /**
     * Verifies that an InvalidArgumentException is thrown for an invalid autoloader file path.
     *
     * @throws Throwable
     */
    public function testConstructorWithInvalidAutoloaderFilePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Composer autoloader file invalid/path/to/autoload.php does not exist!');

        new NamespaceHandler('invalid/path/to/autoload.php');
    }

    /**
     * Verifies that the constructor accepts a valid autoloader file path and initializes successfully.
     *
     * @throws Throwable
     */
    public function testConstructorWithValidAutoloaderFilePath(): void
    {
        $validFilePath = __DIR__ . '/valid_autoload.php';
        file_put_contents($validFilePath, '<?php return new class extends Composer\Autoload\ClassLoader {};');

        self::assertInstanceOf(NamespaceHandler::class, new NamespaceHandler($validFilePath));

        unlink($validFilePath); // Clean up
    }

    /**
     * Verifies that `getDirectory` returns the correct directory for a valid namespace.
     *
     * @throws Throwable
     */
    public function testGetDirectory(): void
    {
        $expected = realpath(__DIR__ . '/../src');

        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader
            ->expects($this->exactly(3))
            ->method('getPrefixesPsr4')
            ->willReturn(['BlackBonjour\\NamespaceHandler' => [$expected]]);

        $handler = new NamespaceHandler($classLoader);

        self::assertEquals($expected, $handler->getDirectory('BlackBonjour\\NamespaceHandler'));
        self::assertEquals($expected, $handler->getDirectory('\\BlackBonjour\\NamespaceHandler'));
        self::assertEquals($expected, $handler->getDirectory('BlackBonjour\\NamespaceHandler\\'));
    }

    /**
     * Verifies that `getDirectory` throws a RuntimeException when realpath fails.
     *
     * @throws Throwable
     */
    public function testGetDirectoryThrowsRuntimeExceptionWhenRealPathFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Failed to fetch real path for directory invalid/directory!');

        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader
            ->expects($this->once())
            ->method('getPrefixesPsr4')
            ->willReturn(['TestNamespace\\' => ['invalid/directory']]);

        $handler = new NamespaceHandler($classLoader);
        $handler->getDirectory('TestNamespace\\SubNamespace');
    }

    /**
     * Verifies that `getDirectory` returns NULL for an unmapped namespace.
     *
     * @throws Throwable
     */
    public function testGetDirectoryWithUnmappedNamespace(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader
            ->expects($this->once())
            ->method('getPrefixesPsr4')
            ->willReturn([]);

        $handler = new NamespaceHandler($classLoader);

        self::assertNull($handler->getDirectory('UnknownNamespace\\'));
    }
}
