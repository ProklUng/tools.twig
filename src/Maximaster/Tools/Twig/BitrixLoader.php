<?php

namespace Maximaster\Tools\Twig;

use Twig\Template;
use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Loader\LoaderInterface as TwigLoaderInterface;

/**
 * Class BitrixLoader. Класс загрузчик файлов шаблонов. Понимает специализированный синтаксис
 * @package Maximaster\Twig
 */
class BitrixLoader extends TwigFilesystemLoader implements TwigLoaderInterface
{
    /**
     * @var array Статическое хранилище для уже отрезолвленных путей для ускорения
     */
    private static $resolved = [];

    /**
     * @var array Статическое хранилище нормализованных имен шаблонов для ускорения
     */
    private static $normalized = [];

    /**
     * {@inheritdoc}
     *
     * Принимает на вход имя компонента и шаблона в виде<br>
     * <b>vendor:componentname[:template[:specifictemplatefile]]</b><br>
     * Например bitrix:news.list:.default, или bitrix:sale.order:show:step1
     *
     * @param string $name Шаблон и имя компонента.
     *
     * @return string
     * @throws TwigLoaderError
     */
    public function getSource(string $name) : string
    {
        return (string)file_get_contents($this->getSourcePath($name));
    }

    /**
     * @inheritdoc
     * @throws TwigLoaderError
     */
    protected function findTemplate(string $name, bool $throw = true): ?string
    {
        if ($this->isNamespacedTemplate($name)) {
            return parent::findTemplate($name, $throw);
        }

        return $this->getSourcePath($name);
    }

    /**
     * @inheritdoc
     */
    public function getCacheKey(string $name): string
    {
        return $this->normalizeName($name);
    }

    /**
     * {@inheritdoc}
     * Не использовать в продакшене!
     * Метод используется только в режиме разработки или при использовании опции auto_reload = true.
     *
     * @param string  $name Путь к шаблону.
     * @param integer $time Время изменения закешированного шаблона.
     *
     * @return boolean Актуален ли закешированный шаблон.
     */
    public function isFresh(string $name, int $time): bool
    {
        if ($this->isNamespacedTemplate($name)) {
            return parent::isFresh($name, $time);
        }

        return filemtime($this->getSourcePath($name)) <= $time;
    }

    /**
     * Получает путь до файла с шаблоном по его имени
     *
     * @param string $name Название.
     *
     * @return string
     *
     * @throws TwigLoaderError Ошибки Твига.
     */
    public function getSourcePath(string $name): string
    {
        $name = $this->normalizeName($name);

        if (isset(static::$resolved[$name])) {
            return static::$resolved[$name];
        }

        $resolved = '';
        if (strpos($name, ':') !== false) {
            $resolved = $this->getComponentTemplatePath($name);
        } elseif (($firstChar = substr($name, 0, 1)) === DIRECTORY_SEPARATOR) {
            $resolved = is_file($name) ? $name : $_SERVER['DOCUMENT_ROOT'].$name;
        }

        if (!file_exists($resolved)) {
            throw new TwigLoaderError("Не удалось найти шаблон '{$name}'");
        }

        return static::$resolved[ $name ] = $resolved;
    }

    /**
     * На основании шаблона компонента создает полное имя для Twig.
     *
     * @param \CBitrixComponentTemplate $template Шаблон.
     *
     * @return string
     */
    public function makeComponentTemplateName(\CBitrixComponentTemplate $template)
    {
        if ($template->__fileAlt) {
            return $template->__fileAlt;
        }

        $component = $template->getComponent();

        if (!empty($component->getParent())) {
            return $template->__file;
        }

        $templatePage = $template->__page;
        $templateName = $template->__name;
        $componentName = $component->getName();

        return "{$componentName}:{$templateName}:{$templatePage}";
    }

    /**
     * Преобразует имя в максимально-полное начертание
     *
     * @param string $name Название шаблона.
     *
     * @return string
     */
    public function normalizeName(string $name) : string
    {
        if (strpos($name, DIRECTORY_SEPARATOR) !== false) {
            $name = preg_replace('#/{2,}#', '/', str_replace('\\', '/', $name));
        }

        $isComponentPath = strpos($name, ':') !== false;
        $isGlobalPath = substr($name, 0, 1) === '/';

        if (($isComponentPath || $isGlobalPath) && isset(static::$normalized[ $name ])) {
            return static::$normalized[ $name ];
        }

        if ($isComponentPath) {
            list($namespace, $component, $template, $file) = explode(':', $name);

            if (strlen($template) === 0) {
                $template = '.default';
            }

            if (strlen($file) === 0) {
                $file = 'template';
            }

            $normalizedName = "{$namespace}:{$component}:{$template}:{$file}";
        } elseif ($isGlobalPath) {
            $normalizedName = $name;
        } else {
            $lastRendered = $this->getLastRenderedTemplate();
            if ($lastRendered) {
                $normalizedName = dirname($lastRendered).'/'.$name;
            } else {
                $normalizedName = $name;
            }
        }

        return (static::$normalized[ $name ] = $normalizedName);
    }


    /**
     * @return string
     */
    private function getLastRenderedTemplate() : string
    {
        $trace = debug_backtrace();
        foreach ($trace as $point) {
            if (isset($point['object']) && ($obj = $point['object']) instanceof Template) {
                /**
                 * @var Template $obj
                 */
                return $obj->getSourceContext()->getPath();
            }
        }

        return '';
    }

    /**
     * По Битрикс-имени шаблона возвращает путь к его файлу
     *
     * @param string $name Шаблон.
     *
     * @return string
     */
    private function getComponentTemplatePath(string $name) : string
    {
        $name = $this->normalizeName($name);

        list($namespace, $component, $template, $page) = explode(':', $name);

        // Относительный путь, например: vendor:component:template:inc/area.twig
        $isRelative = $page !== basename($page);

        $dotExt = '.twig';
        if ($isRelative) {
            if (pathinfo($page, PATHINFO_EXTENSION) !== 'twig') {
                $page .= $dotExt;
            }
        } else {
            $page = basename($page, $dotExt);
        }

        $componentName = "{$namespace}:{$component}";

        $component = new \CBitrixComponent();
        $component->InitComponent($componentName, $template);
        if (!$isRelative) {
            $component->__templatePage = $page;
        }

        $obTemplate = new \CBitrixComponentTemplate();
        $obTemplate->Init($component);
        $templatePath = $_SERVER['DOCUMENT_ROOT'].(
            $isRelative ? ($obTemplate->GetFolder().DIRECTORY_SEPARATOR.$page) : $obTemplate->GetFile()
            );

        return $templatePath;
    }

    /**
     * Определение - шаблон с namespace или нет.
     *
     * @param string $name Шаблон.
     *
     * @return boolean
     *
     * @since 12.08.2021
     */
    private function isNamespacedTemplate(string $name) : bool
    {
        if (strpos($name, '@') === 0) {
            return true;
        }

        return false;
    }
}
