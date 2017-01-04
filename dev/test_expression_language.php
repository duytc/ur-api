<?php
namespace tagcade\dev;

use AppKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$expressionLanguage = new ExpressionLanguage();

/* ============================================================ */
$result = $expressionLanguage->evaluate('1+1');
var_dump($result);


/* ============================================================ */
$result = $expressionLanguage->evaluate('1+10/2');
var_dump($result);


/* ============================================================ */
$result = $expressionLanguage->evaluate('myVar/2', ['myVar' => 10]);
var_dump($result);


/* ============================================================ */
try {
    $result = $expressionLanguage->evaluate('myVar/0', ['myVar' => 10]);
    var_dump($result);
} catch (\Exception $e) {
    var_dump($e->getMessage());
}


/* ============================================================ */
try {
    $expressionLanguage->register('abs', function ($number) {
        return sprintf('(is_numeric(%1$s) ? abs(%1$s) : %1$s)', $number);
    }, function ($arguments, $number) {
        if (!is_numeric($number)) {
            return $number;
        }

        return abs($number);
    });

    $result = $expressionLanguage->evaluate('10 + abs(myVar)', ['myVar' => -10]);
    var_dump($result);
} catch (\Exception $e) {
    var_dump($e->getMessage());
}

/* ============================================================ */
class NumberExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return array(
            new ExpressionFunction('abs', function ($number) {
                return sprintf('(is_numeric(%1$s) ? abs(%1$s) : %1$s)', $number);
            }, function ($arguments, $number) {
                if (!is_numeric($number)) {
                    return $number;
                }

                return abs($number);
            }),
            new ExpressionFunction('myFn', function ($a, $b) {
                return sprintf('((is_numeric(%1$s) && is_numeric(%2$s)) ? ((%2$s === 0)(%1$s/%2$s - 1) : %1$s)) : null', $a, $b);
            }, function ($arguments, $a, $b) {
                if (!is_numeric($a) || !is_numeric($b)) {
                    return null;
                }

                if ($b === 0) {
                    return null;
                }

                return $a/$b - 1;
            })
        );
    }
}

$expressionLanguageWithNumber = new ExpressionLanguage();
$expressionLanguageWithNumber->registerProvider(new NumberExpressionLanguageProvider());

$result = $expressionLanguageWithNumber->evaluate('abs(-10)');
var_dump($result);

$result = $expressionLanguageWithNumber->evaluate('myFn(10,2)');
var_dump($result);

$result = $expressionLanguageWithNumber->evaluate('myFn(10,"a")');
var_dump($result);

$result = $expressionLanguageWithNumber->evaluate('myFn(10,0)');
var_dump($result);


/* ============================================================ */
try {
    $expressionLanguage->register('lowercase', function ($str) {
        return sprintf('(is_string(%1$s) ? strtolower(%1$s) : %1$s)', $str);
    }, function ($arguments, $str) {
        if (!is_string($str)) {
            return $str;
        }

        return strtolower($str);
    });

    $result = $expressionLanguage->evaluate('lowercase("HELLO")');
    var_dump($result);
} catch (\Exception $e) {
    var_dump($e->getMessage());
}


/* ============================================================ */
class StringExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return array(
            new ExpressionFunction('lowercase', function ($str) {
                return sprintf('(is_string(%1$s) ? strtolower(%1$s) : %1$s)', $str);
            }, function ($arguments, $str) {
                if (!is_string($str)) {
                    return $str;
                }

                return strtolower($str);
            }),
            new ExpressionFunction('uppercase', function ($str) {
                return sprintf('(is_string(%1$s) ? strtoupper(%1$s) : %1$s)', $str);
            }, function ($arguments, $str) {
                if (!is_string($str)) {
                    return $str;
                }

                return strtoupper($str);
            }),
        );
    }
}

$expressionLanguageWithString = new ExpressionLanguage();
$expressionLanguageWithString->registerProvider(new StringExpressionLanguageProvider());

$result = $expressionLanguageWithString->evaluate('lowercase("Hello")');
var_dump($result);

$result = $expressionLanguageWithString->evaluate('uppercase("Hello")');
var_dump($result);


/* ============================================================ */
$expressionLanguage->register(
    'myFn',
    function ($a, $b) {
        return sprintf('(%2$s === 0) ? { return null; } : return %1$s/%2$s - 1;', $b, $a, $b);
    },
    function ($arguments, $a, $b) {
        if ($b === 0) {
            return null;
        }

        return $a / $b - 1;
    });
$result = $expressionLanguage->evaluate('myFn(myVar[0],myVar[1])', ['myVar' => [10, 2]]);
var_dump($result);


