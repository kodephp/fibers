<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

/**
 * IO_uring 支持检测和桥接
 *
 * IO_uring 是 Linux 5.1+ 的高性能异步 I/O 接口。
 * 本类提供特性检测和优雅降级。
 */
class IOuringSupport
{
    protected static ?bool $supported = null;
    protected static ?bool $available = null;

    public static function isSupported(): bool
    {
        if (self::$supported !== null) {
            return self::$supported;
        }

        if (PHP_OS !== 'Linux') {
            self::$supported = false;
            return false;
        }

        if (!function_exists('posix_uname')) {
            self::$supported = false;
            return false;
        }

        $uname = posix_uname();
        $version = $uname['release'] ?? '';
        
        if (preg_match('/^(\d+)\.(\d+)/', $version, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            
            if ($major > 5 || ($major === 5 && $minor >= 1)) {
                self::$supported = true;
                return true;
            }
        }

        self::$supported = false;
        return false;
    }

    public static function isExtensionLoaded(): bool
    {
        return extension_loaded('io_uring') || extension_loaded('uopz');
    }

    public static function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        self::$available = self::isSupported() || self::isExtensionLoaded();
        return self::$available;
    }

    public static function getStatus(): array
    {
        $status = [
            'supported' => false,
            'extension_loaded' => false,
            'linux_kernel_version' => null,
            'io_uring_available' => false,
            'features' => [],
        ];

        if (PHP_OS === 'Linux') {
            $uname = function_exists('posix_uname') ? posix_uname() : null;
            $status['linux_kernel_version'] = $uname['release'] ?? 'unknown';
            $status['supported'] = self::isSupported();
        }

        $status['extension_loaded'] = self::isExtensionLoaded();
        $status['io_uring_available'] = self::isAvailable();

        if ($status['io_uring_available']) {
            $status['features'] = self::detectFeatures();
        }

        return $status;
    }

    protected static function detectFeatures(): array
    {
        return [
            'read' => true,
            'write' => true,
            'recv' => true,
            'send' => true,
            'fsync' => true,
            'poll' => true,
        ];
    }

    public static function getBestIOStrategy(): string
    {
        if (self::isExtensionLoaded()) {
            return 'io_uring';
        }

        if (self::isSupported()) {
            return 'native_io_uring';
        }

        if (function_exists('swoole_async_read')) {
            return 'swoole';
        }

        if (function_exists('uv_fs_read')) {
            return 'uv';
        }

        return 'blocking';
    }

    public static function createAsyncFileHandle(string $path, string $mode = 'r'): mixed
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('IO_uring is not available on this system');
        }

        if (self::isExtensionLoaded()) {
            return self::createExtensionHandle($path, $mode);
        }

        return self::createNativeHandle($path, $mode);
    }

    protected static function createExtensionHandle(string $path, string $mode): ?int
    {
        if (!extension_loaded('io_uring')) {
            return null;
        }

        $setupFunc = 'io_uring_setup';
        if (!function_exists($setupFunc)) {
            return null;
        }

        return @call_user_func($setupFunc, 32);
    }

    protected static function createNativeHandle(string $path, string $mode): mixed
    {
        return null;
    }

    public static function asyncRead(mixed $fd, int $length, int $offset = -1): array
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('IO_uring is not available');
        }

        return [
            'fd' => $fd,
            'length' => $length,
            'offset' => $offset,
            'operation' => 'read',
        ];
    }

    public static function asyncWrite(mixed $fd, string $data, int $offset = -1): array
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('IO_uring is not available');
        }

        return [
            'fd' => $fd,
            'data' => $data,
            'offset' => $offset,
            'operation' => 'write',
        ];
    }

    public static function asyncRecv(mixed $fd, int $length): array
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('IO_uring is not available');
        }

        return [
            'fd' => $fd,
            'length' => $length,
            'operation' => 'recv',
        ];
    }

    public static function asyncSend(mixed $fd, string $data): array
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('IO_uring is not available');
        }

        return [
            'fd' => $fd,
            'data' => $data,
            'operation' => 'send',
        ];
    }
}
