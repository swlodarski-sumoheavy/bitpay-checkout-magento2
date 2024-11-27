<?php

declare(strict_types=1);

namespace Bitpay\BPCheckout\Test\Unit\Model\Ipn;

use Bitpay\BPCheckout\Model\Ipn\WebhookVerifier;
use PHPUnit\Framework\TestCase;

class WebhookVerifierTest extends TestCase
{
    /**
     * @var WebhookVerifier $webhookVerifier
     */
    private $webhookVerifier;

    public function setUp(): void
    {
        $this->webhookVerifier = new WebhookVerifier();
    }

    public function testIsValidHmac(): void
    {
        $this->assertTrue(
            $this->webhookVerifier->isValidHmac(
                'testkey',
                'SKEpFPexQ4ko9QAEre51+n+ypvQQidUheDl3+4irEOQ=',
                '{"data":{"test":true}'
            )
        );
    }

    public function testIsValidHmacFalse(): void
    {
        $this->assertFalse(
            $this->webhookVerifier->isValidHmac(
                'differentkey',
                'SKEpFPexQ4ko9QAEre51+n+ypvQQidUheDl3+4irEOQ=',
                '{"data":{"test":true}'
            )
        );
    }
}
