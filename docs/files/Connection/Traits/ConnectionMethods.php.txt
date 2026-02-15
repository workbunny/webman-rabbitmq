<?php

declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanRabbitMQ\Connection\Traits;

use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodAccessRequestFrame;
use Bunny\Protocol\MethodConnectionCloseFrame;
use Bunny\Protocol\MethodConnectionCloseOkFrame;
use Bunny\Protocol\MethodConnectionOpenFrame;
use Bunny\Protocol\MethodConnectionSecureOkFrame;
use Bunny\Protocol\MethodConnectionStartOkFrame;
use Bunny\Protocol\MethodConnectionTuneOkFrame;

trait ConnectionMethods
{
    /**
     * send AMQP connection.heartbeat frame
     *
     * @return bool
     */
    public function connectionHeartbeat(): bool
    {
        $f = new HeartbeatFrame();

        return $this->frameSend($f);
    }

    /**
     * send AMQP connection.startOk frame
     *
     * @param array $clientProperties
     * @param string $mechanism
     * @param string $response
     * @param string $locale
     * @return bool
     */
    public function connectionStartOk(array $clientProperties, string $mechanism, string $response, string $locale = 'en_US'): bool
    {
        $f = new MethodConnectionStartOkFrame();
        $f->clientProperties = $clientProperties;
        $f->mechanism = $mechanism;
        $f->response = $response;
        $f->locale = $locale;

        return $this->frameSend($f);
    }

    /**
     * send AMQP connection.secureOk frame
     *
     * @param $response
     * @return bool
     */
    public function connectionSecureOk($response): bool
    {
        $f = new MethodConnectionSecureOkFrame();
        $f->response = $response;

        return $this->frameSend($f);
    }

    /**
     * send AMQP connection.tuneOk frame
     *
     * @param int $channelMax
     * @param int $frameMax
     * @param int $heartbeat
     * @return bool
     */
    public function connectionTuneOk(int $channelMax = 0, int $frameMax = 0, int $heartbeat = 0): bool
    {
        $f = new MethodConnectionTuneOkFrame();
        $f->channelMax = $channelMax;
        $f->frameMax = $frameMax;
        $f->heartbeat = $heartbeat;

        return $this->frameSend($f);
    }

    /**
     * send AMQP connection.open frame
     *
     * @param string $virtualHost
     * @param string $capabilities
     * @param bool $insist
     * @return bool
     */
    public function connectionOpen(string $virtualHost = '/', string $capabilities = '', bool $insist = false): bool
    {
        $f = new MethodConnectionOpenFrame();
        $f->virtualHost = $virtualHost;
        $f->capabilities = $capabilities;
        $f->insist = $insist;

        return $this->frameSend($f);
    }

    /**
     * send AMQP connection.close frame
     *
     * @param int $replyCode
     * @param string $replyText
     * @param int $closeClassId
     * @param int $closeMethodId
     * @return bool
     */
    public function connectionClose(int $replyCode, string $replyText, int $closeClassId, int $closeMethodId): bool
    {
        $f = new MethodConnectionCloseFrame();
        $f->replyCode = $replyCode;
        $f->replyText = $replyText;
        $f->closeClassId = $closeClassId;
        $f->closeMethodId = $closeMethodId;

        return $this->frameSend($f);
    }

    /**
     * send AMQP connection.closeOk frame
     *
     * @return bool
     */
    public function connectionCloseOk(): bool
    {
        $f = new MethodConnectionCloseOkFrame();

        return $this->frameSend($f);
    }

    /**
     * send AMQP access.request frame
     *
     * @deprecated The current conventional AMQP-server broker generally do not need to handle it by themselves
     * @param string $realm
     * @param bool $exclusive
     * @param bool $passive
     * @param bool $active
     * @param bool $write
     * @param bool $read
     * @return bool
     */
    public function accessRequest(
        string $realm = '/data',
        bool $exclusive = false,
        bool $passive = true,
        bool $active = true,
        bool $write = true,
        bool $read = true
    ): bool {
        $f = new MethodAccessRequestFrame();
        $f->realm = $realm;
        $f->exclusive = $exclusive;
        $f->passive = $passive;
        $f->active = $active;
        $f->write = $write;
        $f->read = $read;

        return $this->frameSend($f);
    }
}
