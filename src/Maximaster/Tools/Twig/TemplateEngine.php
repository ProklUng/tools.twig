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
     * @var Environment
     */
    private $engine;

    /**
     * @var TwigOptionsStorage $options
     */
    private $options;

    /**
     * @var TemplateEngine|null $instance
     */
    private static $instance = null;

    /**
     * TemplateEngine constructor.
     *
     * @throws LoaderError Ошибки Твига.
     */
    public function __construct()
    {
        $this->options = new TwigOptionsStorage();
        $loader = new BitrixLoader($_SERVER['DOCUMENT_ROOT']);

        // Namespaces
        foreach ($this->options->getNamespaces() as $path => $namespace) {
            if (!$namespace) {
                $loader->addPath($_SERVER['DOCUMENT_ROOT'] . $path);
            } else {
                $loader->addPath($_SERVER['DOCUMENT_ROOT'] . $path, $namespace);
            }
        }

        $this->engine = new Environment(
            $loader,
            $this->options->asArray()
        );

        $this->initExtensions();
        $this->initGlobals();
        $this->initRuntimes();

        $this->generateInitEvent();

        self::$instance = $this;
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
     * @deprecated начиная с 0.8. Будет удален в 1.0.
     */
    public static function clearAllCache(): int
    {
        $cleaner = new TwigCacheCleaner(self::getInstance()->getEngine());

        return $cleaner->clearAll();
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
     */
    public static function getInstance(): ?TemplateEngine
    {
        return self::$instance ?: (self::$instance = new self);
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
     * @throws LoaderError | RuntimeError | SyntaxError Ошибки Твига.
     */
    public static function render(
        /** @noinspection PhpUnusedParameterInspection */ $templateFile,
        $arResult,
        $arParams,
        $arLangMessages,
        $templateFolder,
        $parentTemplateFolder,
        CBitrixComponentTemplate $template
    ) {
        if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) {
            throw new TwigError('Пролог не подключен');
        }

        $component = $template->__component;
        /** @var BitrixLoader $loader */
        $loader = self::getInstance()->getEngine()->getLoader();
        if (!($loader instanceof BitrixLoader)) {
            throw new LogicException(
                "Загрузчиком должен быть 'Maximaster\\Tools\\Twig\\BitrixLoader' или его наследник"
            );
        }

        $templateName = $loader->makeComponentTemplateName($template);

        $engine = self::getInstance();
        $options = $engine->getOptions();

        if ($options['extract_result']) {
            $context = $arResult;
            $context['result'] =& $arResult;
        } else {
            $context = array('result' => $arResult);
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

        echo self::getInstance()->getEngine()->render($templateName, $context);

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
    public static function renderStandalone(string $src, array $context = [])
    {
        return self::getInstance()->getEngine()->render($src, $context);
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
        echo self::renderStandalone($src, $context);
    }

    /**
     * @return TwigOptionsStorage
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Создается событие для внесения в Twig изменения из проекта.
     *
     * @return void
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
