<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Block\Checkout\Onepage\Success;

use Bitpay\BPCheckout\Model\Client;
use Bitpay\BPCheckout\Model\Config;
use Bitpay\BPCheckout\Model\TransactionRepository;
use BitPaySDK\Model\Invoice\Invoice;
use Magento\Checkout\Block\Onepage\Success as MagentoSuccess;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;

class PayButton extends MagentoSuccess
{
    /**
     * @var TransactionRepository
     */
    protected TransactionRepository $transactionRepository;

    /**
     * @var Client
     */
    protected Client $client;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $url;

    /**
     * @var Invoice|null
     */
    protected ?Invoice $invoice = null;

    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderConfig $orderConfig
     * @param HttpContext $httpContext
     * @param TransactionRepository $transactionRepository
     * @param Client $client
     * @param Config $config
     * @param UrlInterface $url
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderConfig $orderConfig,
        HttpContext $httpContext,
        TransactionRepository $transactionRepository,
        Client $client,
        Config $config,
        UrlInterface $url,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);

        $this->transactionRepository = $transactionRepository;
        $this->client = $client;
        $this->config = $config;
        $this->url = $url;
    }

    /**
     * Returns true when Pay button be displayed
     *
     * @return bool
     */
    public function canViewPayButton(): bool
    {
        if ($this->config->getBitpayCheckoutSuccess() === 'standard'
            && $this->config->getBitpayInvoiceCloseHandling() === 'keep_order') {
            $invoice = $this->getBitpayInvoice();
            
            return $invoice !== null;
        }

        return false;
    }

    /**
     * Returns button url
     *
     * @return string
     */
    public function getButtonUrl(): string
    {
        return $this->url->getUrl('bpcheckout/invoice/pay', [
            '_query' => [
                'order_id' => $this->getOrder()->getId(), 'invoice_id' => $this->getBitpayInvoice()->getId()
            ]
        ]);
    }

    /**
     * Get BitPay invoice by last order
     *
     * @return Invoice|null
     */
    protected function getBitpayInvoice(): ?Invoice
    {
        if (!$this->invoice) {
            $order = $this->getOrder();
            if ($order->canInvoice()) {
                $transactions = $this->transactionRepository
                    ->findByOrderIdAndTransactionStatus($order->getIncrementId(), 'new');
                if (!empty($transactions)) {
                    $lastTransaction = array_pop($transactions);
                    $client = $this->client->initialize();
                    $invoice = $client->getInvoice($lastTransaction['transaction_id']);
                    
                    $this->invoice = $invoice;
                }
            }
        }

        return $this->invoice;
    }

    /**
     * Get order instance based on last order ID
     *
     * @return Order
     */
    protected function getOrder(): Order
    {
        return $this->_checkoutSession->getLastRealOrder();
    }
}
