<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Sales\Api\Data\OrderInterface;

class ReturnHash extends AbstractHelper
{
    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor
    ) {
        $this->encryptor = $encryptor;

        parent::__construct($context);
    }

    /**
     * Generates return hash
     *
     * @param OrderInterface $order
     * @return string
     */
    public function generate(OrderInterface $order): string
    {
        return $this->encryptor->hash(
            "{$order->getIncrementId()}:{$order->getCustomerEmail()}:{$order->getProtectCode()}"
        );
    }

    /**
     * Checks if returnHash is valid
     *
     * @param string $returnHashToCheck
     * @param OrderInterface $order
     * @return bool
     */
    public function isValid(string $returnHashToCheck, OrderInterface $order): bool
    {
        return $returnHashToCheck === $this->generate($order);
    }
}
