<?php

declare(strict_types=1);

namespace Nova\Fibers\Profiler;

/**
 * Web Profiler面板
 *
 * 提供Fiber Profiler的可视化界面
 */
class WebProfilerPanel
{
    /**
     * 渲染Profiler面板
     *
     * @return string HTML内容
     */
    public static function render(): string
    {
        if (!FiberProfiler::isEnabled()) {
            return self::renderDisabled();
        }

        $stats = FiberProfiler::getStats();
        $report = FiberProfiler::getReport();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Fiber Profiler</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    background-color: #f5f5f5;
                }
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                    background-color: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #333;
                    border-bottom: 2px solid #007cba;
                    padding-bottom: 10px;
                }
                .summary {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .metric {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 4px;
                    text-align: center;
                }
                .metric .value {
                    font-size: 24px;
                    font-weight: bold;
                    color: #007cba;
                }
                .metric .label {
                    font-size: 14px;
                    color: #666;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                th, td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                }
                tr:hover {
                    background-color: #f5f5f5;
                }
                .status-completed {
                    color: #28a745;
                }
                .status-failed {
                    color: #dc3545;
                }
                .status-running {
                    color: #ffc107;
                }
                .duration {
                    font-family: monospace;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Fiber Profiler</h1>
                
                <div class="summary">
                    <div class="metric">
                        <div class="value"><?php echo $report['total_fibers']; ?></div>
                        <div class="label">Total Fibers</div>
                    </div>
                    <div class="metric">
                        <div class="value"><?php echo $report['completed_fibers']; ?></div>
                        <div class="label">Completed</div>
                    </div>
                    <div class="metric">
                        <div class="value"><?php echo $report['failed_fibers']; ?></div>
                        <div class="label">Failed</div>
                    </div>
                    <div class="metric">
                        <div class="value"><?php echo $report['running_fibers']; ?></div>
                        <div class="label">Running</div>
                    </div>
                </div>
                
                <div class="summary">
                    <div class="metric">
                        <div class="value"><?php echo number_format($report['average_duration'] * 1000, 2); ?>ms</div>
                        <div class="label">Avg Duration</div>
                    </div>
                    <div class="metric">
                        <div class="value"><?php echo number_format($report['max_duration'] * 1000, 2); ?>ms</div>
                        <div class="label">Max Duration</div>
                    </div>
                    <div class="metric">
                        <div class="value"><?php echo number_format($report['min_duration'] * 1000, 2); ?>ms</div>
                        <div class="label">Min Duration</div>
                    </div>
                    <div class="metric">
                        <div class="value"><?php echo number_format($report['total_duration'] * 1000, 2); ?>ms</div>
                        <div class="label">Total Duration</div>
                    </div>
                </div>
                
                <h2>Fiber Details</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Start Time</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $id => $stat) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars(substr($id, 0, 8)); ?></td>
                            <td><?php echo htmlspecialchars($stat['name']); ?></td>
                            <td class="status-<?php echo $stat['status']; ?>">
                            <?php echo ucfirst($stat['status']); ?>
                        </td>
                            <td><?php echo date('H:i:s', (int)$stat['start_time']); ?></td>
                            <td class="duration">
                                <?php if ($stat['duration'] !== null) : ?>
                                    <?php echo number_format($stat['duration'] * 1000, 2); ?>ms
                                <?php else : ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * 渲染禁用状态的面板
     *
     * @return string HTML内容
     */
    private static function renderDisabled(): string
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Fiber Profiler</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    background-color: #f5f5f5;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    text-align: center;
                }
                h1 {
                    color: #333;
                    margin-bottom: 20px;
                }
                p {
                    color: #666;
                    font-size: 16px;
                    line-height: 1.6;
                }
                .enable-btn {
                    background-color: #007cba;
                    color: white;
                    padding: 12px 24px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                    margin-top: 20px;
                }
                .enable-btn:hover {
                    background-color: #005a87;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Fiber Profiler</h1>
                <p>The Fiber Profiler is currently disabled.</p>
                <p>To enable profiling, call <code>FiberProfiler::enable()</code> in your application.</p>
                <button class="enable-btn" onclick="enableProfiler()">Enable Profiler</button>
                
                <script>
                    function enableProfiler() {
                        // In a real implementation, this would make an AJAX request to enable the profiler
                        alert('Profiler enabled! Refresh the page to see profiling data.');
                        // For demonstration purposes, we'll just reload the page
                        window.location.reload();
                    }
                </script>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}