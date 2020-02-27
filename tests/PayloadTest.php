<?php

namespace Ang3\Component\Http\Tests;

use Ang3\Component\Http\Payload;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Serializer;
use stdClass;

/**
 * @coversDefaultClass \Ang3\Component\Http\Payload
 *
 * @author Joanis ROUANET
 */
class PayloadTest extends TestCase
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var Payload
     */
    private $arrayPayload;

    /**
     * @var Payload
     */
    private $objectPayload;

    public function setUp(): void
    {
        $this->data = [
            'foo' => [
                (object) [
                    'bar' => 'qux',
                    'baz' => null,
                ],
            ],
            'bar' => null,
        ];

        $this->arrayPayload = Payload::create($this->data);
        $this->objectPayload = Payload::create((object) $this->data);
    }

    /**
     * @covers ::getSerializer
     */
    public function testGetSerializer(): void
    {
        $this->assertInstanceOf(Serializer::class, $this->arrayPayload::getSerializer());
        $this->assertInstanceOf(Serializer::class, $this->objectPayload::getSerializer());
    }

    /**
     * @covers ::buildHttpQuery
     */
    public function testBuildHttpQuery(): void
    {
        $this->assertEquals('foo%5B0%5D%5Bbar%5D=qux', $this->arrayPayload->buildHttpQuery());
        $this->assertEquals('foo%5B0%5D%5Bbar%5D=qux', $this->objectPayload->buildHttpQuery());
    }

    /**
     * @covers ::encode
     */
    public function testEncodeJson(): void
    {
        $this->assertEquals('{"foo":[{"bar":"qux","baz":null}],"bar":null}', $this->arrayPayload->encode('json'));
        $this->assertEquals('{"foo":[{"bar":"qux","baz":null}],"bar":null}', $this->objectPayload->encode('json'));
    }

    /**
     * @covers ::set
     */
    public function testSet(): void
    {
        $this->assertEquals($this->arrayPayload, $this->arrayPayload->set('[foo]', 'bar'));
        $this->assertEquals($this->objectPayload, $this->objectPayload->set('foo', 'bar'));
    }

    /**
     * @covers ::set
     */
    public function testSetObjectPathNotWritable(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $this->assertEquals($this->arrayPayload, $this->arrayPayload->set('foo', 'bar'));
    }

    /**
     * @covers ::set
     */
    public function testSetArrayPathNotWritable(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $this->assertEquals($this->objectPayload, $this->objectPayload->set('[foo]', 'bar'));
    }

    /**
     * @covers ::get
     */
    public function testGet(): void
    {
        // Valid paths
        $this->assertEquals($this->data['foo'], $this->arrayPayload->get('[foo]'));
        $this->assertEquals($this->data['foo'], $this->objectPayload->get('foo'));

        // Invalid paths
        $this->assertEquals(null, $this->arrayPayload->get('baz'));
        $this->assertEquals(null, $this->objectPayload->get('baz'));
        $this->assertEquals(__FUNCTION__, $this->arrayPayload->get('baz', __FUNCTION__));
        $this->assertEquals(__FUNCTION__, $this->objectPayload->get('baz', __FUNCTION__));
    }

    /**
     * @covers ::isReadable
     */
    public function testIsReadable(): void
    {
        // Valid paths
        $this->assertTrue($this->arrayPayload->isReadable('[foo]'));
        $this->assertTrue($this->objectPayload->isReadable('foo'));

        // Invalid paths
        $this->assertFalse($this->arrayPayload->isReadable('foo'));
        $this->assertFalse($this->objectPayload->isReadable('[foo]'));
    }

    /**
     * @covers ::isWritable
     */
    public function testIsWritable(): void
    {
        // Valid paths
        $this->assertTrue($this->arrayPayload->isWritable('[foo]'));
        $this->assertTrue($this->objectPayload->isWritable('foo'));

        // Invalid paths
        $this->assertFalse($this->arrayPayload->isWritable('foo'));
        $this->assertFalse($this->objectPayload->isWritable('[foo]'));
    }

    public function provideTestIsEmpty(): array
    {
        $standardObject = new stdClass();
        $standardObject->foo = 'bar';

        $emptyStandardObject = new stdClass();

        $emptyObject = new EmptyObject();

        return [
            [[], true],
            [['foo'], false],
            [$emptyStandardObject, true],
            [$standardObject, false],
            [$this, false],
            [$emptyObject, true],
        ];
    }

    /**
     * @covers ::isEmpty
     * @dataProvider provideTestIsEmpty
     *
     * @param mixed $data
     */
    public function testIsEmpty($data, bool $result): void
    {
        $payload = Payload::create($data);

        $this->assertEquals($result, $payload->isEmpty());
    }

    /**
     * @covers ::discover
     */
    public function testDiscover(): void
    {
        $this->assertEquals([
            '[foo]' => $this->data['foo'],
            '[bar]' => null,
        ], $this->arrayPayload->discover([
            'recursive' => false,
        ]));

        $this->assertEquals([
            'foo' => $this->data['foo'],
            'bar' => null,
        ], $this->objectPayload->discover([
            'recursive' => false,
        ]));
    }

    /**
     * @covers ::discover
     */
    public function testDiscoverRecursive(): void
    {
        $this->assertEquals([
            '[foo][0].bar' => 'qux',
            '[foo][0].baz' => null,
            '[bar]' => null,
        ], $this->arrayPayload->discover([
            'recursive' => true,
        ]));

        $this->assertEquals([
            'foo[0].bar' => 'qux',
            'foo[0].baz' => null,
            'bar' => null,
        ], $this->objectPayload->discover([
            'recursive' => true,
        ]));
    }
}
