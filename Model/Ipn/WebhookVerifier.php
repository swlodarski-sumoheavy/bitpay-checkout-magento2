<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Model\Ipn;

class WebhookVerifier
{
    /**
     * Verify the validity of webhooks (HMAC)
     *
     * @see https://developer.bitpay.com/reference/hmac-verification
     *
     * @param string $signingKey
     * @param string $sigHeader
     * @param string $webhookBody
     *
     * @return bool
     */
    public function isValidHmac(string $signingKey, string $sigHeader, string $webhookBody): bool
    {
        $hmac = base64_encode(
            hash_hmac(
                'sha256',
                $webhookBody,
                $signingKey,
                true
            )
        );

        return $sigHeader === $hmac;
    }
}
