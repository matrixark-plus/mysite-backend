# 技术架构重构文档 - 数据访问层分离

## 3.1 数据访问层分离

### 3.1.1 创建UserRepository

创建了`App\Repository\UserRepository`类，封装所有与用户数据相关的数据库操作，实现了以下核心方法：

- `findById(int $id)`：根据ID查找用户
- `findBy(array $conditions)`：根据条件查找单个用户
- `findAllBy(array $conditions, array $likeConditions = [], array $orderBy = [], int $page = 1, int $pageSize = 20)`：条件查询并分页
- `count(array $conditions = [])`：统计用户数量
- `create(array $data)`：创建新用户
- `update(int $id, array $data)`：更新用户信息
- `updateRole(int $id, string $role)`：更新用户角色
- `getAdminCount()`：获取管理员数量

### 3.1.2 代码示例

```php
/**
 * 根据ID查找用户
 * 
 * @param int $id 用户ID
 * @return array|null 用户信息数组，不存在则返回null
 */
public function findById(int $id): ?array
{
    $user = User::find($id);
    return $user ? $user->toArray() : null;
}

/**
 * 条件查询用户列表并分页
 * 
 * @param array $conditions 等值查询条件
 * @param array $likeConditions 模糊查询条件
 * @param array $orderBy 排序条件
 * @param int $page 页码
 * @param int $pageSize 每页数量
 * @return array 分页数据
 */
public function findAllBy(array $conditions = [], array $likeConditions = [], array $orderBy = [], int $page = 1, int $pageSize = 20): array
{
    $query = User::query();
    
    // 处理等值查询条件
    foreach ($conditions as $field => $value) {
        $query->where($field, $value);
    }
    
    // 处理模糊查询条件
    if (!empty($likeConditions)) {
        $query->where(function ($q) use ($likeConditions) {
            foreach ($likeConditions as $condition) {
                $q->orWhere($condition['field'], 'like', $condition['value']);
            }
        });
    }
    
    // 处理排序
    foreach ($orderBy as $field => $direction) {
        $query->orderBy($field, $direction);
    }
    
    // 执行分页查询
    $result = $query->paginate($pageSize, ['*'], 'page', $page);
    
    return [
        'total' => $result->total(),
        'page' => $result->currentPage(),
        'page_size' => $result->perPage(),
        'data' => $result->items()
    ];
}
```

### 3.1.3 Repository层的优势

1. **关注点分离**：Repository专注于数据访问，业务逻辑在Service层处理
2. **代码复用**：相同的数据库操作可以在多个Service中复用
3. **易于测试**：可以通过Mock Repository来测试Service层
4. **解耦**：业务层不依赖于具体的数据库实现
5. **统一管理**：所有数据库相关的操作都集中在一处，便于维护

[返回主索引](./Architecture-Refactoring-Main.md)