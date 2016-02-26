<?php

namespace Kraken\Runtime\Container\Provider\Console;

use Kraken\Channel\ChannelBaseInterface;
use Kraken\Channel\ChannelCompositeInterface;
use Kraken\Channel\Router\RuleHandler;
use Kraken\Core\CoreInterface;
use Kraken\Core\Service\ServiceProvider;
use Kraken\Core\Service\ServiceProviderInterface;
use Kraken\Runtime\Runtime;

class ConsoleProvider extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * @var string[]
     */
    protected $requires = [
        'Kraken\Config\ConfigInterface',
        'Kraken\Channel\ChannelFactoryInterface',
        'Kraken\Runtime\RuntimeInterface',
        'Kraken\Command\CommandManagerInterface'
    ];

    /**
     * @var string[]
     */
    protected $provides = [
        'Kraken\Runtime\RuntimeConsoleInterface'
    ];

    /**
     * @param CoreInterface $core
     */
    protected function register(CoreInterface $core)
    {
        $config  = $core->make('Kraken\Config\ConfigInterface');
        $factory = $core->make('Kraken\Channel\ChannelFactoryInterface');
        $runtime = $core->make('Kraken\Runtime\RuntimeInterface');

        $console = $factory->create('Kraken\Channel\ChannelBase', [
            $runtime->parent() === null
                ? $config->get('channel.channels.console.class')
                : 'Kraken\Channel\Model\Null\NullModel',
            array_merge(
                $config->get('channel.channels.console.config'),
                [ 'hosts' => Runtime::RESERVED_CONSOLE_CLIENT ]
            )
        ]);

        $core->instance(
            'Kraken\Runtime\RuntimeConsoleInterface',
            $console
        );
    }

    /**
     * @param CoreInterface $core
     */
    protected function unregister(CoreInterface $core)
    {
        $core->remove(
            'Kraken\Runtime\RuntimeConsoleInterface'
        );
    }

    /**
     * @param CoreInterface $core
     */
    protected function boot(CoreInterface $core)
    {
        $runtime = $core->make('Kraken\Runtime\RuntimeInterface');
        $channel = $core->make('Kraken\Runtime\RuntimeChannelInterface');
        $console = $core->make('Kraken\Runtime\RuntimeConsoleInterface');

        $this->applyConsoleRouting($channel, $console);

        $runtime->on('create', [ $console, 'start' ]);
        $runtime->on('destroy', [ $console, 'stop' ]);
    }

    /**
     * @param ChannelCompositeInterface $channel
     * @param ChannelBaseInterface $console
     */
    private function applyConsoleRouting(ChannelCompositeInterface $channel, ChannelBaseInterface $console)
    {
        $master = $channel->bus('master');

        $router = $console->input();
        $router->addAnchor(
            new RuleHandler(function($params) use($master) {
                $master->receive(
                    $params['alias'],
                    $params['protocol']
                );
            })
        );

        $router = $console->output();
        $router->addAnchor(
            new RuleHandler(function($params) use($channel) {
                $channel->push(
                    $params['alias'],
                    $params['protocol'],
                    $params['flags'],
                    $params['success'],
                    $params['failure'],
                    $params['cancel'],
                    $params['timeout']
                );
            })
        );
    }
}