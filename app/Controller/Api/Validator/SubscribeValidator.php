<?php

declare(strict_types=1);

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;
use Hyperf\Di\Annotation\Inject;

/**
 * 订阅相关的参数验证器.
 */
class SubscribeValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 验证博客订阅请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateBlogSubscribe(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证确认订阅请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateConfirmSubscribe(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'token' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}