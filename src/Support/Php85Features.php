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
     * 使用管道执行命令（PHP 8.5+）
     *
     * @param string $command 命令
     * @param string|null $input 输入数据
     * @return array [stdout, stderr, exitCode]
     */
    public static function pipeExecute(string $command, ?string $input = null): array
    {
        if (self::supportsPipe()) {
            return self::executeWithNativePipe($command, $input);
        }
        
        return self::executeWithProcOpen($command, $input);
    }

    /**
     * 使用原生管道执行（PHP 8.5+）
     *
     * @param string $command 命令
     * @param string|null $input 输入数据
     * @return array
     */
    protected static function executeWithNativePipe(string $command, ?string $input = null): array
    {
        $stdout = '';
        $stderr = '';
        $exitCode = 0;
        
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
     * 使用 proc_open 执行（兼容模式）
     *
     * @param string $command 命令
     * @param string|null $input 输入数据
     * @return array
     */
    protected static function executeWithProcOpen(string $command, ?string $input = null): array
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
        
        curl_setopt_array($ch, array_merge($defaultOptions, $options));
        
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
