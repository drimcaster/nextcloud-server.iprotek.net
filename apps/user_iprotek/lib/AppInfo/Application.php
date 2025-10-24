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
use OCP\AppFramework\IAppContainer;
use OCA\UserIprotek\Service\BrowserService;

if (class_exists(__NAMESPACE__ . '\\Application')) {
    return;
}

class Application extends App implements IBootstrap {

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
        
        $container->registerService(BrowserService::class, function($c) {
            return new BrowserService(
                $c->query(\OCP\IRequest::class),
                $c->query(\OCP\ISession::class),
                $c->query(\OCP\Security\ISecureRandom::class)
            );
        });
        

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
        $this->logger->error("DATA INFO");
        $context->injectFn(function () {
            /** @var \OCP\Route\IRouter $router */
            $router = \OC::$server->get(\OCP\Route\IRouter::class);
            $collection = $router->getCollection();

            // Remove default route if it exists
            if ($collection->get('core.lost.email')) {
                $collection->remove('core.lost.email');
            }

            // Add your custom route
            $route = new Route(
                '/lostpassword/email',
                [
                    '_controller' => 'OCA\\UserIprotek\\Controller\\LostController::email',
                    '_route' => 'core.lost.email',
                ],
                [], [], '', [], ['POST']
            );

            $collection->add('core.lost.email', $route);
        });
    }

}