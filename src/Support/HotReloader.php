<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

/**
 * 热重载支持
 *
 * 提供不中断服务的代码更新能力，支持文件监控和自动重载。
 */
class HotReloader
{
    /**
     * 监控的目录列表
     */
    protected array $watchDirs = [];

    /**
     * 文件修改时间缓存
     */
    protected array $fileMtimes = [];

    /**
     * 是否正在运行
     */
    protected bool $running = false;

    /**
     * 重载回调列表
     */
    protected array $reloadCallbacks = [];

    /**
     * 排除的目录模式
     */
    protected array $excludePatterns = [
        '/vendor/',
        '/node_modules/',
        '/.git/',
        '/storage/',
        '/cache/',
    ];

    /**
     * 检查间隔（毫秒）
     */
    protected int $intervalMs = 1000;

    /**
     * 创建热重载器实例
     *
     * @param array $watchDirs 监控的目录
     * @param array $options 配置选项
     */
    public function __construct(array $watchDirs = [], array $options = [])
    {
        $this->watchDirs = $watchDirs;
        
        if (isset($options['exclude_patterns'])) {
            $this->excludePatterns = array_merge($this->excludePatterns, $options['exclude_patterns']);
        }
        
        if (isset($options['interval_ms'])) {
            $this->intervalMs = (int) $options['interval_ms'];
        }
    }

    /**
     * 添加监控目录
     *
     * @param string $dir 目录路径
     * @return self
     */
    public function addWatchDir(string $dir): self
    {
        if (is_dir($dir) && !in_array($dir, $this->watchDirs, true)) {
            $this->watchDirs[] = $dir;
        }
        
        return $this;
    }

    /**
     * 注册重载回调
     *
     * @param callable $callback 重载时执行的回调
     * @return self
     */
    public function onReload(callable $callback): self
    {
        $this->reloadCallbacks[] = $callback;
        return $this;
    }

    /**
     * 启动热重载监控
     *
     * @param bool $blocking 是否阻塞运行
     * @return void
     */
    public function start(bool $blocking = true): void
    {
        $this->running = true;
        $this->scanFiles();

        if ($blocking) {
            $this->runBlocking();
        }
    }

    /**
     * 停止热重载监控
     *
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 检查是否有文件变更
     *
     * @return array 变更的文件列表
     */
    public function checkChanges(): array
    {
        $changes = [];
        
        foreach ($this->watchDirs as $dir) {
            $changes = array_merge($changes, $this->scanDirectory($dir));
        }
        
        return $changes;
    }

    /**
     * 执行重载
     *
     * @param array $changedFiles 变更的文件列表
     * @return void
     */
    public function reload(array $changedFiles = []): void
    {
        foreach ($changedFiles as $file) {
            if (str_ends_with($file, '.php')) {
                $this->reloadPhpFile($file);
            }
        }
        
        foreach ($this->reloadCallbacks as $callback) {
            $callback($changedFiles);
        }
    }

    /**
     * 获取监控状态
     *
     * @return array
     */
    public function getStatus(): array
    {
        return [
            'running' => $this->running,
            'watch_dirs' => $this->watchDirs,
            'file_count' => count($this->fileMtimes),
            'interval_ms' => $this->intervalMs,
        ];
    }

    /**
     * 扫描所有文件并记录修改时间
     *
     * @return void
     */
    protected function scanFiles(): void
    {
        foreach ($this->watchDirs as $dir) {
            $this->scanDirectory($dir, true);
        }
    }

    /**
     * 扫描目录
     *
     * @param string $dir 目录路径
     * @param bool $init 是否初始化扫描
     * @return array 变更的文件列表
     */
    protected function scanDirectory(string $dir, bool $init = false): array
    {
        $changes = [];
        
        if (!is_dir($dir)) {
            return $changes;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $path = $file->getRealPath();
            
            if ($this->shouldExclude($path)) {
                continue;
            }
            
            $mtime = $file->getMTime();
            
            if ($init) {
                $this->fileMtimes[$path] = $mtime;
                continue;
            }
            
            if (!isset($this->fileMtimes[$path])) {
                $this->fileMtimes[$path] = $mtime;
                $changes[] = ['file' => $path, 'type' => 'added'];
            } elseif ($this->fileMtimes[$path] < $mtime) {
                $this->fileMtimes[$path] = $mtime;
                $changes[] = ['file' => $path, 'type' => 'modified'];
            }
        }
        
        return $changes;
    }

    /**
     * 检查文件是否应该被排除
     *
     * @param string $path 文件路径
     * @return bool
     */
    protected function shouldExclude(string $path): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 重载 PHP 文件
     *
     * @param string $file 文件路径
     * @return bool
     */
    protected function reloadPhpFile(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }
        
        $opcacheEnabled = function_exists('opcache_invalidate');
        
        if ($opcacheEnabled) {
            opcache_invalidate($file, true);
        }
        
        return true;
    }

    /**
     * 阻塞式运行
     *
     * @return void
     */
    protected function runBlocking(): void
    {
        while ($this->running) {
            usleep($this->intervalMs * 1000);
            
            $changes = $this->checkChanges();
            
            if (!empty($changes)) {
                $files = array_column($changes, 'file');
                $this->reload($files);
            }
        }
    }

    /**
     * 创建热重载器实例
     *
     * @param array $watchDirs 监控的目录
     * @param array $options 配置选项
     * @return self
     */
    public static function make(array $watchDirs = [], array $options = []): self
    {
        return new self($watchDirs, $options);
    }
}
