<?php

namespace Maximaster\Tools\Twig;

use Twig\Extension\AbstractExtension as TwigAbstractExtension;
use Twig\TwigFunction;
use Twig\Extension\GlobalsInterface as TwigGlobalsInterface;

/**
 * Class CustomFunctionsExtension
 * @package Maximaster\Tools\Twig
 */
class CustomFunctionsExtension extends TwigAbstractExtension implements TwigGlobalsInterface
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'maximaster_functions_extension';
    }

    /**
     * @inheritdoc
     */
    public function getGlobals(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('russianPluralForm', [$this, 'russianPluralForm']),
        ];
    }

    /**
     * Выводит правильный вариант множественной формы числа.
     *
     * @param integer  $howmuch Число, для которого нужно сформировать множественную форму
     * (число будет приведено к целому).
     * @param string[] $input  Массив, содержащий 3 слова ['билетов', 'билет', 'билета']
     * (Ноль билетов, Один билет, Два билета).
     *
     * @return string
     */
    public static function russianPluralForm(int $howmuch, array $input) : string
    {
        $howmuch = (int)$howmuch;
        $l2 = substr($howmuch, -2);
        $l1 = substr($howmuch, -1);
        if ($l2 > 10 && $l2 < 20) {
            return $input[0];
        } else {
            switch ($l1) {
                case 1:
                    return $input[1];
                case 2:
                case 3:
                case 4:
                    return $input[2];
                default:
                    return $input[0];
            }
        }
    }
}