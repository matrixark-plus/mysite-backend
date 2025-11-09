<?php

declare(strict_types=1);
/**
 * 联系表单验证器
 * 处理联系表单相关请求参数的验证
 */

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;
use Hyperf\Di\Annotation\Inject;

/**
 * 联系表单相关的参数验证器.
 */
class ContactValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 验证提交联系表单请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateContactForm(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:200',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证获取联系表单列表请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateContactList(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|integer',
            'search' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新联系表单状态请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateUpdateStatus(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'status' => 'required|integer|min:0|max:1',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}