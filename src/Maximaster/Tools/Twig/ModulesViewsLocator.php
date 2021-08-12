<?php

namespace Maximaster\Tools\Twig;

use Bitrix\Main\ModuleManager;

/**
 * Class ModulesViewsLocator
 * @package Maximaster\Tools\Twig
 *
 * @since 12.08.2021
 */
class ModulesViewsLocator
{
    /**
     * Собрать директории модулей, где могут лежать твиговские шаблоны.
     *
     * @return array
     */
    public function get() : array
    {
        $result = [];

        $installedModules = ModuleManager::getInstalledModules();
        foreach ($installedModules as $module) {
            $pathModule = '';

            if (@file_exists($_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $module['ID'])) {
                $pathModule = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $module['ID'];
            }

            if (@file_exists($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/' . $module['ID'])) {
                $pathModule = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/' . $module['ID'];
            }

            if (!$pathModule) {
                continue;
            }
            
            $paths = [$pathModule . '/Resources/views', $pathModule . '/templates'];
            foreach ($paths as $path) {
                if (@file_exists($dir = $path)) {
                    $result[$module['ID']][] = $dir;
                }
            }
        }

        return $result;
    }
}