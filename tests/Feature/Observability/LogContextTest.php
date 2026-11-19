<?php

declare(strict_types=1);

use Illuminate\Log\Context\ContextLogProcessor;
use Illuminate\Support\Facades\Context;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

it('appends the Context (request_id) as metadata of every log record', function (): void {
    Context::add('request_id', 'req-42');

    // O mesmo processor que o LogManager anexa a cada canal.
    $handler = new TestHandler;
    $logger = new Logger('test', [$handler], [$this->app->make(ContextLogProcessor::class)]);

    $logger->info('order placed', ['order_id' => 'o-1']);

    $record = $handler->getRecords()[0];
    expect($record->context)->toBe(['order_id' => 'o-1'])
        ->and($record->extra)->toBe(['request_id' => 'req-42']);
});
