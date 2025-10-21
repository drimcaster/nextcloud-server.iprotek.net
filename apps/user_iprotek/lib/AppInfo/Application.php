<?php
namespace OCA\UserIprotek\AppInfo;

use OCP\AppFramework\App;
use OCA\UserIprotek\iProtekBackend;
use Psr\Log\LoggerInterface;
use OCP\IDBConnection;

class Application extends App {
    public function __construct(array $urlParams = []) {
        parent::__construct('user_iprotek', $urlParams);

        // Get the container
        $container = $this->getContainer();

        // Get logger from container
        $logger = $container->get(LoggerInterface::class);
        $dbConnection = $container->get(IDBConnection::class);

        // Get UserManager from server
        $userManager = $container->getServer()->getUserManager();

        // Register your backend here
        $userManager->registerBackend(new iProtekBackend( $dbConnection, $logger), 100);
    }

    public function register(IRegistrationContext $context): void {
    }

}