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

class SystemValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * 验证统计数据参数.
     * @param array $data 请求数据
     * @return array 验证后的数据
     */
    public function validateStatistics(array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'type' => 'nullable|string|max:50',
        ], [
            'start_date.date_format' => '开始日期格式不正确，应为YYYY-MM-DD',
            'end_date.date_format' => '结束日期格式不正确，应为YYYY-MM-DD',
            'end_date.after_or_equal' => '结束日期必须大于或等于开始日期',
            'type.max' => '统计类型长度不能超过50个字符',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
