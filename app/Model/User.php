<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;
use Qbhy\HyperfAuth\Authenticatable;

/**
 * 用户模型
 * 实现hyperf-auth的认证接口
 */
class User extends Model implements Authenticatable
{
    /**
     * 表名
     */
    protected ?string $table = 'users';

    /**
     * 主键
     */
    protected string $primaryKey = 'id';

    /**
     * 是否自动递增
     */
    public bool $incrementing = true;

    /**
     * 主键类型
     */
    protected string $keyType = 'int';

    /**
     * 时间戳
     */
    public bool $timestamps = true;

    /**
     * 可填充字段
     * @var string[]
     */
    protected array $fillable = [
        'username',
        'email',
        'password_hash',
        'real_name',
        'avatar',
        'bio',
        'role',
        'status',
    ];

    /**
     * 隐藏字段
     * @var string[]
     */
    protected array $hidden = [
        'password_hash',
    ];

    /**
     * 用于JWT认证的唯一标识字段
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * 获取用于JWT认证的唯一标识
     */
    public function getAuthIdentifier(): int
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * 获取用于验证密码的字段
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * 获取密码哈希用于认证
     */
    public function getAuthPassword(): string
    {
        return $this->{$this->getAuthPasswordName()};
    }

    /**
     * 设置密码属性，转换为密码哈希
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = password_hash($value, PASSWORD_DEFAULT);
    }
    
    /**
     * 获取用户ID
     */
    public function getId(): int
    {
        return $this->{$this->getAuthIdentifierName()};
    }
    
    /**
     * 根据键检索用户（符合hyperf-auth规范）
     */
    public static function retrieveById($key): ?\Qbhy\HyperfAuth\Authenticatable
    {
        return static::find($key);
    }
    
    /**
     * 验证密码是否正确
     */
    public function validatePassword(string $password): bool
    {
        return password_verify($password, $this->getAuthPassword());
    }
}