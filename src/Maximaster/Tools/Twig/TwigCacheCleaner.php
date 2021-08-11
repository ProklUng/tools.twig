<?php

namespace Maximaster\Tools\Twig;

use Bitrix\Main\ArgumentException;
use InvalidArgumentException;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Twig\Environment as TwigEnvironment;

/**
 * Класс, который берет на себя очистку кеша твига
 * @package Maximaster\Tools\Twig
 */
class TwigCacheCleaner
{
    /**
     * @var TwigEnvironment $engine
     */
    private $engine;

    /**
     * TwigCacheCleaner constructor.
     *
     * @param TwigEnvironment $engine Twig.
     */
    public function __construct(TwigEnvironment $engine)
    {
        $this->engine = $engine;
        $this->checkCacheEngine();
    }

    /**
     * Очищает кеш по его строковому имени.
     *
     * @param string $name Имя шаблона для удаления.
     *
     * @return integer Количество удаленных файлов кеша.
     * @throws InvalidArgumentException
     */
    public function clearByName(string $name)
    {
        if (strlen($name) === 0) {
            throw new InvalidArgumentException('Имя шаблона не задано');
        }

        $counter = 0;

        $templateClass = $this->engine->getTemplateClass($name);
        if (strlen($name) === 0) {
            throw new InvalidArgumentException("Шаблон с именем '{$name}' не найден");
        }

        $fileName = $this->engine->getCache(false)->generateKey($name, $templateClass);

        if (is_file($fileName)) {
            @unlink($fileName);

            if (is_file($fileName)) {
                throw new LogicException("Шаблон '{$name}'.\nПроизошла ошибка в процессе удаления файла:\n$fileName");
            }

            $counter++;
        }

        return $counter;
    }


    /**
     * Удаляет весь кеш твига.
     *
     * @return integer Количество удаленных файлов кеша.
     */
    public function clearAll() : int
    {
        $counter = 0;

        $cachePath = $this->engine->getCache(true);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cachePath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                @unlink($file->getPathname());
                if (!is_file($file->getPathname())) {
                    $counter++;
                }
            }
        }

        return $counter;
    }

    /**
     * Проверяет, является ли кеш файловым, просто на основании существования директории с кешем.
     *
     * @return boolean
     */
    private function isFileCache() : bool
    {
        return is_dir($this->engine->getCache(true));
    }

    /**
     * @return void
     * @throws LogicException
     */
    private function checkCacheEngine()
    {
        if (!$this->isFileCache()) {
            throw new LogicException('Невозможно очистить кеш. Он либо хранится не в файлах, либо кеш отсутствует полностью');
        }
    }
}
