<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use App\Service\SocketClientService;

/**
 * @Command
 */
class RemoveWsCacheCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('ws:remove-cache');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('清除 WebSocket 客户端 FD 与用户绑定的缓存信息');
    }

    public function handle()
    {
        $socket = new SocketClientService();
        $this->line('此过程可能耗时较长，请耐心等待!', 'info');

        // 获取所有已停止运行的服务ID
        $arr = $socket->getServerRunIdAll(2);

        foreach ($arr as $run_id => $value) {
            go(function () use ($socket, $run_id) {
                $socket->removeRedisCache($run_id);
            });
        }

        $this->line('缓存已清除!', 'info');
    }
}
