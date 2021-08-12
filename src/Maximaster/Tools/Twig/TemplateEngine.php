<?php

namespace Maximaster\Tools\Twig;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use CBitrixComponentTemplate;
use LogicException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Twig\Error\Error as TwigError;
use Bitrix\Main\Localization\Loc;
use Twig\Extra\Cache\CacheExtension;

/**
 * Class TemplateEngine. Небольшой синглтон, который позволяет в процессе работы страницы несколько раз обращаться к
 * одному и тому же рендереру страниц
 * @package Maximaster\Twig
 */
class TemplateEngine
{
    /**
     * @var Environment $engine Твиг.
     */
    private $engine;

    /**
     * @var TwigOptionsStorage $options Опции.
     */
    private $options;

    /**
     * @var BitrixLoader $loader Загрузчик Твига.
     */
    private $loader;

    /**
     * @var TemplateEngine|null $instance
     */
    private static $instance = null;

    /**
     * @var ModulesViewsLocator $modulesViewsLocator Поиск по модулям.
     */
    private $modulesViewsLocator;

    /**
     * TemplateEngine constructor.
     *
     * @param TwigOptionsStorage  $twigOptionsStorage  Опции.
     * @param BitrixLoader        $loader              Загрузчик Твига.
     * @param ModulesViewsLocator $modulesViewsLocator Поиск по модулям.
     *
     * @throws LoaderError Ошибки Твига.
     */
    public function __construct(
        TwigOptionsStorage $twigOptionsStorage,
        BitrixLoader $loader,
        ModulesViewsLocator $modulesViewsLocator
    ) {
        $this->options = $twigOptionsStorage;
        $this->modulesViewsLocator = $modulesViewsLocator;
        $this->loader = $loader;

        // Namespaces
        foreach ($this->options->getNamespaces() as $path => $namespace) {
            if (!$namespace) {
                $this->loader->addPath($_SERVER['DOCUMENT_ROOT'] . $path);
            } else {
                $this->loader->addPath($_SERVER['DOCUMENT_ROOT'] . $path, $namespace);
            }
        }

        if ($this->getOptions()->getImportFromModules()) {
            $this->initModulesPath();
        }

        $this->engine = new Environment(
            $this->loader,
            $this->options->asArray()
        );

        $this->initExtensions();
        $this->initGlobals();
        $this->initRuntimes();

        $this->generateInitEvent();

        static::$instance = $this;
    }

    /**
     * Возвращает настроенный инстанс движка Twig.
     *
     * @return Environment
     */
    public function getEngine() : Environment
    {
        return $this->engine;
    }

    /**
     * Очищает весь кеш твига.
     *
     * @throws LoaderError Ошибки Твига.
     * @deprecated начиная с 0.8. Будет удален в 1.0.
     */
    public static function clearAllCache(): int
    {
        $cleaner = new TwigCacheCleaner(static::getInstance()->getEngine());

        return $cleaner->clearAll();
    }

    /**
     * Инициализируется директории с шаблонами модулей.
     *
     * @return void
     *
     * @throws LoaderError Ошибки Твига.
     * @since 12.08.2021
     */
    private function initModulesPath() : void
    {
        $modulesViews = $this->modulesViewsLocator->get();

        if (!$modulesViews) {
            return;
        }

        foreach ($modulesViews as $moduleId => $dirsView) {
            foreach ($dirsView as $dirView) {
                $this->loader->addPath($dirView, $moduleId);
            }
        }
    }

    /**
     * Инициализируется расширения, необходимые для работы.
     *
     * @return void
     */
    private function initExtensions() : void
    {
        if ($this->engine->isDebug()) {
            $this->engine->addExtension(new TwigDebugExtension());
        }

        $this->engine->addExtension(new BitrixExtension());
        $this->engine->addExtension(new PhpGlobalsExtension());
        $this->engine->addExtension(new CustomFunctionsExtension());

        // Для реализации работы директивы cache
        if (class_exists(CacheExtension::class)
            &&
            !$this->engine->hasExtension(CacheExtension::class)
        ) {
            $this->engine->addExtension(new CacheExtension());
        }

        // Extensions из конфига
        $configExtensions = $this->options->getExtensions();

        foreach ($configExtensions as $configExtension) {
            $extension = is_object($configExtension) ? $configExtension : new $configExtension;
            if ($this->engine->hasExtension(
                is_object($configExtension) ? get_class($configExtension) : $configExtension
            )) {
                continue;
            }

            $this->engine->addExtension($extension);
        }
    }

    /**
     * Инициализируется runtimes.
     *
     * @return void
     */
    private function initRuntimes() : void
    {
        $runtimes = $this->options->getRuntimes();

        if (!$runtimes) {
            return;
        }

        foreach ($runtimes as $runtime) {
            $this->engine->addRuntimeLoader($runtime);
        }
    }

    /**
     * Инициализируется globals.
     *
     * @return void
     */
    private function initGlobals() : void
    {
        $globals = $this->options->getGlobals();

        if (!$globals) {
            return;
        }

        foreach ($globals as $global => $value) {
            $this->engine->addGlobal($global, $value);
        }
    }

    /**
     * @return TemplateEngine|null
     * @throws LoaderError Ошибки Твига.
     */
    public static function getInstance(): ?TemplateEngine
    {
        return static::$instance ?: (static::$instance = new self(
            new TwigOptionsStorage(),
            new BitrixLoader($_SERVER['DOCUMENT_ROOT']),
            new ModulesViewsLocator()
        ));
    }

    /**
     * Собственно сама функция - рендерер. Принимает все данные о шаблоне и компоненте, выводит в stdout данные.
     * Содержит дополнительную обработку для component_epilog.php.
     *
     * @param string                   $templateFile
     * @param array                    $arResult
     * @param array                    $arParams
     * @param array                    $arLangMessages
     * @param string                   $templateFolder
     * @param string                   $parentTemplateFolder
     * @param CBitrixComponentTemplate $template
     *
     * @return void
     * @throws LoaderError | RuntimeError | SyntaxError | TwigError Ошибки Твига.
     */
    public static function render(
        /** @noinspection PhpUnusedParameterInspection */ string $templateFile,
        array $arResult,
        array $arParams,
        array $arLangMessages,
        string $templateFolder,
        string $parentTemplateFolder,
        CBitrixComponentTemplate $template
    ) {
        if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) {
            throw new TwigError('Пролог не подключен');
        }

        $component = $template->__component;
        /** @var BitrixLoader $loader */
        $loader = static::getInstance()->getEngine()->getLoader();
        if (!($loader instanceof BitrixLoader)) {
            throw new LogicException(
                "Загрузчиком должен быть 'Maximaster\\Tools\\Twig\\BitrixLoader' или его наследник"
            );
        }

        $templateName = $loader->makeComponentTemplateName($template);

        $engine = static::getInstance();
        $options = $engine->getOptions();

        if ($options['extract_result']) {
            $context = $arResult;
            $context['result'] =& $arResult;
        } else {
            $context = ['result' => $arResult];
        }

        // Битрикс не умеет "лениво" грузить языковые сообщения если они запрашиваются из twig, т.к. ищет вызов
        // GetMessage, а после ищет рядом lang-папки. Т.к. рядом с кешем их конечно нет
        // Кроме того, Битрикс ждёт такое же имя файла, внутри lang-папки. Т.е. например template.twig
        // Но сам includ'ит их, что в случае twig файла конечно никак не сработает. Поэтому подменяем имя
        $templateMess = Loc::loadLanguageFile(
            $_SERVER['DOCUMENT_ROOT'].preg_replace('/[.]twig$/', '.php', $template->GetFile())
        );

        // Это не обязательно делать если не используется lang, т.к. Битрикс загруженные фразы все равно запомнил
        // и они будут доступны через вызов getMessage в шаблоне. После удаления lang, можно удалить и этот код
        if (is_array($templateMess)) {
            $arLangMessages = array_merge($arLangMessages, $templateMess);
        }

        $context = [
                'params' => $arParams,
                'lang' => $arLangMessages,
                'template' => $template,
                'component' => $component,
                'templateFolder' => $templateFolder,
                'parentTemplateFolder' => $parentTemplateFolder,
                'render' => compact('templateName', 'engine'),
            ] + $context;

        echo static::getInstance()->getEngine()->render($templateName, $context);

        $component_epilog = $templateFolder . '/component_epilog.php';
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $component_epilog)) {
            /** @var \CBitrixComponent $component */
            $component->SetTemplateEpilog([
                'epilogFile' => $component_epilog,
                'templateName' => $template->__name,
                'templateFile' => $template->__file,
                'templateFolder' => $template->__folder,
                'templateData' => false,
            ]);
        }
    }

    /**
     * Рендерит произвольный twig-файл, возвращает результат в виде строки.
     *
     * @param string $src     Путь к twig-файлу.
     * @param array  $context Контекст.
     *
     * @return string Результат рендера.
     * @throws LoaderError | RuntimeError | SyntaxError Ошибки Твига.
     */
    public static function renderStandalone(string $src, array $context = []): string
    {
        return static::getInstance()->getEngine()->render($src, $context);
    }

    /**
     * Рендерит произвольный twig-файл, выводит результат в stdout.
     *
     * @param string $src     Путь к twig-файлу.
     * @param array  $context Контекст.
     *
     * @return void
     * @throws LoaderError | RuntimeError | SyntaxError Ошибки Твига.
     */
    public static function displayStandalone(string $src, array $context = [])
    {
        echo static::renderStandalone($src, $context);
    }

    /**
     * @return TwigOptionsStorage
     */
    public function getOptions(): TwigOptionsStorage
    {
        return $this->options;
    }

    /**
     * Создается событие для внесения в Twig изменения из проекта.
     *
     * @return void
     * @throws LogicException Когда что-то неверно с постановкой события.
     */
    private function generateInitEvent() : void
    {
        $eventName = 'onAfterTwigTemplateEngineInited';
        $event = new Event('', $eventName, array('engine' => $this->engine));
        $event->send();
        if ($event->getResults()) {
            foreach ($event->getResults() as $evenResult) {
                if ($evenResult->getType() == EventResult::SUCCESS) {
                    $twig = current($evenResult->getParameters());
                    if (!($twig instanceof Environment)) {
                        throw new LogicException(
                            "Событие '{$eventName}' должно возвращать экземпляр класса ".
                            "'\\TwigEnvironment' при успешной отработке"
                        );
                    }

                    $this->engine = $twig;
                }
            }
        }
    }
}