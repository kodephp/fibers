<?php

declare(strict_types=1);

namespace Nova\Fibers\Event;

/**
 * PaymentSuccessEvent事件类
 * 
 * 表示支付成功事件
 */
class PaymentSuccessEvent
{
    /**
     * 事件数据
     * 
     * @var array
     */
    protected array $data;

    /**
     * 构造函数
     * 
     * @param array $data 事件数据
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * 获取事件数据
     * 
     * @return array 事件数据
     */
    public function getData(): array
    {
        return $this->data;
    }
}