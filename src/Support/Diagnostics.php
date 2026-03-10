<?php

namespace Kode\Fibers\Support;

use Kode\Fibers\Exceptions\FiberException;

/**
 * 环境诊断工具，用于检查PHP版本兼容性、禁用函数、运行时环境等
 */
class Diagnostics
{
    /**
     * 最小支持的PHP版本
     */
    public const MIN_PHP_VERSION = '8.1.0';

    /**
     * 建议的PHP版本
     */
    public const RECOMMENDED_PHP_VERSION = '8.3.0';

    /**
     * 与纤程不兼容的函数列表
     */
    private const INCOMPATIBLE_FUNCTIONS = [
        'pcntl_fork', 'pcntl_wait', 'pcntl_waitpid', 'pcntl_wifexited',
        'pcntl_wifstopped', 'pcntl_wifsignaled', 'pcntl_wexitstatus',
        'pcntl_wtermsig', 'pcntl_wstopsig', 'pcntl_signal', 'pcntl_signal_dispatch',
        'pcntl_signal_get_handler', 'pcntl_sigprocmask', 'pcntl_sigwaitinfo',
        'pcntl_sigtimedwait', 'pcntl_exec', 'pcntl_get_last_error',
        'pcntl_strerror', 'pcntl_setpriority', 'pcntl_alarm', 'set_time_limit'
    ];

    /**
     * 建议启用的扩展
     */
    private const RECOMMENDED_EXTENSIONS = [
        'mbstring', 'json', 'curl', 'pdo', 'openssl', 'zlib'
    ];

    /**
     * 理想的性能相关ini设置
     */
    private const OPTIMAL_INI_SETTINGS = [
        'memory_limit' => '>= 256M',
        'max_execution_time' => '>= 30',
        'max_input_time' => '>= 60',
        'post_max_size' => '>= 8M',
        'upload_max_filesize' => '>= 2M',
        'realpath_cache_size' => '>= 4M',
        'opcache.enable' => '1',
        'opcache.memory_consumption' => '>= 128',
        'opcache.max_accelerated_files' => '>= 10000',
    ];

    /**
     * 运行完整的环境诊断
     *
     * @return array 诊断结果数组
     */
    public static function runFullDiagnostics(): array
    {
        $results = [
            'php_version' => self::checkPhpVersion(),
            'disabled_functions' => self::checkDisabledFunctions(),
            'runtime_environment' => self::checkRuntimeEnvironment(),
            'extensions' => self::checkExtensions(),
            'resource_limits' => self::checkResourceLimits(),
            'performance_config' => self::checkPerformanceConfig(),
            'fiber_support' => self::checkFiberSupport(),
            'overall_status' => self::determineOverallStatus(
                self::checkPhpVersion(),
                self::checkDisabledFunctions(),
                self::checkRuntimeEnvironment(),
                self::checkFiberSupport()
            )
        ];

        return $results;
    }

    /**
     * 检查PHP版本
     *
     * @return array 版本检查结果
     */
    public static function checkPhpVersion(): array
    {
        $currentVersion = PHP_VERSION;
        $minVersion = self::MIN_PHP_VERSION;
        $recommendedVersion = self::RECOMMENDED_PHP_VERSION;
        
        $isCompatible = version_compare($currentVersion, $minVersion, '>=');
        $isRecommended = version_compare($currentVersion, $recommendedVersion, '>=');
        
        return [
            'current' => $currentVersion,
            'minimum' => $minVersion,
            'recommended' => $recommendedVersion,
            'is_compatible' => $isCompatible,
            'is_recommended' => $isRecommended,
            'message' => $isCompatible ? 
                ($isRecommended ? 'PHP版本满足推荐要求' : 'PHP版本满足最低要求，但建议升级到更高版本') :
                'PHP版本不满足要求，请升级到PHP ' . $minVersion . ' 或更高版本',
            'severity' => $isCompatible ? ($isRecommended ? 'info' : 'warning') : 'critical'
        ];
    }

    /**
     * 检查禁用函数
     *
     * @return array 禁用函数检查结果
     */
    public static function checkDisabledFunctions(): array
    {
        $disabledFunctionsString = ini_get('disable_functions');
        $disabledFunctions = $disabledFunctionsString ? explode(',', $disabledFunctionsString) : [];
        $disabledFunctions = array_map('trim', $disabledFunctions);
        
        $incompatibleDisabledFunctions = array_intersect($disabledFunctions, self::INCOMPATIBLE_FUNCTIONS);
        
        $criticalFunctions = ['set_time_limit'];
        $hasCriticalDisabledFunctions = array_intersect($criticalFunctions, $incompatibleDisabledFunctions);
        
        return [
            'disabled_functions' => $disabledFunctions,
            'incompatible_disabled_functions' => array_values($incompatibleDisabledFunctions),
            'has_incompatible_functions' => count($incompatibleDisabledFunctions) > 0,
            'has_critical_issues' => count($hasCriticalDisabledFunctions) > 0,
            'message' => count($incompatibleDisabledFunctions) > 0 ?
                ('检测到 ' . count($incompatibleDisabledFunctions) . ' 个与纤程不兼容的禁用函数') :
                '未检测到与纤程不兼容的禁用函数',
            'severity' => count($hasCriticalDisabledFunctions) > 0 ? 'critical' : 
                (count($incompatibleDisabledFunctions) > 0 ? 'warning' : 'info')
        ];
    }

    /**
     * 检查运行时环境
     *
     * @return array 运行时环境检查结果
     */
    public static function checkRuntimeEnvironment(): array
    {
        $issues = [];
        
        // 检查线程安全模式
        $isThreadSafe = PHP_ZTS === 1;
        if ($isThreadSafe) {
            $issues[] = [
                'type' => 'thread_safety',
                'message' => 'PHP以线程安全模式运行，这可能会影响纤程性能',
                'severity' => 'warning'
            ];
        }
        
        // 检查是否在CLI模式
        $isCli = PHP_SAPI === 'cli';
        if (!$isCli) {
            $issues[] = [
                'type' => 'not_cli',
                'message' => 'PHP在非CLI模式下运行，纤程性能可能会受到影响',
                'severity' => 'warning'
            ];
        }
        
        // 检查最大嵌套纤程数
        $maxNestedFibers = ini_get('fibers.max_nested_fibers') ?: 'unlimited';
        if (is_numeric($maxNestedFibers) && $maxNestedFibers < 100) {
            $issues[] = [
                'type' => 'low_max_nested_fibers',
                'message' => '最大嵌套纤程数设置较低，可能会限制复杂应用程序',
                'severity' => 'warning'
            ];
        }
        
        // 检查是否启用了xdebug（开发工具，生产环境应禁用）
        if (extension_loaded('xdebug')) {
            $issues[] = [
                'type' => 'xdebug_enabled',
                'message' => 'Xdebug已启用，这会严重影响纤程性能',
                'severity' => 'warning'
            ];
        }
        
        // 检查析构函数限制（PHP 8.4之前）
        $hasDestructorRestriction = PHP_VERSION_ID < 80400;
        if ($hasDestructorRestriction) {
            $issues[] = [
                'type' => 'destructor_restriction',
                'message' => 'PHP 8.4之前的版本不允许在析构函数中切换纤程',
                'severity' => 'info'
            ];
        }
        
        $hasCriticalIssues = count(array_filter($issues, fn($i) => $i['severity'] === 'critical')) > 0;
        $hasWarnings = count(array_filter($issues, fn($i) => $i['severity'] === 'warning')) > 0;
        
        return [
            'issues' => $issues,
            'has_critical_issues' => $hasCriticalIssues,
            'has_warnings' => $hasWarnings,
            'is_cli' => $isCli,
            'is_thread_safe' => $isThreadSafe,
            'max_nested_fibers' => $maxNestedFibers,
            'message' => count($issues) > 0 ?
                ('检测到 ' . count($issues) . ' 个运行时环境问题') :
                '运行时环境检查通过',
            'severity' => $hasCriticalIssues ? 'critical' : ($hasWarnings ? 'warning' : 'info')
        ];
    }

    /**
     * 检查已加载的扩展
     *
     * @return array 扩展检查结果
     */
    public static function checkExtensions(): array
    {
        $loadedExtensions = get_loaded_extensions();
        $missingRecommendedExtensions = array_diff(self::RECOMMENDED_EXTENSIONS, $loadedExtensions);
        
        // 检查Fiber扩展
        $hasFiberSupport = extension_loaded('fibers') || class_exists('Fiber');
        
        return [
            'loaded_extensions' => $loadedExtensions,
            'missing_recommended_extensions' => array_values($missingRecommendedExtensions),
            'has_fiber_support' => $hasFiberSupport,
            'has_missing_recommended' => count($missingRecommendedExtensions) > 0,
            'message' => $hasFiberSupport ?
                (count($missingRecommendedExtensions) > 0 ?
                    ('纤程支持可用，但缺少 ' . count($missingRecommendedExtensions) . ' 个推荐的扩展') :
                    '纤程支持可用且所有推荐的扩展已加载') :
                '未检测到纤程支持，可能需要更新PHP版本或安装相关扩展',
            'severity' => $hasFiberSupport ?
                (count($missingRecommendedExtensions) > 0 ? 'warning' : 'info') :
                'critical'
        ];
    }

    /**
     * 检查系统资源限制
     *
     * @return array 资源限制检查结果
     */
    public static function checkResourceLimits(): array
    {
        $limits = [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_input_vars' => ini_get('max_input_vars'),
        ];
        
        $issues = [];
        
        // 检查内存限制
        $memoryLimit = self::convertToBytes($limits['memory_limit']);
        if ($memoryLimit > 0 && $memoryLimit < 256 * 1024 * 1024) {
            $issues[] = [
                'type' => 'low_memory_limit',
                'message' => '内存限制较低，建议至少设置为256M',
                'severity' => 'warning'
            ];
        }
        
        // 检查执行时间限制
        if ((int)$limits['max_execution_time'] > 0 && (int)$limits['max_execution_time'] < 30) {
            $issues[] = [
                'type' => 'low_max_execution_time',
                'message' => '最大执行时间限制较低，建议至少设置为30秒',
                'severity' => 'warning'
            ];
        }
        
        $hasIssues = count($issues) > 0;
        
        return [
            'limits' => $limits,
            'issues' => $issues,
            'has_issues' => $hasIssues,
            'message' => $hasIssues ?
                ('检测到 ' . count($issues) . ' 个资源限制问题') :
                '资源限制检查通过',
            'severity' => $hasIssues ? 'warning' : 'info'
        ];
    }

    /**
     * 检查性能相关配置
     *
     * @return array 性能配置检查结果
     */
    public static function checkPerformanceConfig(): array
    {
        $issues = [];
        
        // 检查OPcache
        $opcacheEnabled = ini_get('opcache.enable') === '1';
        if (!$opcacheEnabled) {
            $issues[] = [
                'type' => 'opcache_disabled',
                'message' => 'OPcache未启用，强烈建议在生产环境中启用',
                'severity' => 'warning'
            ];
        } else {
            // 检查OPcache配置
            $opcacheMemory = (int)ini_get('opcache.memory_consumption');
            if ($opcacheMemory < 128) {
                $issues[] = [
                    'type' => 'low_opcache_memory',
                    'message' => 'OPcache内存设置较低，建议至少设置为128M',
                    'severity' => 'warning'
                ];
            }
            
            $maxAcceleratedFiles = (int)ini_get('opcache.max_accelerated_files');
            if ($maxAcceleratedFiles < 10000) {
                $issues[] = [
                    'type' => 'low_max_accelerated_files',
                    'message' => 'OPcache最大加速文件数较低，建议至少设置为10000',
                    'severity' => 'warning'
                ];
            }
        }
        
        // 检查realpath_cache_size
        $realpathCacheSize = self::convertToBytes(ini_get('realpath_cache_size'));
        if ($realpathCacheSize < 4 * 1024 * 1024) {
            $issues[] = [
                'type' => 'low_realpath_cache_size',
                'message' => 'realpath缓存大小较低，建议至少设置为4M',
                'severity' => 'warning'
            ];
        }
        
        $hasIssues = count($issues) > 0;
        
        return [
            'opcache_enabled' => $opcacheEnabled,
            'issues' => $issues,
            'has_issues' => $hasIssues,
            'message' => $hasIssues ?
                ('检测到 ' . count($issues) . ' 个性能配置问题') :
                '性能配置检查通过',
            'severity' => $hasIssues ? 'warning' : 'info'
        ];
    }

    /**
     * 检查纤程支持
     *
     * @return array 纤程支持检查结果
     */
    public static function checkFiberSupport(): array
    {
        $hasNativeSupport = class_exists('Fiber');
        $hasComposerSupport = class_exists('Kode\Fibers\Fibers');
        
        // 尝试创建一个简单的纤程来测试
        $canCreateFiber = false;
        $testError = null;
        
        if ($hasNativeSupport) {
            try {
                $fiber = new \Fiber(function () {
                    return 'success';
                });
                $fiber->start();
                $canCreateFiber = true;
            } catch (\Throwable $e) {
                $testError = $e->getMessage();
            }
        }
        
        return [
            'has_native_support' => $hasNativeSupport,
            'has_composer_support' => $hasComposerSupport,
            'can_create_fiber' => $canCreateFiber,
            'test_error' => $testError,
            'message' => $canCreateFiber ?
                '纤程支持正常，可以创建和执行纤程' :
                ($hasNativeSupport ?
                    ('检测到纤程类，但创建纤程时出错：' . $testError) :
                    '未检测到原生纤程支持，需要PHP 8.1或更高版本'),
            'severity' => $canCreateFiber ? 'info' : 'critical'
        ];
    }

    /**
     * 生成HTML格式的诊断报告
     *
     * @param array $diagnostics 诊断结果
     * @param string $outputPath 输出路径
     * @return bool 是否生成成功
     */
    public static function generateHtmlReport(array $diagnostics, string $outputPath): bool
    {
        $html = self::generateHtmlReportContent($diagnostics);
        
        try {
            // 确保目录存在
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            return file_put_contents($outputPath, $html) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 生成HTML报告内容
     *
     * @param array $diagnostics 诊断结果
     * @return string HTML内容
     */
    private static function generateHtmlReportContent(array $diagnostics): string
    {
        $severityClass = [
            'critical' => 'bg-red-100 text-red-800',
            'warning' => 'bg-yellow-100 text-yellow-800',
            'info' => 'bg-green-100 text-green-800'
        ];
        
        $overallStatusClass = $severityClass[$diagnostics['overall_status']['severity']] ?? 'bg-gray-100 text-gray-800';
        
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode/Fibers 环境诊断报告</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; margin: 0; padding: 20px; background-color: #f9fafb; }
        .container { max-width: 1200px; margin: 0 auto; background-color: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .header { padding: 24px; background-color: #165DFF; color: white; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 500; }
        .header p { margin: 8px 0 0; opacity: 0.9; }
        .content { padding: 24px; }
        .section { margin-bottom: 32px; }
        .section h2 { margin: 0 0 16px; font-size: 20px; font-weight: 500; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 14px; font-weight: 500; }
        .card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .card-title { font-size: 16px; font-weight: 500; margin: 0; color: #374151; }
        .card-content { color: #4b5563; font-size: 14px; }
        .list { margin: 0; padding: 0; list-style: none; }
        .list li { padding: 4px 0; border-bottom: 1px solid #f3f4f6; }
        .list li:last-child { border-bottom: none; }
        .key-value { display: flex; margin-bottom: 8px; }
        .key { width: 150px; font-weight: 500; color: #374151; }
        .value { flex: 1; color: #4b5563; }
        .issue { padding: 8px; margin: 8px 0; border-radius: 4px; font-size: 14px; }
        .footer { padding: 16px 24px; background-color: #f9fafb; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 14px; }
        .timestamp { font-size: 12px; color: #9ca3af; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Kode/Fibers 环境诊断报告</h1>
            <p>生成时间: <?php echo date('Y-m-d H:i:s'); ?></p>
            <div class="timestamp">PHP版本: <?php echo PHP_VERSION; ?></div>
        </div>
        
        <div class="content">
            <!-- 整体状态 -->
            <div class="section">
                <h2>整体状态</h2>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">环境兼容性评估</h3>
                        <span class="status-badge <?php echo $overallStatusClass; ?>">
                            <?php echo $diagnostics['overall_status']['message']; ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <p><?php echo $diagnostics['overall_status']['detail']; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- PHP版本 -->
            <div class="section">
                <h2>PHP版本</h2>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">版本信息</h3>
                        <span class="status-badge <?php echo $severityClass[$diagnostics['php_version']['severity']]; ?>">
                            <?php echo $diagnostics['php_version']['message']; ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <div class="key-value">
                            <div class="key">当前版本:</div>
                            <div class="value"><?php echo $diagnostics['php_version']['current']; ?></div>
                        </div>
                        <div class="key-value">
                            <div class="key">最低要求:</div>
                            <div class="value"><?php echo $diagnostics['php_version']['minimum']; ?></div>
                        </div>
                        <div class="key-value">
                            <div class="key">推荐版本:</div>
                            <div class="value"><?php echo $diagnostics['php_version']['recommended']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 纤程支持 -->
            <div class="section">
                <h2>纤程支持</h2>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">纤程功能检查</h3>
                        <span class="status-badge <?php echo $severityClass[$diagnostics['fiber_support']['severity']]; ?>">
                            <?php echo $diagnostics['fiber_support']['message']; ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <div class="key-value">
                            <div class="key">原生支持:</div>
                            <div class="value"><?php echo $diagnostics['fiber_support']['has_native_support'] ? '是' : '否'; ?></div>
                        </div>
                        <div class="key-value">
                            <div class="key">Composer支持:</div>
                            <div class="value"><?php echo $diagnostics['fiber_support']['has_composer_support'] ? '是' : '否'; ?></div>
                        </div>
                        <div class="key-value">
                            <div class="key">可创建纤程:</div>
                            <div class="value"><?php echo $diagnostics['fiber_support']['can_create_fiber'] ? '是' : '否'; ?></div>
                        </div>
                        <?php if ($diagnostics['fiber_support']['test_error']): ?>
                        <div class="key-value">
                            <div class="key">测试错误:</div>
                            <div class="value"><?php echo $diagnostics['fiber_support']['test_error']; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 禁用函数 -->
            <div class="section">
                <h2>禁用函数</h2>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">与纤程不兼容的禁用函数</h3>
                        <span class="status-badge <?php echo $severityClass[$diagnostics['disabled_functions']['severity']]; ?>">
                            <?php echo $diagnostics['disabled_functions']['message']; ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <?php if (count($diagnostics['disabled_functions']['incompatible_disabled_functions']) > 0): ?>
                        <ul class="list">
                            <?php foreach ($diagnostics['disabled_functions']['incompatible_disabled_functions'] as $func): ?>
                            <li><?php echo $func; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p>未检测到与纤程不兼容的禁用函数</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 运行时环境 -->
            <div class="section">
                <h2>运行时环境</h2>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">环境设置</h3>
                        <span class="status-badge <?php echo $severityClass[$diagnostics['runtime_environment']['severity']]; ?>">
                            <?php echo $diagnostics['runtime_environment']['message']; ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <div class="key-value">
                            <div class="key">CLI模式:</div>
                            <div class="value"><?php echo $diagnostics['runtime_environment']['is_cli'] ? '是' : '否'; ?></div>
                        </div>
                        <div class="key-value">
                            <div class="key">线程安全:</div>
                            <div class="value"><?php echo $diagnostics['runtime_environment']['is_thread_safe'] ? '是' : '否'; ?></div>
                        </div>
                        <div class="key-value">
                            <div class="key">最大嵌套纤程:</div>
                            <div class="value"><?php echo $diagnostics['runtime_environment']['max_nested_fibers']; ?></div>
                        </div>
                        
                        <?php if (count($diagnostics['runtime_environment']['issues']) > 0): ?>
                        <div style="margin-top: 16px;">
                            <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 500;">环境问题:</h4>
                            <?php foreach ($diagnostics['runtime_environment']['issues'] as $issue): ?>
                            <div class="issue <?php echo $severityClass[$issue['severity']]; ?>">
                                <?php echo $issue['message']; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 扩展 -->
            <div class="section">
                <h2>扩展</h2>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">推荐扩展</h3>
                        <span class="status-badge <?php echo $severityClass[$diagnostics['extensions']['severity']]; ?>">
                            <?php echo $diagnostics['extensions']['message']; ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <?php if (count($diagnostics['extensions']['missing_recommended_extensions']) > 0): ?>
                        <div style="margin-bottom: 12px;">
                            <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 500;">缺少的推荐扩展:</h4>
                            <ul class="list">
                                <?php foreach ($diagnostics['extensions']['missing_recommended_extensions'] as $ext): ?>
                                <li><?php echo $ext; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 资源限制 -->
            <div class="section">
                <h2>资源限制</h2>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">PHP资源限制</h3>
                        <span class="status-badge <?php echo $severityClass[$diagnostics['resource_limits']['severity']]; ?>">
                            <?php echo $diagnostics['resource_limits']['message']; ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <div class="key-value">
                            <div class="key">内存限制:</div>
                            <div class="value"><?php echo $diagnostics['resource_limits']['limits']['memory_limit']; ?></div>
                        </div>
                        <div class="key-value">
                            <div class="key">最大执行时间:</div>
                            <div class="value"><?php echo $diagnostics['resource_limits']['limits']['max_execution_time']; ?>秒</div>
                        </div>
                        <div class="key-value">
                            <div class="key">最大输入时间:</div>
                            <div class="value"><?php echo $diagnostics['resource_limits']['limits']['max_input_time']; ?>秒</div>
                        </div>
                        <div class="key-value">
                            <div class="key">POST最大大小:</div>
                            <div class="value"><?php echo $diagnostics['resource_limits']['limits']['post_max_size']; ?></div>
                        </div>
                        <div class="key-value">
                            <div class="key">上传最大文件:</div>
                            <div class="value"><?php echo $diagnostics['resource_limits']['limits']['upload_max_filesize']; ?></div>
                        </div>
                        
                        <?php if (count($diagnostics['resource_limits']['issues']) > 0): ?>
                        <div style="margin-top: 16px;">
                            <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 500;">资源问题:</h4>
                            <?php foreach ($diagnostics['resource_limits']['issues'] as $issue): ?>
                            <div class="issue <?php echo $severityClass[$issue['severity']]; ?>">
                                <?php echo $issue['message']; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 性能配置 -->
            <div class="section">
                <h2>性能配置</h2>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">性能优化设置</h3>
                        <span class="status-badge <?php echo $severityClass[$diagnostics['performance_config']['severity']]; ?>">
                            <?php echo $diagnostics['performance_config']['message']; ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <div class="key-value">
                            <div class="key">OPcache启用:</div>
                            <div class="value"><?php echo $diagnostics['performance_config']['opcache_enabled'] ? '是' : '否'; ?></div>
                        </div>
                        
                        <?php if (count($diagnostics['performance_config']['issues']) > 0): ?>
                        <div style="margin-top: 16px;">
                            <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 500;">性能建议:</h4>
                            <?php foreach ($diagnostics['performance_config']['issues'] as $issue): ?>
                            <div class="issue <?php echo $severityClass[$issue['severity']]; ?>">
                                <?php echo $issue['message']; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Kode/Fibers 环境诊断报告 - 帮助您优化纤程运行环境</p>
        </div>
    </div>
</body>
</html>
        <?php
        
        return ob_get_clean();
    }

    /**
     * 确定整体状态
     *
     * @param array ...$checkResults 各检查项的结果
     * @return array 整体状态
     */
    private static function determineOverallStatus(array ...$checkResults): array
    {
        $hasCriticalIssues = false;
        $hasWarnings = false;
        $messages = [];
        
        foreach ($checkResults as $result) {
            if ($result['severity'] === 'critical') {
                $hasCriticalIssues = true;
                $messages[] = $result['message'];
            } elseif ($result['severity'] === 'warning') {
                $hasWarnings = true;
                $messages[] = $result['message'];
            }
        }
        
        if ($hasCriticalIssues) {
            return [
                'status' => 'critical',
                'severity' => 'critical',
                'message' => '环境存在严重问题',
                'detail' => '检测到可能导致纤程无法正常工作的严重问题，建议立即修复。\n\n' . implode('\n', $messages)
            ];
        } elseif ($hasWarnings) {
            return [
                'status' => 'warning',
                'severity' => 'warning',
                'message' => '环境存在警告',
                'detail' => '纤程可以运行，但存在一些可能影响性能或稳定性的问题。\n\n' . implode('\n', $messages)
            ];
        } else {
            return [
                'status' => 'ok',
                'severity' => 'info',
                'message' => '环境检查通过',
                'detail' => '您的环境满足Kode/Fibers的所有要求，可以正常使用纤程功能。'
            ];
        }
    }

    /**
     * 将PHP.ini值转换为字节数
     *
     * @param string $val INI值
     * @return int 字节数
     */
    private static function convertToBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        
        $val = (int)$val;
        
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
}