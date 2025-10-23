<?php
namespace OCA\UserIprotek\AppInfo;

use OCP\AppFramework\App;
use OCA\UserIprotek\iProtekBackend;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IDBConnection;

class Application extends App{

    private $logger;
    private $dbConnection;

    public function __construct(array $urlParams = []) {

        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // Load your custom config file
        $configPath = __DIR__ . '/../config/config.php';
        if (file_exists($configPath)) {
            $GLOBALS['USER_IPROTEK_CONFIG'] = include $configPath;
        }


        //parent::__construct('user_iprotek', $urlParams);

        // Get the container
        $container = $this->getContainer();

        // Get logger from container
        $this->logger = $container->get(LoggerInterface::class);
        $this->dbConnection = $container->get(IDBConnection::class);

        // Get UserManager from server
        $userManager = $container->getServer()->getUserManager();

        // ✅ STEP 1: Temporarily store all existing backends
        $existingBackends = $userManager->getBackends();

        // ✅ STEP 2: Remove them
        foreach ($existingBackends as $backend) {
            $userManager->removeBackend($backend);
        }

        // ✅ STEP 3: Register YOUR backend first
        $userManager->registerBackend(new iProtekBackend($this->dbConnection, $this->logger));

        // ✅ STEP 4: Re-register the others (Database, LDAP, etc.)
        //foreach ($existingBackends as $backend) {
        //    $userManager->registerBackend($backend);
        //}

        // Register your backend here
        //$this->logger->error("iProtekBackend registered as highest priority.:".count($existingBackends));
        $container->registerService('Command.Migrate', function($c) {
            return new \OCA\UserIprotek\Command\Migrate(
                $c->query(\OCP\IDBConnection::class)
            );
        });
    
    }

    public function register(IRegistrationContext $context): void {
        /*
        $context->registerService('Command.Migrate', function($c) {
            return new \OCA\UserIprotek\Command\Migrate(
                $c->query(\OCP\IDBConnection::class)
            );
        });
        */
    }

    public function registerCommands(\Symfony\Component\Console\Application $application) {
        $application->add($this->getContainer()->query('Command.Migrate'));
    }


    public function boot(IBootContext $context): void {

        return;
    }

}