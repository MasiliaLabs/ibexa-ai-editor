<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Llm;

use Masilia\Contracts\IbexaAi\Observability\RequestLog;
use Masilia\Contracts\IbexaAi\Observability\RequestLogger;

final class CollectingRequestLogger implements RequestLogger
{
    /** @var list<RequestLog> */
    public array $entries = [];

    public function log(RequestLog $entry): void
    {
        $this->entries[] = $entry;
    }
}
