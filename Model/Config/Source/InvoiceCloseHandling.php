<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * InvoiceCloseHandling Model
 */
class InvoiceCloseHandling implements OptionSourceInterface
{
    /**
     * Return array of Checkout Success options
     *
     * @return string[]
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'delete_order', 'label' =>  __('Delete Order')],
            ['value' => 'keep_order', 'label' =>  __('Keep Order')],
        ];
    }
}
