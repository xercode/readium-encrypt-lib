<?php


namespace xeBook\Readium\Encrypt\Command;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\RegisterEnvVarProcessorsPass;
use Symfony\Component\DependencyInjection\Compiler\ReplaceAliasByActualDefinitionPass;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class Application extends BaseApplication
{
    /**
     * Show last git tag
     */
    private const GIT_LAST_TAG = 'git describe --abbrev=0 --tags';

    private $commandsRegistered = false;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var bool
     */
    private $debug;

    /**
     * The current app version
     * @var string
     */
    private $version = null;

    protected $startTime;

    /**
     * @var string
     */
    private $rootDir;


    public function __construct(string $environment, bool $debug)
    {
        if ($this->version === null) {
            $this->version = exec(self::GIT_LAST_TAG, $output);
        }

        parent::__construct('readium-encrypt-tool', $this->version);

        $this->environment  = $environment;
        $this->debug        = $debug;
        $this->rootDir      = $this->getRootDir();

        if ($this->debug) {
            $this->startTime = microtime(true);
        }

        $this->registerCommands();
    }

    protected function registerCommands()
    {
        if ($this->commandsRegistered) {
            return;
        }

        $this->commandsRegistered = true;

        $this->boot();
        $container = $this->getContainer();

        if ($container->has('console.command_loader')) {
            $this->setCommandLoader($container->get('console.command_loader'));
        }

    }

    private function boot()
    {
        if($this->container !== null ) {
            return $this->container;
        }

        foreach (array('cache' => $this->getCacheDir(), 'logs' => $this->getLogDir()) as $name => $dir) {
            if (!is_dir($dir)) {
                if (false === @mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new \RuntimeException(sprintf("Unable to create the %s directory (%s)\n", $name, $dir));
                }
            } elseif (!is_writable($dir)) {
                throw new \RuntimeException(sprintf("Unable to write in the %s directory (%s)\n", $name, $dir));
            }
        }

        $container = $this->createContainer();
        $services_dir = $container->getParameter('app.services_dir');
        $xmlFileLocator = $xmlFileLocator  =  new FileLocator($services_dir);

        $loader = new XmlFileLoader($container, $xmlFileLocator);
        $finder = new Finder();
        $finder->files()->in($services_dir);
        foreach ($finder as $file) {
            $loader->load($file->getRelativePathname());

        }

        $container->addCompilerPass(new RegisterEnvVarProcessorsPass());
        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->addCompilerPass(new ReplaceAliasByActualDefinitionPass());
        $container->addCompilerPass(new MessengerPass());

        $dns        = $container->getParameter('amqp.dsn');


        $config = [
            'buses'      => ['messenger.bus.default' => ['default_middleware' => true, 'middleware' => []]],
            'transports' => ['amqp' => ['dsn' => $dns, 'options' => []]],
            'routing'    => [\xeBook\Readium\Encrypt\Message\EncryptedResource::class => ['senders' => ['amqp'], 'send_and_handle' => false]],
            'enabled'    => true,
            'serializer'    => ['enabled' => true, 'format' => 'json', 'context' => []],
            'encoder'    => 'messenger.transport.serializer',
            'decoder'    => 'messenger.transport.serializer',
            'default_bus'    => [],
        ];

        $this->registerMessengerConfiguration($config, $container);

        $container->compile(true);

        return $this->container = $container;
    }

    private function registerMessengerConfiguration(array $config, ContainerBuilder $container)
    {
        // TODO REVIEW bus default and queue config
        if (!interface_exists(MessageBusInterface::class)) {
            throw new LogicException('Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".');
        }

        if (null === $config['default_bus'] && 1 === \count($config['buses'])) {
            $config['default_bus'] = key($config['buses']);
        }

        $defaultMiddleware = [
            'before' => [
                ['id' => 'add_bus_name_stamp_middleware'],
                ['id' => 'dispatch_after_current_bus'],
                ['id' => 'failed_message_processing_middleware'],
            ],
            'after' => [
                ['id' => 'send_message'],
                ['id' => 'handle_message'],
            ],
        ];
        foreach ($config['buses'] as $busId => $bus) {
            $middleware = $bus['middleware'];

            if ($bus['default_middleware']) {
                if ('allow_no_handlers' === $bus['default_middleware']) {
                    $defaultMiddleware['after'][1]['arguments'] = [true];
                } else {
                    unset($defaultMiddleware['after'][1]['arguments']);
                }

                // argument to add_bus_name_stamp_middleware
                $defaultMiddleware['before'][0]['arguments'] = [$busId];

                $middleware = array_merge($defaultMiddleware['before'], $middleware, $defaultMiddleware['after']);
            }

            $container->setParameter($busId.'.middleware', $middleware);
            $container->register($busId, MessageBus::class)->addArgument([])->addTag('messenger.bus');

            $container->registerAliasForArgument($busId, MessageBusInterface::class);
        }


        $senderAliases = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializerId = $transport['serializer'] ?? 'messenger.default_serializer';

            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([$transport['dsn'], $transport['options'] + ['transport_name' => $name], new Reference($serializerId)])
                ->addTag('messenger.receiver', ['alias' => $name])
            ;
            $container->setDefinition($transportId = 'messenger.transport.'.$name, $transportDefinition);
            $senderAliases[$name] = $transportId;
        }

        $messageToSendersMapping = [];
        foreach ($config['routing'] as $message => $messageConfiguration) {
            if ('*' !== $message && !class_exists($message) && !interface_exists($message, false)) {
                throw new LogicException(sprintf('Invalid Messenger routing configuration: class or interface "%s" not found.', $message));
            }

            // make sure senderAliases contains all senders
            foreach ($messageConfiguration['senders'] as $sender) {
                if (!isset($senderAliases[$sender])) {
                    $senderAliases[$sender] = $sender;
                }
            }

            $messageToSendersMapping[$message] = $messageConfiguration['senders'];
        }

        $senderReferences = [];
        foreach ($senderAliases as $alias => $serviceId) {
            $senderReferences[$alias] = new Reference($serviceId);
        }

        $container->getDefinition('messenger.senders_locator')
            ->replaceArgument(0, $messageToSendersMapping)
            ->replaceArgument(1, ServiceLocatorTagPass::register($container, $senderReferences))
        ;
    }
    /**
     * Returns the default Application parameters.
     *
     * @return array An array of Application parameters
     */
    protected function getApplicationParameters()
    {
        return [
            'app.root_dir'      => realpath($this->rootDir) ?: $this->rootDir,
            'app.project_dir'   => realpath($this->getProjectDir()),
            'app.config_dir'    => realpath($this->getConfigDir()),
            'app.environment'   => $this->environment,
            'app.debug'         => $this->debug,
            'app.name'          => $this->getName(),
            'app.cache_dir'     => realpath($this->getCacheDir()),
            'app.logs_dir'      => realpath($this->getLogDir()),
            'app.charset'       => $this->getCharset(),
            'app.services_dir' => realpath($this->getConfigDir().'/services'),
        ];
    }

    private function createContainer()
    {

        $container = new ContainerBuilder(
            new EnvPlaceholderParameterBag($this->getApplicationParameters())
        );

        return $container;
    }

    /**
     * Gets the application root dir (path of the project's composer file).
     *
     * @return string The project root dir
     */
    public function getProjectDir()
    {
        if (null === $this->projectDir) {
            $r = new \ReflectionObject($this);
            $dir = $rootDir = \dirname($r->getFileName());
            while (!file_exists($dir.'/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     */
    public function getCacheDir()
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     */
    public function getLogDir()
    {
        return $this->getProjectDir().'/var/logs';
    }

    /**
     * Gets the charset of the application.
     *
     * @return string The charset
     */
    public function getCharset()
    {
        return 'UTF-8';
    }

    /**
     * Gets the current container.
     *
     * @return ContainerInterface|null A ContainerInterface instance or null when the Kernel is shutdown
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Gets the request start time (not available if debug is disabled).
     *
     * @return int The request start timestamp
     */
    public function getStartTime()
    {
        return $this->debug ? $this->startTime : -INF;
    }

    /**
     * Gets the application root dir (path of the project's this class).
     *
     * @return string The Application root dir
     */
    public function getRootDir()
    {
        if (null === $this->rootDir) {
            $this->rootDir = realpath(__DIR__.'/..');
        }

        return $this->rootDir;
    }

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool true if debug mode is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * Gets the environment.
     *
     * @return string The current environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }


    /**
     * Gets the application config dir (path of the project's this class).
     *
     * @return string The Application config dir
     */
    public function getConfigDir()
    {
        return $this->getProjectDir().'/config';
    }
}
