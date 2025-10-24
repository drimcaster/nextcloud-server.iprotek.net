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
use OCA\UserIprotek\Middleware\LostRedirectMiddleware;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\AppFramework\Http\Events\BeforeControllerEvent;
use OCP\AppFramework\Http\RedirectResponse;

if (class_exists(__NAMESPACE__ . '\\Application')) {
    return;
}

$dispatcher = \OC::$server->query(IEventDispatcher::class);
$dispatcher->addListener(BeforeControllerEvent::class, function (BeforeControllerEvent $event) {
    $request = \OC::$server->getRequest();
    $path = $request->getPathInfo();

    // Intercept the core lostpassword route
        die("GG");
    if ($path === '/lostpassword/email') {
        $urlGen = \OC::$server->getURLGenerator();
        $customUrl = $urlGen->linkToRouteAbsolute('user_iprotek.lost.email');

        $event->setResponse(new RedirectResponse($customUrl));
    }
});


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
    }


    public function boot(IBootContext $context): void { 
    }

}