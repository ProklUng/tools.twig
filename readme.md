# maximaster/tools.twig

Форк библиотеки [maximaster/tools.twig](https://github.com/maximaster/tools.twig). Подключен Twig версии 3.X. Минимальная версия PHP поднята до >=7.2.5. Можно использовать с Bitrix версии >=20.5.393, т.к. данные версии не ругаются на mbstring.func_overload = 0.

Данная библиотека позволяет использовать twig шаблоны в 1С Битрикс для компонентов 2.0. Обрабатываются файлы шаблонов, имеющие расширение `.twig`. Если создать в директории шаблона компонента файл `template.twig`, то именно он будет использоваться при генерации шаблона.

Для установки форкнутой версии через composer необходимо добавить в composer.json:

```
...
"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/proklUng/tools.twig"
        }
    ],
    "require": {
        ...
        "maximaster/tools.twig": "dev-master",
        ...
    },
...
```

и выполнить

```
composer update
```

## Простой пример

Для наследования шаблона `new_year` компонента `bitrix:news.detail` в twig шаблоне нужно всего-лишь подключить этот шаблон с помощью особого синтаксиса:

```twig
{% extends "bitrix:news.detail:new_year" %}
```
После чего можно будет переопределить все блоки, которые есть в родительском шаблоне. Подробнее о [синтаксисе](docs/syntax.md) - в документации

## Документация 

### Обязательный момент

В `init.php`:

```php
// Регистрация Твига

maximasterRegisterTwigTemplateEngine();
```


* **[Синтаксис подключения шаблонов](docs/syntax.md)**
* **[Доступные переменные и функции внутри шаблонов](docs/twig_extension.md)**
* **[Конфигурирование](docs/configuration.md)**
* **[Работа с кешем](docs/working_with_cache.md)**
* **[Расширение возможностей](docs/extend.md)**
* **[Тонкости интеграции с битриксом](docs/bitrix_pitfalls.md)**

## Отличный от оригинала функционал

- ***Extensions*** - альтернативный способу добавления расширений через события - ключ в `settings.php` (или `settings_extra.php`)
 - `extensions`:
 
 ```php
 ...
 'extensions' => [
     new FooExtension(),
     BarExtension::class
 ]    
 ```

Здесь класс FooExtension или BarExtension должны быть наследниками класса `Twig\Extension\AbstractExtension` 
или имплементировать интерфейс `Twig\Extension\ExtensionInterface`.

- ***Runtimes*** - ключ в `settings.php` (или `settings_extra.php`) - `runtimes`. Массив с анонимными классами
 вида:

```php
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Twig\Extra\Cache\CacheRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

...
'runtimes' => [
    new class implements RuntimeLoaderInterface {
        public function load($class) {
            if (CacheRuntime::class === $class) {
                return new CacheRuntime(
                              new \WebArch\BitrixCache\AntiStampedeCacheAdapter(
                                          '/', 3600, 'cache/s1/twig-cache'
                              );
            }
        }
    }
]    
```

Таким образом достигается работы директивы [cache](https://twig.symfony.com/doc/3.x/tags/cache.html).

Кэшером может выступать любой класс, реализующий интерфейс `Symfony\Contracts\Cache\CacheInterface`.

- ***Namespaces*** - пространства имен для записи путей к шаблонам вида `@FooNamespace/nice_template.twig`.

ключ в `settings.php` (или `settings_extra.php`) - `namespaces`:

```php
...
// ...
'namespaces' => [
   '/local/fooTemplates' => 'FooNamespace',
   '/another_path/templates' => null // Добавится еще один путь, где будут искаться шаблоны.
]    
```

## Всякое

1) Хэлперы:

 - `Maximaster\Tools\Twig\TemplateEngine::getInstance()->getEngine()` - экземпляр сконфигурированного Твига. 
 - `Maximaster\Tools\Twig\TemplateEngine::renderStandalone(string $src, array $context = [])` - Рендерит произвольный 
 twig-файл. Результат возвращается в виде строки.
 - `Maximaster\Tools\Twig\TemplateEngine::displayStandalone(string $src, array $context = [])` - Рендерит произвольный twig-файл, 
 выводит результат в stdout.   