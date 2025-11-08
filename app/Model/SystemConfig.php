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

namespace App\Model;

/**
 * 系统配置模型.
 */
class SystemConfig extends Model
{
    /**
     * 状态常量.
     */
    public const STATUS_DISABLED = 0;  // 禁用

    public const STATUS_ENABLED = 1;   // 启用

    /**
     * 配置类型常量.
     */
    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_NUMBER = 'number';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_JSON = 'json';

    /**
     * 时间戳字段.
     */
    public bool $timestamps = true;

    /**
     * 表名.
     */
    protected ?string $table = 'system_configs';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段.
     */
    protected array $fillable = [
        'key',
        'value',
        'description',
        'type',
        'sort',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 获取配置值
     * 根据配置类型返回适当的数据类型.
     * @return mixed
     */
    public function getValue()
    {
        switch ($this->type) {
            case self::TYPE_NUMBER:
                return (int) $this->value;
            case self::TYPE_BOOLEAN:
                return (bool) $this->value;
            case self::TYPE_JSON:
                return json_decode($this->value, true) ?: [];
            default:
                return $this->value;
        }
    }

    /**
     * 设置配置值
     * 根据配置类型进行适当的格式化.
     * @param mixed $value 配置值
     */
    public function setValue($value)
    {
        switch ($this->type) {
            case self::TYPE_NUMBER:
                $this->value = (string) (int) $value;
                break;
            case self::TYPE_BOOLEAN:
                $this->value = $value ? '1' : '0';
                break;
            case self::TYPE_JSON:
                $this->value = json_encode($value, JSON_UNESCAPED_UNICODE);
                break;
            default:
                $this->value = (string) $value;
        }
    }
}
