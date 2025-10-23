<?php

declare(strict_types=1);

namespace OCA\UserIprotek\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getToken()
 * @method void setToken(string $token)
 * @method string getRefreshToken()
 * @method void setRefreshToken(string $refreshToken)
 * @method string getBrowserId()
 * @method void setBrowserId(string $browserId)
 * @method string getExpiresAt()
 * @method void setExpiresAt(string $expiresAt)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
if (class_exists(__NAMESPACE__ . '\\AccessToken')) {
    return;
}
class AccessToken extends Entity {
    protected $userId;
    protected $token;
    protected $refreshToken;
    protected $browserId;
    protected $expiresAt;
    protected $createdAt;

    public function __construct() {
        $this->addType('id', 'integer');
    }
}