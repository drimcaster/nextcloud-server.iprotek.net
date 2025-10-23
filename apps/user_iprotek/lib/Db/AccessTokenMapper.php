<?php

declare(strict_types=1);

namespace OCA\UserIprotek\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

if (class_exists(__NAMESPACE__ . '\\AccessTokenMapper')) {
    return;
}
class AccessTokenMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'user_iprotek_tokens', AccessToken::class);
    }

    public function findByUser(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('user_iprotek_tokens')
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntities($qb);
    }

    public function findByBrowser(string $browserId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('user_iprotek_tokens')
           ->where($qb->expr()->eq('browser_id', $qb->createNamedParameter($browserId)));

        return $this->findEntities($qb);
    }

    public function findByToken(string $token): ?AccessToken {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('user_iprotek_tokens')
           ->where($qb->expr()->eq('token', $qb->createNamedParameter($token)));

        return $this->findEntity($qb);
    }
}