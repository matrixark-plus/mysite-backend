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

namespace App\Repository;

use App\Model\SystemConfig;
use Carbon\Carbon;
use Exception;
use Hyperf\Database\Model\Collection;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 系统配置数据访问层
 * 封装所有与系统配置相关的数据库操作.
 */
class SystemConfigRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据key获取系统配置
     *
     * @param string $key 配置键
     * @return mixed 配置值
     */
    public function findByKey(string $key): mixed
    {
        try {
            $config = SystemConfig::where('key', $key)->first();
            if ($config) {
                return $config->getValue();
            }
            return null;
        } catch (Exception $e) {
            $this->logger->error('根据key获取系统配置失败: ' . $e->getMessage(), ['key' => $key]);
            return null;
        }
    }

    /**
     * 获取所有系统配置
     *
     * @return array<string, mixed> 配置数组，key为配置键，value为配置值
     */
    public function findAll(): array
    {
        try {
            $configs = SystemConfig::all();
            $result = [];
            foreach ($configs as $config) {
                $result[$config->key] = $config->getValue();
            }
            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取所有系统配置失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 更新或插入系统配置
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return bool 是否成功
     */
    public function updateOrCreate(string $key, mixed $value): bool
    {
        try {
            // 序列化配置值
            $valueStr = json_encode($value);
            
            $result = SystemConfig::updateOrCreate(
                ['key' => $key],
                ['value' => $valueStr, 'updated_at' => Carbon::now()->toDateTimeString()]
            );
            
            return !empty($result);
        } catch (Exception $e) {
            $this->logger->error('更新或插入系统配置失败: ' . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    /**
     * 批量更新系统配置
     *
     * @param array<string, mixed> $configs 配置数组，key为配置键，value为配置值
     * @return bool 是否成功
     */
    public function batchUpdate(array $configs): bool
    {
        try {
            return Db::transaction(function () use ($configs) {
                foreach ($configs as $key => $value) {
                    if (!$this->updateOrCreate($key, $value)) {
                        return false;
                    }
                }
                return true;
            });
        } catch (Exception $e) {
            $this->logger->error('批量更新系统配置失败: ' . $e->getMessage());
            return false;
        }
    }
}