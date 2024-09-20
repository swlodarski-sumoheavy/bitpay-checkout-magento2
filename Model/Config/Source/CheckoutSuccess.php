<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * CheckoutSuccess Model
 */
class CheckoutSuccess implements ArrayInterface
{
    /**
     * Return array of Checkout Success options
     *
     * @return string[]
     */
    public function toOptionArray()
    {
        return [
            'module' => 'Module',
            'standard' => 'Standard',
        ];
    }
}
