<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

/**
 * PHP 8.5+ 特性支持
 *
 * 根据 PHP 版本自动选择最佳实现方式。
 */
class Php85Features
{
    /**
     * 检查是否支持 PHP 8.5+ 特性
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return PHP_VERSION_ID >= 80500;
    }

    /**
     * 检查是否支持管道操作
     *
     * @return bool
     */
    public static function supportsPipe(): bool
    {
        return PHP_VERSION_ID >= 80500 && function_exists('pipe');
    }

    /**
     * 检查是否支持增强的 curl
     *
     * @return bool
     */
    public static function supportsEnhancedCurl(): bool
    {
        return PHP_VERSION_ID >= 80500 && extension_loaded('curl');
    }

    /**
     * 获取可用的特性列表
     *
     * @return array
     */
    public static function getAvailableFeatures(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_version_id' => PHP_VERSION_ID,
            'is_php85' => self::isSupported(),
            'pipe_support' => self::supportsPipe(),
            'enhanced_curl' => self::supportsEnhancedCurl(),
            'fiber_local' => method_exists(\Fiber::class, 'getLocal'),
            'destruct_suspend' => PHP_VERSION_ID >= 80400,
        ];
    }

    /**
     * 执行命令并捕获输出（兼容 PHP 8.5 前后的所有版本）
     *
     * 以数组形式传入命令与参数（命令为第 0 项，参数为后续项），
     * 直接交由 proc_open 而**不经过 shell**，从根上杜绝命令注入。
     *
     * @param string $command 可执行文件（或命令）路径
     * @param string[] $args 参数列表（不会经由 shell 解析）
     * @param string|null $input 通过 stdin 传入的数据
     * @return array [stdout, stderr, exitCode]
     */
    public static function pipeExecute(string $command, array $args = [], ?string $input = null): array
    {
        $spec = array_merge([$command], array_values($args));

        return self::executeCommand($spec, $input);
    }

    /**
     * 通过 proc_open 执行（数组命令形式，无 shell）
     *
     * @param string[] $command
     * @param string|null $input
     * @return array [stdout, stderr, exitCode]
     */
    protected static function executeCommand(array $command, ?string $input = null): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return ['', 'Failed to create process', -1];
        }

        if ($input !== null) {
            fwrite($pipes[0], $input);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$stdout, $stderr, $exitCode];
    }

    /**
     * 创建优化的 cURL 句柄（PHP 8.5+ 增强版）
     *
     * @param string $url URL
     * @param array $options 选项
     * @return \CurlHandle
     */
    public static function createCurlHandle(string $url, array $options = []): \CurlHandle
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('无法初始化 cURL 句柄: ' . $url);
        }

        $defaultOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        if (self::isSupported()) {
            $defaultOptions[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
        }

        // 注意：CURLOPT_* 是整数键，array_merge 会重新编号导致选项全部错乱，
        // 必须使用 `+` 运算符保留键（左侧优先，即调用方选项覆盖默认值）。
        curl_setopt_array($ch, $options + $defaultOptions);

        return $ch;
    }

    /**
     * 执行并发 cURL 请求
     *
     * @param array $urls URL列表
     * @param array $commonOptions 公共选项
     * @return array
     */
    public static function multiCurl(array $urls, array $commonOptions = []): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];
        
        foreach ($urls as $key => $url) {
            $ch = self::createCurlHandle($url, $commonOptions);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }
        
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);
        
        foreach ($handles as $key => $ch) {
            $results[$key] = [
                'body' => curl_multi_getcontent($ch),
                'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                'error' => curl_error($ch),
                'errno' => curl_errno($ch),
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($mh);
        
        return $results;
    }
}
