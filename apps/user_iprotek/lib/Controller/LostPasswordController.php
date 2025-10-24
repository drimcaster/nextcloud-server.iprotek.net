<?php
namespace OCA\UserIprotek\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use OCP\EventDispatcher\IEventDispatcher;
use OCA\UserIprotek\Events\ForgotPasswordEvent;
class LostPasswordController extends Controller {
    private LoggerInterface $logger;
    private IEventDispatcher $dispatcher;

    public function __construct(string $appName, IRequest $request, LoggerInterface $logger, IEventDispatcher $dispatcher) {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function email(string $user = ''): DataResponse {
        $this->logger->error("UserIprotek: custom LostPasswordController::email() for {$user}");
        $this->dispatcher->dispatchTyped(new ForgotPasswordEvent($user));

        // You can still forward to the core handler if you want
        // $core = \OC::$server->query(\OC\Core\Controller\LostPasswordController::class);
        // return $core->email($user);

        return new DataResponse(['status' => 'ok', 'user' => $user]);
    }
}