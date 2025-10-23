<?php

declare(strict_types=1);

namespace OCA\UserIprotek\Service;

use OCP\IRequest;
use OCP\ISession;
use OCP\Security\ISecureRandom;

if (class_exists(__NAMESPACE__ . '\\BrowserService')) {
    return;
}
class BrowserService {
    public function __construct(
        private IRequest $request,
        private ISession $session,
        private ISecureRandom $random
    ) {}

    public function getBrowserId(): string {
        // Check if we already have one stored in session
        if ($this->session->exists('browser_id')) {
            return $this->session->get('browser_id');
        }

        // Build fingerprint
        $userAgent = $this->request->getHeader('User-Agent') ?? '';
        $ip = $this->request->getRemoteAddress() ?? '';
        $acceptLang = $this->request->getHeader('Accept-Language') ?? '';

        // Create a hashed fingerprint + random salt
        $fingerprint = hash('sha256', $userAgent . '|' . $ip . '|' . $acceptLang);
        $browserId = $fingerprint . ':' . $this->random->generate(8, ISecureRandom::CHAR_HUMAN_READABLE);

        // Store for reuse
        $this->session->set('browser_id', $browserId);

        return $browserId;
    }
}