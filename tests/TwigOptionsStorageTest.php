<?php

namespace Maximaster\Tools\Twig\Test;

use Exception;
use Maximaster\Tools\Twig\TwigOptionsStorage;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Class TwigOptionsStorageTest
 *
 * @since 12.08.2021
 */
class TwigOptionsStorageTest extends TestCase
{
    /**
     * @var TwigOptionsStorage $testObject Тестовый объект.
     */
    private $testObject;

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function setUp(): void
    {
        Mockery::resetContainer();
        parent::setUp();

        define('SITE_CHARSET', 'UTF-8');
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
     */
    public function testProcessDefaultParams() : void
    {
        $this->setMockOptions();

        $this->testObject = new TwigOptionsStorage();

        $result = $this->testObject->asArray();

        $expected = [
            'debug' => false,
            'charset' => SITE_CHARSET,
            'cache' => $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache/maximaster/tools.twig',
            'auto_reload' => isset($_GET['clear_cache']) && strtoupper($_GET['clear_cache']) == 'Y',
            'autoescape' => false,
            'extract_result' => false,
            'use_by_default' => false,
            'import_from_modules' => false,
            'runtimes' => [],
            'extensions' => [],
            'namespaces' => [],
            'globals' => [],
        ];

        $this->assertSame($expected, $result, 'Значения по умолчанию обработались криво.');
    }

    /**
     * Обработка опций.
     *
     * @return void
     */
    public function testProcessOptions() : void
    {
        $this->setMockOptions([
            'tools' => [
                'twig' => [
                    'debug' => true,
                    'charset' => 'CHN',
                    'auto_reload' => true,
                    'extract_result' => true,
                    'extensions' => [FooClass::class],
                ]
            ]
        ]);

        $this->testObject = new TwigOptionsStorage();

        $result = $this->testObject->asArray();

        $this->assertSame(true, $result['debug']);
        $this->assertSame('CHN', $result['charset']);
        $this->assertSame(true, $result['auto_reload']);
        $this->assertSame(true, $result['extract_result']);
        $this->assertSame([FooClass::class], $result['extensions']);
    }

    /**
     * Мок Bitrix/Options.
     *
     * @param array $values
     *
     * @return void
     */
    private function setMockOptions(array $values = []): void
    {
        $mock = \Mockery::mock('overload:\Bitrix\Main\Config\Configuration');
        $mock = $mock->shouldReceive('getInstance')->andReturnSelf();
        $mock->shouldReceive('get')->andReturn($values);
    }
}
