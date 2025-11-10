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

namespace App\Controller\Api\Validator;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;

/**
 * 邮件管理相关的参数验证器.
 */
class EmailValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

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

    /**
     * 验证发送邮件参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateSendEmail(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'email' => 'required_without:to|email',
            'to' => 'required_without:email|email',
            'subject' => 'required|string|max:255',
            'content' => 'required_if:template,null|string',
            'template' => 'required_if:content,null|string|max:255',
            'data' => 'nullable|array',
        ], [
            'email.required_without' => '邮箱地址不能为空',
            'email.email' => '邮箱格式不正确',
            'to.required_without' => '接收邮箱不能为空',
            'to.email' => '接收邮箱格式不正确',
            'subject.required' => '邮件主题不能为空',
            'subject.max' => '邮件主题不能超过255个字符',
            'content.required_if' => '邮件内容不能为空',
            'template.required_if' => '邮件模板不能为空',
            'template.max' => '邮件模板名称不能超过255个字符',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
