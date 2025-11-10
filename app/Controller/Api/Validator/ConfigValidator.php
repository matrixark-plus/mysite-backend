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
 * 配置管理相关的参数验证器.
 */
class ConfigValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 验证获取配置请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateGetConfig(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'key' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新配置请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateUpdateConfig(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'key' => 'required|string|max:255',
            'value' => 'required',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证批量更新配置请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateBatchUpdateConfig(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'configs' => 'required|array',
            'configs.*' => 'array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // 验证configs数组中的每个配置项
        foreach ($data['configs'] as $config) {
            $itemValidator = $this->validatorFactory->make($config, [
                'key' => 'required|string|max:255',
                'value' => 'required',
            ]);

            if ($itemValidator->fails()) {
                throw new ValidationException($itemValidator);
            }
        }

        return $validator->validated();
    }
}
