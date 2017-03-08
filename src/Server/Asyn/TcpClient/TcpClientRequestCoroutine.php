<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Asyn\TcpClient;

use Server\CoreBase\SwooleException;
use Server\Coroutine\CoroutineBase;
use Server\Memory\Pool;

class TcpClientRequestCoroutine extends CoroutineBase
{
    /**
     * @var TcpClientPool
     */
    public $pool;
    public $data;
    public $token;

    public function __construct()
    {
        parent::__construct();
    }

    public function init($pool, $data)
    {
        $this->pool = $pool;
        $this->data = $data;
        if (!array_key_exists('path', $data)) {
            throw new SwooleException('tcp data must has path');
        }
        $this->request = '[tcpClient]' . $data['path'];
        unset($this->data['path']);
        if ($this->fuse()) {//启动断路器
            $this->send(function ($result) {
                $this->result = $result;
                $this->immediateExecution();
            });
        }
        return $this;
    }

    public function send($callback)
    {
        $this->token = $this->pool->send($this->data, $callback);
    }

    public function destory()
    {
        parent::destory();
        unset($this->pool);
        unset($this->data);
        unset($this->token);
        Pool::getInstance()->push(TcpClientRequestCoroutine::class, $this);
    }

    protected function onTimerOutHandle()
    {
        parent::onTimerOutHandle();
        $this->pool->destoryGarbage($this->token);
    }
}