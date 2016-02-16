<?php

class CM_Log_LoggerTest extends CMTest_TestCase {

    public function testConstructor() {
        /** @var CM_Log_Context $context */
        $context = $this->mockClass('CM_Log_Context')->newInstanceWithoutConstructor();
        $badHandlersLayerList = [
            ['foo', 'bar'],
        ];

        $exception = $this->catchException(function () use ($context) {
            new CM_Log_Logger($context, []);
        });
        $this->assertInstanceOf('CM_Exception_Invalid', $exception);
        $this->assertSame('Logger should have at least 1 handler', $exception->getMessage());

        $exception = $this->catchException(function () use ($context, $badHandlersLayerList) {
            new CM_Log_Logger($context, $badHandlersLayerList);
        });
        $this->assertInstanceOf('CM_Exception_Invalid', $exception);
        $this->assertSame('Not logger handler instance', $exception->getMessage());

        $goodHandlersLayerList = [
            [
                $this->mockClass('CM_Log_Handler_Abstract')->newInstanceWithoutConstructor(),
                $this->mockClass('CM_Log_Handler_Abstract')->newInstanceWithoutConstructor(),
            ],
            [
                $this->mockClass('CM_Log_Handler_Abstract')->newInstanceWithoutConstructor(),
            ]
        ];
        $logger = new CM_Log_Logger($context, $goodHandlersLayerList);
        $this->assertInstanceOf('CM_Log_Logger', $logger);
    }

    public function testAddRecord() {
        $mockLogHandler = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::ERROR]);
        $mockWriteRecord = $mockLogHandler->mockMethod('_writeRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [[$mockLogHandler]]);

        $expectedRecord = new CM_Log_Record(CM_Log_Logger::ERROR, 'foo', new CM_Log_Context());
        $debugRecord = new CM_Log_Record(CM_Log_Logger::DEBUG, 'bar', new CM_Log_Context());
        $mockWriteRecord->set(function (CM_Log_Record $record) use ($expectedRecord) {
            $this->assertSame($expectedRecord, $record);
        });

        $this->callProtectedMethod($logger, '_addRecord', [$expectedRecord]);
        $this->callProtectedMethod($logger, '_addRecord', [$debugRecord]);
        $this->assertSame(1, $mockWriteRecord->getCallCount());
    }

    public function testHandlerLayerWriting() {
        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        $mockLogHandlerFoo = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::ERROR]);
        $mockLogHandlerBar = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::WARNING]);
        $mockLogHandlerBaz = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::ERROR]);

        $handleRecord = function (CM_Log_Record $record) use ($expectedRecord) {
            $this->assertSame($expectedRecord->getLevel(), $record->getLevel());
            $this->assertSame($expectedRecord->getMessage(), $record->getMessage());
        };

        $mockHandleRecordFoo = $mockLogHandlerFoo->mockMethod('handleRecord')->set($handleRecord);
        $mockHandleRecordBar = $mockLogHandlerBar->mockMethod('handleRecord')->set($handleRecord);
        $mockHandleRecordBaz = $mockLogHandlerBaz->mockMethod('handleRecord')->set($handleRecord);

        $logger = new CM_Log_Logger(new CM_Log_Context(), [[$mockLogHandlerFoo, $mockLogHandlerBar, $mockLogHandlerBaz]]);

        $this->callProtectedMethod($logger, '_addRecord', [$expectedRecord]);
        $this->assertSame(1, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(1, $mockHandleRecordBar->getCallCount());
        $this->assertSame(1, $mockHandleRecordBaz->getCallCount());
    }

    public function testHandlerLayerException() {
        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        $mockLogHandlerFoo = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::ERROR]);
        $mockLogHandlerBar = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::WARNING]);
        $mockLogHandlerBaz = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::ERROR]);

        $handleRecordOK = function (CM_Log_Record $record) use ($expectedRecord) {
            $this->assertSame($expectedRecord->getLevel(), $record->getLevel());
            $this->assertSame($expectedRecord->getMessage(), $record->getMessage());
        };

        $handleLoggerException = function (CM_Log_Record $record) {
            $this->assertInstanceOf('CM_Log_Record_Exception', $record);
            /** @var CM_Log_Record_Exception $record */
            $originalException = $record->getException();
            $this->assertInstanceOf('CM_Exception_Invalid', $originalException);
            $this->assertSame('Handler error', $originalException->getMessage());
        };

        $handleRecordFail = function () {
            throw new CM_Exception_Invalid('Handler error');
        };

        $mockHandleRecordFoo = $mockLogHandlerFoo->mockMethod('handleRecord')
            ->at(0, $handleRecordOK)
            ->at(1, $handleLoggerException);
        $mockHandleRecordBar = $mockLogHandlerBar->mockMethod('handleRecord')->set($handleRecordFail);
        $mockHandleRecordBaz = $mockLogHandlerBaz->mockMethod('handleRecord')
            ->at(0, $handleRecordOK)
            ->at(1, $handleLoggerException);

        $logger = new CM_Log_Logger(new CM_Log_Context(), [[$mockLogHandlerFoo, $mockLogHandlerBar, $mockLogHandlerBaz]]);

        $this->callProtectedMethod($logger, '_addRecord', [$expectedRecord]);
        $this->assertSame(2, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(1, $mockHandleRecordBar->getCallCount());
        $this->assertSame(2, $mockHandleRecordBaz->getCallCount());
    }

    public function testPassingMessageDown() {
        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        $mockLogHandlerFoo = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::ERROR]);
        $mockLogHandlerBar = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::WARNING]);
        $mockLogHandlerFooBar = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::INFO]);

        $mockLogHandlerBaz = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::ERROR]);
        $mockLogHandlerQuux = $this->mockClass('CM_Log_Handler_Abstract')->newInstance([CM_Log_Logger::ERROR]);

        $mockHandleRecordFoo = $mockLogHandlerFoo->mockMethod('handleRecord')->set(function () {
            throw new CM_Exception_Invalid('Foo error');
        });
        $mockHandleRecordBar = $mockLogHandlerBar->mockMethod('handleRecord')->set(function () {
            throw new CM_Exception_Invalid('Bar error');
        });
        $mockHandleRecordFooBar = $mockLogHandlerFooBar->mockMethod('handleRecord')->set(function () {
            throw new CM_Exception_Invalid('FooBar error');
        });

        $mockHandleRecordBaz = $mockLogHandlerBaz->mockMethod('handleRecord')
            ->at(0, function (CM_Log_Record $record) use ($expectedRecord) {
                $this->assertSame($expectedRecord->getLevel(), $record->getLevel());
                $this->assertSame($expectedRecord->getMessage(), $record->getMessage());
            })
            ->at(1, function (CM_Log_Record $record) {
                $this->assertInstanceOf('CM_Log_Record_Exception', $record);
                /** @var CM_Log_Record_Exception $record */
                $originalException = $record->getException();
                $this->assertInstanceOf('CM_Exception_Invalid', $originalException);
                $this->assertSame('Foo error', $originalException->getMessage());

            })
            ->at(2, function (CM_Log_Record $record) {
                $this->assertInstanceOf('CM_Log_Record_Exception', $record);
                /** @var CM_Log_Record_Exception $record */
                $originalException = $record->getException();
                $this->assertInstanceOf('CM_Exception_Invalid', $originalException);
                $this->assertSame('Bar error', $originalException->getMessage());
                throw new CM_Exception('Error again');
            });

        $mockHandleRecordQuux = $mockLogHandlerQuux->mockMethod('handleRecord')
            ->at(0, function (CM_Log_Record $record) {
                $this->assertInstanceOf('CM_Log_Record_Exception', $record);
                /** @var CM_Log_Record_Exception $record */
                $originalException = $record->getException();
                $this->assertInstanceOf('CM_Exception_Invalid', $originalException);
                $this->assertSame('Foo error', $originalException->getMessage());
            })
            ->at(1, function (CM_Log_Record $record) {
                $this->assertInstanceOf('CM_Log_Record_Exception', $record);
                /** @var CM_Log_Record_Exception $record */
                $originalException = $record->getException();
                $this->assertInstanceOf('CM_Exception_Invalid', $originalException);
                $this->assertSame('Bar error', $originalException->getMessage());
            });

        $logger = new CM_Log_Logger(new CM_Log_Context(), [
            [$mockLogHandlerFoo, $mockLogHandlerBar, $mockLogHandlerFooBar],
            [$mockLogHandlerBaz],
            [$mockLogHandlerQuux]
        ]);

        $this->callProtectedMethod($logger, '_addRecord', [$expectedRecord]);
        $this->assertSame(1, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(1, $mockHandleRecordBar->getCallCount());
        $this->assertSame(1, $mockHandleRecordFooBar->getCallCount());

        $this->assertSame(3, $mockHandleRecordBaz->getCallCount());
        $this->assertSame(3, $mockHandleRecordQuux->getCallCount());
    }

    public function testLoggingWithContext() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        // without any context
        $logger = new CM_Log_Logger(new CM_Log_Context(), [[$mockLogHandler]]);
        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $context = $record->getContext();
            $this->assertNull($context->getComputerInfo());
            $this->assertNull($context->getUser());
            $this->assertNull($context->getHttpRequest());
            $this->assertSame([], $context->getExtra());
        });
        $logger->addMessage('foo', CM_Log_Logger::INFO);
        $this->assertSame(1, $mockHandleRecord->getCallCount());

        // with a global context
        $computerInfo = new CM_Log_Context_ComputerInfo('foo.dev', '42.0');
        $contextGlobal = new CM_Log_Context(null, null, $computerInfo);
        $logger = new CM_Log_Logger($contextGlobal, [[$mockLogHandler]]);

        $mockHandleRecord->set(function (CM_Log_Record $record) use ($computerInfo) {
            $context = $record->getContext();
            $this->assertEquals($computerInfo, $context->getComputerInfo());
            $this->assertNull($context->getUser());
            $this->assertNull($context->getHttpRequest());
            $this->assertSame([], $context->getExtra());
        });
        $logger->addMessage('foo', CM_Log_Logger::INFO);
        $this->assertSame(2, $mockHandleRecord->getCallCount());

        // with a global context + log context
        $computerInfo = new CM_Log_Context_ComputerInfo('foo.dev', '42.0');
        $contextGlobal = new CM_Log_Context(null, null, $computerInfo);
        $logger = new CM_Log_Logger($contextGlobal, [[$mockLogHandler]]);

        $mockHandleRecord->set(function (CM_Log_Record $record) use ($computerInfo) {
            $context = $record->getContext();
            $this->assertEquals($computerInfo, $context->getComputerInfo());
            $this->assertNull($context->getUser());
            $this->assertNull($context->getHttpRequest());
            $this->assertSame(['foo' => 10], $context->getExtra());
        });
        $logger->addMessage('foo', CM_Log_Logger::INFO, new CM_Log_Context(null, null, null, ['foo' => 10]));
        $this->assertSame(3, $mockHandleRecord->getCallCount());
    }

    public function testLogHelpers() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [[$mockLogHandler]]);

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using debug method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::DEBUG, $record->getLevel());
        });
        $logger->debug('message sent using debug method');
        $this->assertSame(1, $mockHandleRecord->getCallCount());

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using info method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::INFO, $record->getLevel());
        });
        $logger->info('message sent using info method');
        $this->assertSame(2, $mockHandleRecord->getCallCount());

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using warning method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::WARNING, $record->getLevel());
        });
        $logger->warning('message sent using warning method');
        $this->assertSame(3, $mockHandleRecord->getCallCount());

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using error method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::ERROR, $record->getLevel());
        });
        $logger->error('message sent using error method');
        $this->assertSame(4, $mockHandleRecord->getCallCount());

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using critical method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::CRITICAL, $record->getLevel());
        });
        $logger->critical('message sent using critical method');
        $this->assertSame(5, $mockHandleRecord->getCallCount());
    }

    public function testHandleException() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [[$mockLogHandler]]);

        $exception = new Exception('foo');
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $recordException = $record->getSerializableException();
            $this->assertSame($exception->getMessage(), $recordException->getMessage());
            $this->assertSame($exception->getLine(), $recordException->getLine());
            $this->assertSame($exception->getFile(), $recordException->getFile());
            $this->assertSame('Exception: foo', $record->getMessage());
            $this->assertSame(CM_Log_Logger::CRITICAL, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(1, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('bar');
        $exception->setSeverity(CM_Exception::WARN);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $recordException = $record->getSerializableException();
            $this->assertSame($exception->getMessage(), $recordException->getMessage());
            $this->assertSame($exception->getLine(), $recordException->getLine());
            $this->assertSame($exception->getFile(), $recordException->getFile());
            $this->assertSame('CM_Exception: bar', $record->getMessage());
            $this->assertSame(CM_Log_Logger::WARNING, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(2, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('foobar');
        $exception->setSeverity(CM_Exception::ERROR);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $recordException = $record->getSerializableException();
            $this->assertSame($exception->getMessage(), $recordException->getMessage());
            $this->assertSame($exception->getLine(), $recordException->getLine());
            $this->assertSame($exception->getFile(), $recordException->getFile());
            $this->assertSame('CM_Exception: foobar', $record->getMessage());
            $this->assertSame(CM_Log_Logger::ERROR, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(3, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('test');
        $exception->setSeverity(CM_Exception::FATAL);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $recordException = $record->getSerializableException();
            $this->assertSame($exception->getMessage(), $recordException->getMessage());
            $this->assertSame($exception->getLine(), $recordException->getLine());
            $this->assertSame($exception->getFile(), $recordException->getFile());
            $this->assertSame('CM_Exception: test', $record->getMessage());
            $this->assertSame(CM_Log_Logger::CRITICAL, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(4, $mockHandleRecord->getCallCount());
    }

    public function testStaticLogLevelMethods() {
        $this->assertSame('INFO', CM_Log_Logger::getLevelName(CM_Log_Logger::INFO));
        $this->assertNotEmpty(CM_Log_Logger::getLevels());
        $this->assertTrue(CM_Log_Logger::hasLevel(CM_Log_Logger::INFO));
        $this->assertFalse(CM_Log_Logger::hasLevel(666));
    }

    /**
     * @expectedException CM_Exception_Invalid
     * @expectedExceptionMessage is not defined, use one of
     */
    public function testStaticGetLevelNameException() {
        CM_Log_Logger::getLevelName(666);
    }
}
