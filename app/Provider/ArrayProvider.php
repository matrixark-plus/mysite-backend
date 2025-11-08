<?php

declare(strict_types=1);

namespace App\Provider;

use App\Repository\UserRepository;
use Hyperf\Di\Annotation\Inject;
use Qbhy\HyperfAuth\Provider\AbstractUserProvider;
use Qbhy\HyperfAuth\Authenticatable;
use App\Model\User;

/**
 * 数组用户提供者
 * 处理Repository层返回的数组形式用户数据
 */
class ArrayProvider extends AbstractUserProvider
{
    /**
     * @Inject
     * @var UserRepository
     */
    protected $userRepository;
    
    /**
     * 模型类
     * @var string
     */
    protected $model;
    
    /**
     * 验证规则
     * @var array
     */
    protected $rules = [];
    
    /**
     * 构造函数
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        
        // 设置模型类
        if (isset($options['model'])) {
            $this->model = $options['model'];
        }
        
        // 设置验证规则
        if (isset($options['rules'])) {
            $this->rules = $options['rules'];
        }
    }
    
    /**
     * 根据ID获取用户
     * @param mixed $id
     * @return Authenticatable|null
     */
    public function retrieveById($id): ?Authenticatable
    {
        $userData = $this->userRepository->findById((int) $id);
        return $this->createUserInstance($userData);
    }
    
    /**
     * 根据凭据获取用户
     * @param array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        // 移除密码字段，避免在查询中使用
        $query = $credentials;
        unset($query['password']);
        
        // 根据凭据查询用户
        $userData = $this->userRepository->findBy($query);
        return $this->createUserInstance($userData);
    }
    
    /**
     * 验证用户凭据
     * @param Authenticatable $user 用户对象
     * @param array $credentials 凭据
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        // 获取用户密码哈希
        $passwordHash = $user->getAuthPassword();
        
        // 检查密码是否匹配
        if (empty($passwordHash) || !isset($credentials['password'])) {
            return false;
        }
        
        return password_verify($credentials['password'], $passwordHash);
    }
    
    /**
     * 创建用户实例
     * @param array|null $userData 用户数据数组
     * @return Authenticatable|null
     */
    protected function createUserInstance(?array $userData): ?Authenticatable
    {
        if (empty($userData) || !isset($userData['id'])) {
            return null;
        }
        
        // 如果设置了模型类，使用模型类创建实例
        if ($this->model && class_exists($this->model)) {
            $model = new $this->model();
            // 填充数据
            foreach ($userData as $key => $value) {
                $model->$key = $value;
            }
            return $model;
        }
        
        // 否则返回一个简单的Authenticatable实现
        return new class($userData) implements Authenticatable {
            protected $data;
            
            public function __construct(array $data)
            {
                $this->data = $data;
            }
            
            public function getAuthIdentifier()
            {
                return $this->data['id'] ?? null;
            }
            
            public function getAuthIdentifierName()
            {
                return 'id';
            }
            
            public function getAuthPassword()
            {
                return $this->data['password_hash'] ?? null;
            }
            
            public function getRememberToken()
            {
                return $this->data['remember_token'] ?? null;
            }
            
            public function setRememberToken($value)
            {
                $this->data['remember_token'] = $value;
            }
            
            public function getRememberTokenName()
            {
                return 'remember_token';
            }
            
            // 提供数组访问接口，兼容原有的数组访问方式
            public function __get($name)
            {
                return $this->data[$name] ?? null;
            }
            
            public function __set($name, $value)
            {
                $this->data[$name] = $value;
            }
            
            public function __isset($name)
            {
                return isset($this->data[$name]);
            }
        };
    }
}