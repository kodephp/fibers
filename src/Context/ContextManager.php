<?php

namespace Nova\Fibers\Context;

/**
 * ContextManager - 上下文管理器
 * 
 * 管理当前纤程的上下文
 */
class ContextManager
{
    /**
     * @var Context|null 当前上下文
     */
    private static ?Context $currentContext = null;

    /**
     * 设置当前上下文
     *
     * @param Context $context 上下文
     * @return void
     */
    public static function setContext(Context $context): void
    {
        self::$currentContext = $context;
    }

    /**
     * 获取当前上下文
     *
     * @return Context|null 当前上下文
     */
    public static function getContext(): ?Context
    {
        return self::$currentContext;
    }

    /**
     * 派生新的上下文并设置为当前上下文
     *
     * @return Context 新的上下文
     */
    public static function deriveContext(): Context
    {
        $newContext = self::$currentContext ? self::$currentContext->derive() : new Context();
        self::setContext($newContext);
        return $newContext;
    }

    /**
     * 取消当前上下文
     *
     * @param string|null $reason 取消原因
     * @return void
     */
    public static function cancelContext(?string $reason = null): void
    {
        if (self::$currentContext) {
            self::$currentContext->cancel($reason);
        }
    }
}
