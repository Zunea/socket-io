<?php

declare(strict_types=1);
/**
 *
 * This is my open source code, please do not use it for commercial applications.
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code
 *
 * @author CodingHePing<847050412@qq.com>
 * @link   https://github.com/Hyperf-Glory/socket-io
 */
namespace App\Component;

use Hyperf\Redis\RedisFactory;

class Sms
{
    /**
     * 短信验证码用途渠道.
     */
    public const SMS_USAGE = [
        'user_register',     // 注册账号
        'forget_password',   // 找回密码验
        'change_mobile',     // 修改手机
    ];

    /**
     * 检测验证码是否正确.
     *
     * @param string $type 发送类型
     * @param string $mobile 手机号
     * @param string $code 验证码
     */
    public function check(string $type, string $mobile, string $code): bool
    {
        $smsCode = $this->redis()->get($this->getKey($type, $mobile));

        return $smsCode === $code;
    }

    /**
     * 发送验证码
     *
     * @param string $usage 验证码用途
     * @param string $mobile 手机号
     *
     * @return array|bool
     */
    public function send(string $usage, string $mobile)
    {
        if (! $this->isUsages($usage)) {
            return [
                false,
                [
                    'msg' => "[{$usage}]：此类短信验证码不支持发送",
                    'data' => [],
                ],
            ];
        }

        $key = $this->getKey($usage, $mobile);

        // 为防止刷短信行为，此处可进行过滤处理
        [$isTrue, $data] = $this->filter($usage, $mobile);
        if (! $isTrue) {
            return [false, $data];
        }

        if (! $smsCode = $this->getCode($key)) {
            $smsCode = random_int(100000, 999999);
        }

        // 设置短信验证码
        $this->setCode($key, (string) $smsCode);

        // ... 调取短信接口，建议异步任务执行 (暂无短信接口，省略处理)

        return [
            true,
            [
                'msg' => 'success',
                'data' => ['type' => $usage, 'code' => $smsCode],
            ],
        ];
    }

    /**
     * 获取缓存的验证码
     *
     * @return mixed
     */
    public function getCode(string $key)
    {
        return $this->redis()->get($key);
    }

    /**
     * 设置验证码缓存.
     *
     * @param string $key 缓存key
     * @param string $sms_code 验证码
     * @param float|int $exp 过期时间（默认15分钟）
     *
     * @return mixed
     */
    public function setCode(string $key, string $sms_code, $exp = 60 * 15)
    {
        return $this->redis()->setex($key, $exp, $sms_code);
    }

    /**
     * 删除验证码缓存.
     *
     * @param string $usage 验证码用途
     * @param string $mobile 手机号
     *
     * @return mixed
     */
    public function delCode(string $usage, string $mobile)
    {
        return $this->redis()->del($this->getKey($usage, $mobile));
    }

    /**
     * 短信发送过滤验证
     *
     * @param string $usage 验证码用途
     * @param string $mobile 手机号
     */
    public function filter(string $usage, string $mobile): array
    {
        return [
            true,
            [
                'msg' => 'ok',
                'data' => [],
            ],
        ];
    }

    /**
     * 判断验证码用途渠道是否注册.
     */
    public function isUsages(string $usage): bool
    {
        return in_array($usage, self::SMS_USAGE);
    }

    /**
     * @return \Hyperf\Redis\RedisProxy|mixed|\Redis
     */
    private function redis()
    {
        return di(RedisFactory::class)->get(env('CLOUD_REDIS'));
    }

    /**
     * 获取缓存key.
     *
     * @param string $type 短信用途
     * @param string $mobile 手机号
     */
    private function getKey(string $type, string $mobile): string
    {
        return "sms_code:{$type}:{$mobile}";
    }
}
