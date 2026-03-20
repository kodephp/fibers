<?php

declare(strict_types=1);

namespace Kode\Fibers\Integration;

use Composer\Composer;
use Composer\IO\IOInterface;

/**
 * 框架自动检测器
 *
 * 自动检测当前运行环境并返回对应框架类型
 */
class FrameworkDetector
{
    public const FRAMEWORK_LARAVEL = 'laravel';
    public const FRAMEWORK_SYMFONY = 'symfony';
    public const FRAMEWORK_YII3 = 'yii3';
    public const FRAMEWORK_THINKPHP = 'thinkphp';
    public const FRAMEWORK_HYPERF = 'hyperf';
    public const FRAMEWORK_WEBMAN = 'webman';
    public const FRAMEWORK_LUMEN = 'lumen';
    public const FRAMEWORK_BASIC = 'basic';

    /**
     * 检测当前框架类型
     */
    public static function detect(): string
    {
        if (self::isLaravel()) {
            return self::FRAMEWORK_LARAVEL;
        }

        if (self::isSymfony()) {
            return self::FRAMEWORK_SYMFONY;
        }

        if (self::isYii3()) {
            return self::FRAMEWORK_YII3;
        }

        if (self::isThinkPHP()) {
            return self::FRAMEWORK_THINKPHP;
        }

        if (self::isHyperf()) {
            return self::FRAMEWORK_HYPERF;
        }

        if (self::isWebman()) {
            return self::FRAMEWORK_WEBMAN;
        }

        if (self::isLumen()) {
            return self::FRAMEWORK_LUMEN;
        }

        return self::FRAMEWORK_BASIC;
    }

    public static function isLaravel(): bool
    {
        return class_exists('\Illuminate\Foundation\Application')
            && class_exists('\Illuminate\Support\Facades\Facade');
    }

    public static function isSymfony(): bool
    {
        return class_exists('\Symfony\Component\HttpKernel\Kernel');
    }

    public static function isYii3(): bool
    {
        return class_exists('\yii\BaseYii') 
            && defined('\yii\BaseYii::VERSION') 
            && method_exists('\yii\BaseYii', 'createApplication');
    }

    public static function isThinkPHP(): bool
    {
        return class_exists('\think\App')
            && (defined('\think\Facade') || function_exists('app'));
    }

    public static function isHyperf(): bool
    {
        return class_exists('\Hyperf\Framework\Bootstrap\ServerStartBootstrap')
            || class_exists('\Hyperf\Di\Container');
    }

    public static function isWebman(): bool
    {
        return defined('WEBMAN_VERSION')
            || (class_exists('\support\App') && class_exists('\Webman\Bootstrap'));
    }

    public static function isLumen(): bool
    {
        return class_exists('\Laravel\Lumen\Application');
    }

    public static function isSwoole(): bool
    {
        return extension_loaded('swoole') && (
            class_exists('\Swoole\Coroutine\Scheduler') ||
            class_exists('\Swoole\Http\Server')
        );
    }

    public static function isSwow(): bool
    {
        return extension_loaded('swow') && class_exists('\Swow\Coroutine');
    }

    public static function getRuntimeInfo(): array
    {
        return [
            'framework' => self::detect(),
            'swoole' => self::isSwoole(),
            'swow' => self::isSwow(),
            'fiber' => function_exists('Fiber::class'),
            'php_version' => PHP_VERSION,
        ];
    }
}
