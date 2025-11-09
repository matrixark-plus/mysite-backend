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

use Hyperf\DbConnection\Model\Model;
use Qbhy\HyperfAuth\Authenticatable;

/**
 * 用户模型
 * 实现hyperf-auth的认证接口.
 * 
 * @property int $id
 * @property string $email
 * @property string $password_hash
 * @property string $real_name
 * @property string $avatar
 * @property string $bio
 * @property bool $is_active
 * @property bool $is_admin
 * @property string $last_login_at
 * @property string $created_at
 * @property int $login_attempts
 * @property bool $is_locked
 * @property string $lock_expire_time
 * 
 * @method static static|\Hyperf\Database\Query\Builder where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static static|\Hyperf\Database\Query\Builder orderBy($column, $direction = 'asc')
 * @method static static|\Hyperf\Database\Query\Builder select($columns = ['*'])
 * @method static static|\Hyperf\Database\Query\Builder first($columns = ['*'])
 * @method static static|\Hyperf\Database\Query\Builder get($columns = ['*'])
 * @method static static|\Hyperf\Database\Query\Builder count($columns = '*')
 * @method static static|\Hyperf\Database\Query\Builder update(array $values)
 * @method static static|null find($id, $columns = ['*'])
 */
class User extends Model implements Authenticatable
{
    /**
     * 是否自动递增.
     */
    public bool $incrementing = true;

    /**
     * 时间戳. 设为false因为我们不需要updated_at字段
     */
    public bool $timestamps = false;

    /**
     * 表名.
     */
    protected ?string $table = 'users';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 主键类型.
     */
    protected string $keyType = 'int';

    /**
     * 可填充字段.
     * @var string[]
     */
    protected array $fillable = [
        'email',
        'password_hash',
        'real_name',
        'avatar',
        'bio',
        'is_active',
        'is_admin',
        'last_login_at',
        'last_login_ip',
        'login_attempts',
        'is_locked',
        'lock_expire_time',
    ];

    /**
     * 隐藏字段.
     * @var string[]
     */
    protected array $hidden = [
        'password_hash',
    ];

    /**
     * 用于JWT认证的唯一标识字段.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * 获取用于JWT认证的唯一标识.
     */
    public function getAuthIdentifier(): int
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * 获取用于验证密码的字段.
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
     * 设置密码属性，转换为密码哈希.
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 获取用户ID.
     */
    public function getId(): int
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * 根据键检索用户（符合hyperf-auth规范）.
     * @param mixed $key
     */
    public static function retrieveById($key): ?Authenticatable
    {
        return static::find($key);
    }

    /**
     * 验证密码是否正确.
     */
    public function validatePassword(string $password): bool
    {
        return password_verify($password, $this->getAuthPassword());
    }
}
