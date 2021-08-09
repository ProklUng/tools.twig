<?php

namespace Maximaster\Tools\Twig;

use Bitrix\Main\Config\Configuration;

/**
 * Класс для более удобного способа доступа к настрокам twig
 * @package Maximaster\Tools\Twig
 */
class TwigOptionsStorage implements \ArrayAccess
{
    /**
     * @var array $options
     */
    private $options = [];

    /**
     * TwigOptionsStorage constructor.
     */
    public function __construct()
    {
        $this->getOptions();
    }

    /**
     * @return array
     */
    public function getDefaultOptions(): array
    {
        return [
            'debug' => false,
            'charset' => SITE_CHARSET,
            'cache' => $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache/maximaster/tools.twig',
            'auto_reload' => isset($_GET['clear_cache']) && strtoupper($_GET['clear_cache']) == 'Y',
            'autoescape' => false,
            'extract_result' => false,
            'use_by_default' => false
        ];
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        $c = Configuration::getInstance();
        $config = $c->get('maximaster');
        $twigConfig = isset($config['tools']['twig']) ? (array)$config['tools']['twig'] : [];
        $this->options = array_merge($this->getDefaultOptions(), $twigConfig);
        return $this->options;
    }

    /**
     * @return array
     */
    public function asArray(): array
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function getCache(): string
    {
        return (string)$this->options['cache'];
    }

    /**
     * @return boolean
     */
    public function getDebug(): bool
    {
        return (bool)$this->options['debug'];
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return (string)$this->options['charset'];
    }

    /**
     * @return boolean
     */
    public function getAutoReload(): bool
    {
        return (bool)$this->options['auto_reload'];
    }

    /**
     * @return boolean
     */
    public function getAutoescape(): bool
    {
        return (bool)$this->options['autoescape'];
    }

    /**
     * @return boolean
     */
    public function getExtractResult(): bool
    {
        return (bool)$this->options['extract_result'];
    }

    /**
     * @return boolean
     */
    public function getUsedByDefault(): bool
    {
        return (bool)$this->options['use_by_default'];
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setExtractResult($value): TwigOptionsStorage
    {
        $this->options['extract_result'] = !! $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset): bool
    {
        return isset($this->options[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        return $this->options[$offset];
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value): TwigOptionsStorage
    {
        $this->options[ $offset ] = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset) {}
}
