<?php

declare(strict_types=1);

namespace Kode\Fibers\Attributes;

/**
 * 基础属性接口
 *
 * 所有 Fiber 相关属性的标记接口，用于标识属性类型。
 * 支持通过反射获取属性元数据。
 */
interface Attribute
{
    /**
     * 获取属性元数据
     *
     * @return array 属性配置数组
     */
    public function getMetadata(): array;
}
