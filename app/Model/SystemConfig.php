<?php

declare(strict_types=1);

namespace App\Model;

/**
 * 系统配置模型
 */
class SystemConfig extends Model
{
    /**
     * 表名
     * @var ?string
     */
    protected ?string $table = 'system_configs';

    /**
     * 主键
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用
    
    /**
     * 配置类型常量
     */
    const TYPE_STRING = 'string';
    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';

    /**
     * 可填充字段
     * @var array
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
     * 隐藏字段
     * @var array
     */
    protected array $hidden = [];

    /**
     * 时间戳字段
     * @var bool
     */
    public bool $timestamps = true;
    
    /**
     * 获取配置值
     * 根据配置类型返回适当的数据类型
     * @return mixed
     */
    public function getValue()
    {
        switch ($this->type) {
            case self::TYPE_NUMBER:
                return (int)$this->value;
            case self::TYPE_BOOLEAN:
                return (bool)$this->value;
            case self::TYPE_JSON:
                return json_decode($this->value, true) ?: [];
            default:
                return $this->value;
        }
    }
    
    /**
     * 设置配置值
     * 根据配置类型进行适当的格式化
     * @param mixed $value 配置值
     */
    public function setValue($value)
    {
        switch ($this->type) {
            case self::TYPE_NUMBER:
                $this->value = (string)(int)$value;
                break;
            case self::TYPE_BOOLEAN:
                $this->value = $value ? '1' : '0';
                break;
            case self::TYPE_JSON:
                $this->value = json_encode($value, JSON_UNESCAPED_UNICODE);
                break;
            default:
                $this->value = (string)$value;
        }
    }
}