<?php

namespace Joblocal\LaravelSqsSnsSubscriptionQueue\Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;

use Joblocal\LaravelSqsSnsSubscriptionQueue\Queue\SqsSnsQueue;
use Joblocal\LaravelSqsSnsSubscriptionQueue\Queue\Jobs\SqsSnsJob;

class SqsSnsQueueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $sqsClient;

    protected function setUp(): void
    {
        $this->sqsClient = Mockery::mock(SqsClient::class);
    }

    public function testCanInstantiateQueue()
    {
        $queue = new SqsSnsQueue($this->sqsClient, 'default_queue');

        $this->assertInstanceOf(SqsSnsQueue::class, $queue);
    }

    public function testWillSetRoutes()
    {
        $queue = new SqsSnsQueue($this->sqsClient, 'default_queue', '', [
            "Subject#action" => '\\Job',
        ]);

        $queueReflection = new \ReflectionClass($queue);
        $routeReflectionProperty = $queueReflection->getProperty('routes');

        $this->assertEquals([
            "Subject#action" => '\\Job',
        ], $routeReflectionProperty->getValue($queue));
    }

    public function testWillCallReceiveMessage()
    {
        $this->sqsClient
            ->expects('receiveMessage')
            ->andReturn([
                'Messages' => [],
            ]);

        $queue = new SqsSnsQueue($this->sqsClient, 'default_queue');
        $queue->setContainer($this->createStub(Container::class));

        $queue->pop();
    }

    public function testWillPopMessageOffQueue()
    {
        $body = json_encode(
            [
                'MessageId' => 'bc065409-fe1b-59c2-b17c-0e056cd19d5d',
                'TopicArn' => 'arn:aws:sns',
                'Subject' => 'Subject#action',
                'Message' => '',
            ]
        );

        $message = [
            'Body' => $body,
        ];

        $this->sqsClient
            ->expects('receiveMessage')
            ->andReturn([
                'Messages' => [
                    $message,
                ],
            ]);

        $queue = new SqsSnsQueue($this->sqsClient, 'default_queue', '', [
            "Subject#action" => '\\Job',
        ]);

        $queue->setContainer($this->createStub(Container::class));

        $job = $queue->pop();
        $expectedRawBody = [
            'uuid' =>  'bc065409-fe1b-59c2-b17c-0e056cd19d5d',
            'displayName' => '\\Job',
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'data' => [
                'commandName' => '\\Job',
                'command' => 'N;',
            ],
        ];

        $this->assertInstanceOf(SqsSnsJob::class, $job);
        $this->assertEquals(json_encode($expectedRawBody), $job->getRawBody());
    }
}
