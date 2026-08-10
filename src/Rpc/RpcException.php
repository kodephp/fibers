<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

/**
 * RPC 异常
 */
class RpcException extends \Exception
{
    protected int $errorCode;
    protected array $errorData = [];

    public function __construct(string $message, int $code = -32603, array $data = [])
    {
        parent::__construct($message, $code);
        $this->errorCode = $code;
        $this->errorData = $data;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }
}
