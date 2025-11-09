<?php

declare(strict_types=1);

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;
use Psr\Container\ContainerInterface;

/**
 * 笔记管理相关的参数验证器.
 */
class NoteValidator
{
    /**
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 构造函数.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->validatorFactory = $container->get(ValidatorFactoryInterface::class);
    }

    /**
     * 验证笔记列表请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateNoteList(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'keyword' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新笔记请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateUpdateNote(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'is_public' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}