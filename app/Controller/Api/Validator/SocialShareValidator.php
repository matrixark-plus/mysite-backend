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

use App\Exception\ValidationException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

/**
 * 社交分享验证器
 * 用于验证社交分享相关的请求参数.
 */
class SocialShareValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 验证获取分享配置的请求
     * 虽然该接口目前不需要参数，但为了保持一致性，仍保留验证器方法.
     *
     * @param array $data 请求数据
     * @return array 验证通过的数据
     * @throws ValidationException
     */
    public function validateGetShareConfig(array $data): array
    {
        // 获取分享配置接口目前不需要参数验证
        // 但保留该方法以保持验证器的一致性和可扩展性
        return $data;
    }
}
