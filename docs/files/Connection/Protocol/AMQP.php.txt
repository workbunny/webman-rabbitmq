<?php

declare(strict_types=1);

namespace Protocols;

use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;

use function Workbunny\WebmanRabbitMQ\binary_dump;

use Workerman\Connection\ConnectionInterface;
use Workerman\Worker;

class AMQP
{
    public static bool $debug = false;

    /** @var ProtocolReader|null */
    protected static ?ProtocolReader $protocolReader = null;

    /** @var ProtocolWriter|null */
    protected static ?ProtocolWriter $protocolWriter = null;

    /**
     * @return ProtocolReader
     */
    public static function reader(): ProtocolReader
    {
        return self::$protocolReader ??= new ProtocolReader();
    }

    /**
     * @return ProtocolWriter
     */
    public static function writer(): ProtocolWriter
    {
        return self::$protocolWriter ??= new ProtocolWriter();
    }

    /**
     * Check the integrity of the package.
     * Please return the length of package.
     * If length is unknown please return 0 that means waiting for more data.
     * If the package has something wrong please return -1 the connection will be closed.
     *
     * @param string $buffer
     * @param ConnectionInterface $connection
     * @return int
     */
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        try {
            $buf = new Buffer($buffer);
            // not enough data, type | channel | payload size | payload | frame end
            if ($buf->getLength() < ($min = (1 + 2 + 4 + 1))) {
                return 0;
            }
            // check is AMQP protocol header - AMQP-0-9-1
            if ($buf->read($min) === "AMQP\x00\x00\x09\x01") {
                return 8;
            }
            // check is AMQP frame
            $payloadSize = $buf->readUint32(3);
            // not enough data
            if ($buf->getLength() < $payloadSize + $min) {
                return 0;
            }

            return $payloadSize + $min;
        } catch (\Throwable $throwable) {
            Worker::safeEcho("AMQP protocol input Error: {$throwable->getMessage()}\n");
            $connection->close();

            return -1;
        }
    }

    /**
     * Decode package and emit onMessage($message) callback, $message is the result that decode returned.
     *
     * @param string $buffer
     * @param ConnectionInterface $connection
     * @return AbstractFrame|Buffer|null
     */
    public static function decode(string $buffer, ConnectionInterface $connection): null|AbstractFrame|Buffer
    {
        try {
            // buffer object
            $res = new Buffer($buffer);
            // is AMQP frame, trans to Frame object
            if (!str_starts_with($buffer, 'AMQP')) {
                $res = self::reader()->consumeFrame($res);
            }
            if (!$res) {
                throw new \InvalidArgumentException("Invalid frame [$buffer]");
            }
            if (self::$debug) {
                $b = binary_dump($buffer);
                $t = microtime(true);
                $c = $res::class;
                Worker::safeEcho(<<<doc
<<- AMQP <g>Decode</g> on <g>$t</g> [<g>$c</g>]:

$b\n
doc
                    , true);
            }

            return $res;
        } catch (\Throwable $throwable) {
            Worker::safeEcho("AMQP protocol decode Error: {$throwable->getMessage()}\n");
            $connection->close();

            return null;
        }
    }

    /**
     * Encode package before sending to client.
     *
     * @param AbstractFrame|Buffer $data
     * @param ConnectionInterface $connection
     * @return string|null
     */
    public static function encode(AbstractFrame|Buffer $data, ConnectionInterface $connection): ?string
    {
        try {
            $buffer = $data;
            if ($data instanceof AbstractFrame) {
                self::writer()->appendFrame($data, $buffer = new Buffer());
            }
            $res = $buffer->read($buffer->getLength());
            if (!$res) {
                throw new \InvalidArgumentException('Invalid frame');
            }
            if (self::$debug) {
                $b = binary_dump($res);
                $t = microtime(true);
                $c = $data::class;
                Worker::safeEcho(<<<doc
->> AMQP <g>Encode</g> on <g>$t</g> [<g>$c</g>]:

$b\n
doc
                    , true);
            }

            return $res;
        } catch (\Throwable $throwable) {
            Worker::safeEcho("AMQP protocol encode Error: {$throwable->getMessage()}\n");
            $connection->close();

            return null;
        }
    }
}
