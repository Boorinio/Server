<?php

namespace Rubix\Server\Tests\Serializers;

use Rubix\ML\Datasets\Unlabeled;
use Rubix\Server\Queries\Predict;
use Rubix\Server\Queries\Query;
use Rubix\Server\Serializers\Native;
use Rubix\Server\Serializers\Serializer;
use PHPUnit\Framework\TestCase;

/**
 * @group Serializers
 * @covers \Rubix\Server\Serializers\Native
 */
class NativeTest extends TestCase
{
    /**
     * @var \Rubix\Server\Serializers\Native
     */
    protected $serializer;

    /**
     * @before
     */
    protected function setUp() : void
    {
        $this->serializer = new Native();
    }

    /**
     * @test
     */
    public function build() : void
    {
        $this->assertInstanceOf(Native::class, $this->serializer);
        $this->assertInstanceOf(Serializer::class, $this->serializer);
    }

    /**
     * @test
     */
    public function serializeUnserialize() : void
    {
        $query = new Predict(new Unlabeled(['beef']));

        $data = $this->serializer->serialize($query);

        $this->assertIsString($data);
        $this->assertNotEmpty($data);

        $query = $this->serializer->unserialize($data);

        $this->assertInstanceOf(Predict::class, $query);
        $this->assertInstanceOf(Query::class, $query);
    }
}
