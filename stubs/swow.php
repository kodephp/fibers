<?php

/**
 * PHPStan 专用符号桩：Swow 为 PECL 可选扩展，无法经 composer 提供存根。
 * 仅声明本包用到的最小 API 面，真实实现以运行期 extension_loaded('swow') 为准。
 */

namespace Swow {

    class Coroutine
    {
        /** @param callable $task */
        public static function create(mixed ...$args): object
        {
        }

        /** @param callable $task */
        public static function run(mixed ...$args): object
        {
        }

        public static function sleep(float|int $timeout): void
        {
        }
    }

    class FsUtils
    {
        public static function readFile(string $filename): string
        {
        }

        public static function writeFile(string $filename, string $data): int
        {
        }
    }
}
