<?php
declare(strict_types=1);
namespace OCA\UserIprotek\AppInfo;

use OCP\AppFramework\App;
use OCA\UserIprotek\iProtekBackend;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IDBConnection;

if (class_exists(__NAMESPACE__ . '\\Application')) {
    return;
}

class Application extends App{

    private $logger;
    private $dbConnection;
    public const APP_ID = 'user_iprotek';

    public function __construct(array $urlParams = []) {

        parent::__construct(self::APP_ID, $urlParams);

        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // Load your custom config file
        $configPath = __DIR__ . '/../config/config.php';
        if (file_exists($configPath)) {
            $GLOBALS['USER_IPROTEK_CONFIG'] = include $configPath;
        }



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
        /*
        $container->registerService('Command.Migrate', function($c) {
            return new \OCA\UserIprotek\Command\Migrate(
                $c->query(\OCP\IDBConnection::class)
            );
        });
        
        //$manager = \OC::$server->get(\OCP\Migration\IRegistrationContext::class);
        //$manager->registerMigration('user_iprotek', 'Migration\Version0001Date20241023');
        */
    }
        

    public function register(IRegistrationContext $context): void {
        /*
        $context->registerService('Command.Migrate', function($c) {
            return new \OCA\UserIprotek\Command\Migrate(
                $c->query(\OCP\IDBConnection::class)
            );
        });*/ 
    }

    public function registerCommands(\Symfony\Component\Console\Application $application) {
        //$application->add($this->getContainer()->query('Command.Migrate'));
    }


    public function boot(IBootContext $context): void {

    }

}