<?php

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\Flags;
use Zenmanage\Flags\Request\Entities\DefaultValue;
use Zenmanage\Flags\Request\Entities\Context\Context;
use Zenmanage\Flags\Response\Entities\Flag;
use Zenmanage\Shared\HttpClient;
use Zenmanage\Exceptions\InvalidTokenException;
use Zenmanage\Exceptions\NoResponseException;
use Zenmanage\Exceptions\FlagNotFoundException;

class FlagsTest extends TestCase
{
    private $clientMock;
    private Flags $flags;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(HttpClient::class);
        $this->flags = new Flags($this->clientMock);
    }

    public function testAllReturnsFlagsArray()
    {
        $response = json_encode([
            'data' => [
                [
                    'flag' => [
                        'key' => 'feature_x',
                        'type' => 'boolean',
                        'value' => true
                    ]
                ],
                [
                    'flag' => [
                        'key' => 'feature_y',
                        'type' => 'string',
                        'value' => 'on'
                    ]
                ]
            ]
        ]);
        $this->clientMock->method('get')->willReturn($response);

        $flags = $this->flags->all();

        $this->assertIsArray($flags);
        $this->assertInstanceOf(Flag::class, $flags[0]);
        $this->assertEquals('feature_x', $flags[0]->key);
        $this->assertEquals('feature_y', $flags[1]->key);
    }

    public function testAllThrowsInvalidTokenException()
    {
        $this->clientMock->method('get')->will($this->throwException(new InvalidTokenException()));
        $this->expectException(InvalidTokenException::class);
        $this->flags->all();
    }

    public function testAllReturnsDefaultsOnException()
    {
        $default = new DefaultValue('feature_z', 'boolean', false);
        $this->flags->withDefault('feature_z', 'boolean', false);

        $this->clientMock->method('get')->will($this->throwException(new Exception('Network error')));

        $flags = $this->flags->all();

        $this->assertIsArray($flags);
        $this->assertInstanceOf(Flag::class, $flags[0]);
        $this->assertEquals('feature_z', $flags[0]->key);
    }

    public function testAllThrowsNoResponseExceptionIfNoDefaults()
    {
        $this->clientMock->method('get')->will($this->throwException(new Exception('Network error')));
        $this->expectException(NoResponseException::class);
        $this->flags->all();
    }

    public function testReportPostsUsage()
    {
        $this->clientMock->expects($this->once())
            ->method('post')
            ->with('/flags/feature_x/usage');
        $this->flags->report('feature_x');
    }

    public function testReportThrowsInvalidTokenException()
    {
        $this->clientMock->method('post')->will($this->throwException(new InvalidTokenException()));
        $this->expectException(InvalidTokenException::class);
        $this->flags->report('feature_x');
    }

    public function testReportThrowsFlagNotFoundException()
    {
        $this->clientMock->method('post')->will($this->throwException(new FlagNotFoundException()));
        $this->expectException(FlagNotFoundException::class);
        $this->flags->report('feature_x');
    }

    public function testSingleReturnsFlag()
    {
        $flagData = [
            'data' => [
                'flag' => [
                    'key' => 'feature_x',
                    'name' => 'Feature X',
                    'type' => 'boolean',
                    'value' => (object)true
                ]
            ]
        ];
        $this->clientMock->method('get')->willReturn(json_encode($flagData));

        $flag = $this->flags->single('feature_x');

        $this->assertInstanceOf(Flag::class, $flag);
        $this->assertEquals('feature_x', $flag->key);
    }

    public function testSingleThrowsInvalidTokenException()
    {
        $this->clientMock->method('get')->will($this->throwException(new InvalidTokenException()));
        $this->expectException(InvalidTokenException::class);
        $this->flags->single('feature_x');
    }

    public function testSingleThrowsFlagNotFoundException()
    {
        $this->clientMock->method('get')->will($this->throwException(new FlagNotFoundException()));
        $this->expectException(FlagNotFoundException::class);
        $this->flags->single('feature_x');
    }

    public function testSingleReturnsDefaultValueOnException()
    {
        $this->flags->withDefault('feature_x', 'boolean', true);
        $this->clientMock->method('get')->will($this->throwException(new Exception('Network error')));

        $flag = $this->flags->single('feature_x');

        $this->assertInstanceOf(Flag::class, $flag);
        $this->assertEquals('feature_x', $flag->key);
        $this->assertEquals(true, $flag->getValue());
    }

    public function testSingleThrowsNoResponseExceptionIfNoDefault()
    {
        $this->clientMock->method('get')->will($this->throwException(new Exception('Network error')));
        $this->expectException(NoResponseException::class);
        $this->flags->single('feature_x');
    }

    public function testWithContextReturnsSelf()
    {
        $context = $this->createMock(Context::class);
        $result = $this->flags->withContext($context);
        $this->assertSame($this->flags, $result);
    }

    public function testWithDefaultReturnsSelf()
    {
        $result = $this->flags->withDefault('feature_x', 'boolean', true);
        $this->assertSame($this->flags, $result);
    }
}