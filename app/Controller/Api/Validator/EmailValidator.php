<?php

declare(strict_types=1);

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;
use Psr\Container\ContainerInterface;

/**
 * 邮件管理相关的参数验证器.
 */
class EmailValidator
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
     * 验证发送验证码请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateVerifyCode(array $data): array
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
     * 验证发送测试邮件请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateSendTestEmail(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}