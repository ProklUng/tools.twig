<?php

namespace Maximaster\Tools\Twig\Test;

use Exception;
use Maximaster\Tools\Twig\BitrixExtension;
use Maximaster\Tools\Twig\BitrixLoader;
use Maximaster\Tools\Twig\CustomFunctionsExtension;
use Maximaster\Tools\Twig\ModulesViewsLocator;
use Maximaster\Tools\Twig\PhpGlobalsExtension;
use Maximaster\Tools\Twig\TemplateEngine;
use Maximaster\Tools\Twig\TwigOptionsStorage;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/**
 * class TemplateEngineTest
 *
 * @since 12.08.2021
 */
class TemplateEngineTest extends TestCase
{
    /**
     * @var TemplateEngine $testObject Тестовый объект.
     */
    private $testObject;

    /**
     * @inheritDoc
     * @throws Exception
     * @throws LoaderError Ошибки Твига.
     */
    protected function setUp(): void
    {
        Mockery::resetContainer();
        parent::setUp();

        $this->mockEvent();
        $this->testObject = new TemplateEngine(
            $this->getMockTwigOptions(),
            new BitrixLoader($_SERVER['DOCUMENT_ROOT']),
            $this->getMockModulesViewsLocator()
        );
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * Пустые параметры.
     *
     * @return void
     * @throws ReflectionException
     */
    public function testProcessEmptyParams() : void
    {
        /** @var FilesystemLoader $loader */
        $loader = $this->testObject->getEngine()->getLoader();

        $namespaces = $loader->getNamespaces();

        $this->assertCount(1, $namespaces, 'Зарегистрировалось что-то странное.');
        $this->assertSame(['__main__'], $namespaces);

        $twig = $this->testObject->getEngine();

        $ref = new ReflectionProperty($twig, 'runtimeLoaders');
        $ref->setAccessible(true);

        $this->assertEmpty($ref->getValue($twig), 'Runtimes не пусты.');
    }

    /**
     * Namespaces. Пустые параметры.
     *
     * @return void
     */
    public function testProcessNamespacesEmptyParams() : void
    {
        /** @var FilesystemLoader $loader */
        $loader = $this->testObject->getEngine()->getLoader();

        $namespaces = $loader->getNamespaces();

        $this->assertCount(1, $namespaces, 'Зарегистрировалось что-то странное.');
        $this->assertSame(['__main__'], $namespaces);
    }

    /**
     * Namespaces. Передали параметры.
     *
     * @return void
     * @throws LoaderError Ошибки Твига.
     */
    public function testProcessNamespaces() : void
    {
        $this->testObject = new TemplateEngine(
            $this->getMockTwigOptions(
                false,
                [],
                [
                    '/tests/resources/tools.twig/twig_another' => 'namespace',
                    '/tests/resources/tools.twig/twig_another_place' => null,
                ]
            ),
            new BitrixLoader($_SERVER['DOCUMENT_ROOT']),
            $this->getMockModulesViewsLocator()
        );

        /** @var FilesystemLoader $loader */
        $loader = $this->testObject->getEngine()->getLoader();

        $namespaces = $loader->getNamespaces();

        $this->assertCount(2, $namespaces);
        $this->assertSame(['__main__', 'namespace'], $namespaces, 'Пространство имен не зарегистрировалось.');

        // Параметр с null в качестве namespace должен добавиться в пути загрузчика.
        $paths = $loader->getPaths();

        $this->assertTrue(
            in_array($_SERVER['DOCUMENT_ROOT'] . '/tests/resources/tools.twig/twig_another_place', $paths, true),
            'Путь без пространства имен не добавился.'
        );
    }

    /**
     * Extensions. Регистрация штатных.
     *
     * @return void
     */
    public function testProcessStandartExtensions() : void
    {
        $twig = $this->testObject->getEngine();

        foreach ([BitrixExtension::class, PhpGlobalsExtension::class, CustomFunctionsExtension::class] as $extension) {
            $this->assertTrue(
                $twig->hasExtension($extension),
                'Штатное расширение не зарегистрировалось.'
            );
        }
    }

    /**
     * Extensions. Debug.
     *
     * @return void
     * @throws LoaderError Ошибки Твига.
     */
    public function testProcessDebugExtensions() : void
    {
        $this->testObject = new TemplateEngine(
            $this->getMockTwigOptions(
                false,
                ['debug' => true],
            ),
            new BitrixLoader($_SERVER['DOCUMENT_ROOT']),
            $this->getMockModulesViewsLocator()
        );

        $twig = $this->testObject->getEngine();

        $this->assertTrue(
            $twig->hasExtension(DebugExtension::class),
            'DebugExtension не зарегистрировалось.'
        );
    }

    /**
     * Extensions. Из конфига.
     *
     * @return void
     * @throws LoaderError Ошибки Твига.
     */
    public function testProcessExternalExtensions() : void
    {
        $fooExtension = new class extends AbstractExtension {
        };
        $barExtension = new class extends AbstractExtension {
        };

        $this->testObject = new TemplateEngine(
            $this->getMockTwigOptions(
                false,
                [],
                [],
                [get_class($fooExtension), $barExtension]
            ),
            new BitrixLoader($_SERVER['DOCUMENT_ROOT']),
            $this->getMockModulesViewsLocator()
        );

        $twig = $this->testObject->getEngine();

        foreach ([get_class($fooExtension), get_class($barExtension)] as $extensionClass) {
            $this->assertTrue(
                $twig->hasExtension($extensionClass),
                'Внешнее расширение не зарегистрировалось.'
            );
        }
    }

    /**
     * Runtimes.
     *
     * @return void
     * @throws LoaderError Ошибки Твига.
     * @throws ReflectionException
     */
    public function testProcessRuntimes() : void
    {
        $fooRuntime = new class implements RuntimeLoaderInterface {
            public function load($class)
            {
            }
        };

        $this->testObject = new TemplateEngine(
            $this->getMockTwigOptions(
                false,
                [],
                [],
                [],
                [],
                [$fooRuntime],
            ),
            new BitrixLoader($_SERVER['DOCUMENT_ROOT']),
            $this->getMockModulesViewsLocator()
        );

        $twig = $this->testObject->getEngine();

        $ref = new ReflectionProperty($twig, 'runtimeLoaders');
        $ref->setAccessible(true);

        $this->assertNotEmpty($ref->getValue($twig), 'Runtime не зарегистрировался.');

        $this->assertSame($fooRuntime, $ref->getValue($twig)[0], 'Зарегистрировался не тот runtime.');
    }

    /**
     * Globals.
     *
     * @return void
     * @throws LoaderError Ошибки Твига.
     * @throws ReflectionException
     */
    public function testProcessGlobals() : void
    {
        $fooClass = new class {};

        $this->testObject = new TemplateEngine(
            $this->getMockTwigOptions(
                false,
                [],
                [],
                [],
                ['app' => '123', 'obj' => $fooClass],
                [],
            ),
            new BitrixLoader($_SERVER['DOCUMENT_ROOT']),
            $this->getMockModulesViewsLocator()
        );

        $twig = $this->testObject->getEngine();
        $ref = new ReflectionProperty($twig, 'globals');
        $ref->setAccessible(true);

        $globals = $ref->getValue($twig);

        $this->assertSame('123', $globals['app']);
        $this->assertSame($fooClass, $globals['obj']);
    }

    /**
     * Мок TwigOptionsStorage.
     *
     * @param boolean $importFromModules
     * @param array   $options
     * @param array   $namespaces
     * @param array   $extensions
     * @param array   $globals
     * @param array   $runtimes
     *
     * @return mixed
     */
    private function getMockTwigOptions(
        bool $importFromModules = false,
        array $options = [],
        array $namespaces = [],
        array $extensions = [],
        array $globals = [],
        array $runtimes = []
    ) {
        $mock = Mockery::mock(TwigOptionsStorage::class)->makePartial();
        $mock = $mock->shouldReceive('getOptions')->andReturn([]);
        $mock = $mock->shouldReceive('asArray')->once()->andReturn($options);
        $mock = $mock->shouldReceive('getImportFromModules')->once()->andReturn($importFromModules);
        $mock = $mock->shouldReceive('getNamespaces')->once()->andReturn($namespaces);
        $mock = $mock->shouldReceive('getExtensions')->once()->andReturn($extensions);
        $mock = $mock->shouldReceive('getGlobals')->once()->andReturn($globals);
        $mock = $mock->shouldReceive('getRuntimes')->once()->andReturn($runtimes);

        return $mock->getMock();
    }

    /**
     * Мок ModulesViewsLocator.
     *
     * @return mixed
     */
    private function getMockModulesViewsLocator()
    {
        $mock = Mockery::mock(ModulesViewsLocator::class);

        $mock = $mock->shouldReceive('get')->andReturn([]);

        return $mock->getMock();
    }

    /**
     * @return mixed
     */
    private function mockEvent()
    {
        $mock = Mockery::mock('overload:\Bitrix\Main\Event');
        $mock = $mock->shouldReceive('send');
        $mock = $mock->shouldReceive('getResults')->andReturn([]);

        return $mock->getMock();
    }
}
