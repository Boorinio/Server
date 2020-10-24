<?php

namespace Rubix\Server\Serializers;

use Rubix\Server\Message;
use InvalidArgumentException;
use RuntimeException;

class Gzip implements Serializer
{
    /**
     * The compression level between 0 and 9, 0 meaning no compression.
     *
     * @var int
     */
    protected $level;

    /**
     * The base serializer.
     *
     * @var \Rubix\Server\Serializers\Serializer
     */
    protected $serializer;

    /**
     * @param int $level
     * @param \Rubix\Server\Serializers\Serializer|null $serializer
     * @throws \InvalidArgumentException
     */
    public function __construct(int $level = 1, ?Serializer $serializer = null)
    {
        if ($level < 0 or $level > 9) {
            throw new InvalidArgumentException('Level must be'
                . " between 0 and 9, $level given.");
        }

        if ($serializer instanceof self) {
            throw new InvalidArgumentException('Base serializer'
                . ' must not be an instance of itself.');
        }

        $this->level = $level;
        $this->serializer = $serializer ?? new JSON();
    }

    /**
     * Serialize a Message.
     *
     * @param \Rubix\Server\Message $message
     * @throws \RuntimeException
     * @return string
     */
    public function serialize(Message $message) : string
    {
        $data = $this->serializer->serialize($message);

        $data = gzencode($data, $this->level);

        if ($data === false) {
            throw new RuntimeException('Failed to compress data.');
        }

        return $data;
    }

    /**
     * Unserialize a Message.
     *
     * @param string $data
     * @throws \RuntimeException
     * @return \Rubix\Server\Message
     */
    public function unserialize(string $data) : Message
    {
        $data = gzdecode($data);

        if ($data === false) {
            throw new RuntimeException('Failed to decompress data.');
        }

        return $this->serializer->unserialize($data);
    }
}