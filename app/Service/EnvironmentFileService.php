<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Service;

use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 环境变量文件操作服务
 * 负责.env文件的读取和写入操作.
 */
class EnvironmentFileService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * .env文件路径.
     * @var string
     */
    protected $envFilePath;

    /**
     * 构造函数.
     */
    public function __construct()
    {
        // 获取.env文件路径
        $this->envFilePath = BASE_PATH . '/.env';
    }

    /**
     * 读取环境变量文件内容.
     * @return array 环境变量数组
     */
    public function readEnvFile(): array
    {
        try {
            if (! file_exists($this->envFilePath)) {
                $this->logger->warning('环境变量文件不存在: ' . $this->envFilePath);
                return [];
            }

            $lines = file($this->envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $envVars = [];

            foreach ($lines as $line) {
                // 跳过注释行
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                // 解析环境变量
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // 移除引号
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"')
                        || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }

                    $envVars[$key] = $value;
                }
            }

            return $envVars;
        } catch (Exception $e) {
            $this->logger->error('读取环境变量文件失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 更新环境变量.
     * @param string $key 环境变量键
     * @param mixed $value 环境变量值
     * @return bool 是否成功
     */
    public function updateEnvVar(string $key, $value): bool
    {
        try {
            // 读取现有环境变量
            $envVars = $this->readEnvFile();

            // 更新或添加环境变量
            $envVars[$key] = (string) $value;

            // 写回文件
            return $this->writeEnvFile($envVars);
        } catch (Exception $e) {
            $this->logger->error('更新环境变量失败: ' . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    /**
     * 批量更新环境变量.
     * @param array $envVars 环境变量数组
     * @return bool 是否成功
     */
    public function batchUpdateEnvVars(array $envVars): bool
    {
        try {
            // 读取现有环境变量
            $existingVars = $this->readEnvFile();

            // 合并新的环境变量
            $mergedVars = array_merge($existingVars, $envVars);

            // 写回文件
            return $this->writeEnvFile($mergedVars);
        } catch (Exception $e) {
            $this->logger->error('批量更新环境变量失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取环境变量.
     * @param string $key 环境变量键
     * @param mixed $default 默认值
     * @return mixed 环境变量值
     */
    public function getEnvVar(string $key, $default = null)
    {
        $envVars = $this->readEnvFile();
        return $envVars[$key] ?? $default;
    }

    /**
     * 将环境变量写入文件.
     * @param array $envVars 环境变量数组
     * @return bool 是否成功
     */
    protected function writeEnvFile(array $envVars): bool
    {
        try {
            // 确保目录存在
            $dir = dirname($this->envFilePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $content = '';
            foreach ($envVars as $key => $value) {
                // 对包含特殊字符的值添加引号
                if (strpos($value, ' ') !== false || strpos($value, '#') !== false
                    || strpos($value, '=') !== false || strpos($value, '\n') !== false) {
                    $value = '"' . str_replace('"', '\"', $value) . '"';
                }
                $content .= "{$key}={$value}\n";
            }

            // 写入文件
            $result = file_put_contents($this->envFilePath, $content);

            if ($result === false) {
                throw new Exception('写入文件失败');
            }

            $this->logger->info('环境变量文件已成功更新');
            return true;
        } catch (Exception $e) {
            $this->logger->error('写入环境变量文件失败: ' . $e->getMessage());
            return false;
        }
    }
}
