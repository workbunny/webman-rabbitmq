<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanRabbitMQ\Channels;

class Channel extends \Bunny\Channel
{
    /**
     * 获取通道状态
     *
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }
}