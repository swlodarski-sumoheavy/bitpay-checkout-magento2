<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Model;

use Bitpay\BPCheckout\Api\IpnManagementInterface;
use Bitpay\BPCheckout\Exception\IPNValidationException;
use Bitpay\BPCheckout\Exception\HMACVerificationException;
use Bitpay\BPCheckout\Logger\Logger;
use Bitpay\BPCheckout\Model\Ipn\BPCItem;
use Bitpay\BPCheckout\Model\Ipn\Validator;
use Bitpay\BPCheckout\Model\Ipn\WebhookVerifier;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class IpnManagement implements IpnManagementInterface
{
    public const ORDER_STATUS_PENDING = 'pending';

    protected ResponseFactory $responseFactory;
    protected UrlInterface $url;
    protected Session $checkoutSession;
    protected QuoteFactory $quoteFactory;
    protected OrderFactory $orderFactory;
    protected Registry $coreRegistry;
    protected Logger $logger;
    protected Config $config;
    protected Json $serializer;
    protected TransactionRepository $transactionRepository;
    protected Invoice $invoice;
    protected Request $request;
    protected Client $client;
    protected Response $response;

    /**
     * @var BitpayInvoiceRepository
     */
    protected BitpayInvoiceRepository $bitpayInvoiceRepository;

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @var WebhookVerifier
     */
    protected WebhookVerifier $webhookVerifier;

    /**
     * @param ResponseFactory $responseFactory
     * @param UrlInterface $url
     * @param Registry $registry
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param QuoteFactory $quoteFactory
     * @param Logger $logger
     * @param Config $config
     * @param Json $serializer
     * @param TransactionRepository $transactionRepository
     * @param Invoice $invoice
     * @param Request $request
     * @param Client $client
     * @param Response $response
     * @param BitpayInvoiceRepository $bitpayInvoiceRepository
     * @param EncryptorInterface $encryptor
     * @param WebhookVerifier $webhookVerifier
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ResponseFactory $responseFactory,
        UrlInterface $url,
        Registry $registry,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        QuoteFactory $quoteFactory,
        Logger $logger,
        Config $config,
        Json $serializer,
        TransactionRepository $transactionRepository,
        Invoice $invoice,
        Request $request,
        Client $client,
        Response $response,
        BitpayInvoiceRepository $bitpayInvoiceRepository,
        EncryptorInterface $encryptor,
        WebhookVerifier $webhookVerifier
    ) {
        $this->coreRegistry = $registry;
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->quoteFactory = $quoteFactory;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->config = $config;
        $this->serializer = $serializer;
        $this->transactionRepository = $transactionRepository;
        $this->invoice = $invoice;
        $this->request = $request;
        $this->client = $client;
        $this->response = $response;
        $this->bitpayInvoiceRepository = $bitpayInvoiceRepository;
        $this->encryptor = $encryptor;
        $this->webhookVerifier = $webhookVerifier;
    }

    /**
     * Handle close invoice and redirect to cart
     *
     * @return string|void
     */
    public function postClose()
    {
        $redirectUrl = $this->url->getUrl('checkout/cart', ['_query' => 'reload=1']);
        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->responseFactory->create();
        try {
            $orderID = $this->request->getParam('orderID', null);
            $order = $this->orderFactory->create()->loadByIncrementId($orderID);
            $orderData = $order->getData();
            $quoteID = $orderData['quote_id'];
            $quote = $this->quoteFactory->create()->loadByIdWithoutStore($quoteID);
            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $this->checkoutSession->replaceQuote($quote);
                $this->coreRegistry->register('isSecureArea', 'true');
                $order->delete();
                $this->coreRegistry->unregister('isSecureArea');
                $response->setRedirect($redirectUrl)->sendResponse();

                return;
            }

            $response->setRedirect($redirectUrl)->sendResponse();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $response->setRedirect($redirectUrl)->sendResponse();
        }
    }

    /**
     * Handle Instant Payment Notification
     *
     * @return string|void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function postIpn()
    {
        try {
            $requestBody = $this->request->getContent();
            $allData = $this->serializer->unserialize($requestBody);
            $data = $allData['data'];
            $event = $allData['event'];
            $orderId = $data['orderId'];

            $bitPayInvoiceData = $this->bitpayInvoiceRepository->getByOrderId($orderId);
            if (!empty($bitPayInvoiceData['bitpay_token'])) {
                $signingKey = $this->encryptor->decrypt($bitPayInvoiceData['bitpay_token']);
                $xSignature = $this->request->getHeader('x-signature');

                if (!$this->webhookVerifier->isValidHmac($signingKey, $xSignature, $requestBody)) {
                    throw new HMACVerificationException('HMAC Verification Failed!');
                }
            }

            $orderInvoiceId = $data['id'];
            $row = $this->transactionRepository->findBy($orderId, $orderInvoiceId);
            $client = $this->client->initialize();
            $invoice = $client->getInvoice($orderInvoiceId);
            $ipnValidator = new Validator($invoice, $data);
            if (!empty($ipnValidator->getErrors())) {
                throw new IPNValidationException(implode(', ', $ipnValidator->getErrors()));
            }

            if (!$row) {
                return;
            }

            $env = $this->config->getBitpayEnv();
            $bitpayToken = $this->config->getToken();
            $item = new BPCItem(
                $bitpayToken,
                new DataObject(['invoiceID' => $orderInvoiceId, 'extension_version' => Config::EXTENSION_VERSION]),
                $env
            );

            $invoiceStatus = $this->invoice->getBPCCheckInvoiceStatus($client, $orderInvoiceId);
            $updateWhere = ['order_id = ?' => $orderId, 'transaction_id = ?' => $orderInvoiceId];
            $this->transactionRepository->update('transaction_status', $invoiceStatus, $updateWhere);
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            switch ($event['name']) {
                case Invoice::COMPLETED:
                    if ($invoiceStatus == 'complete') {
                        $this->invoice->complete($order, $item);
                    }
                    break;

                case Invoice::CONFIRMED:
                    $this->invoice->confirmed($order, $invoiceStatus, $item);
                    break;

                case Invoice::PAID_IN_FULL:
                    #STATE_PENDING
                    $this->invoice->paidInFull($order, $invoiceStatus, $item);
                    break;

                case Invoice::FAILED_TO_CONFIRM:
                    $this->invoice->failedToConfirm($order, $invoiceStatus, $item);
                    break;

                case Invoice::EXPIRED:
                case Invoice::DECLINED:
                    $this->invoice->declined($order, $invoiceStatus, $item);
                    break;

                case Invoice::REFUND_COMPLETE:
                    #load the order to update
                    $this->invoice->refundComplete($order, $item);
                    break;
            }
        } catch (\Exception $e) {
            $this->response->addMessage($e->getMessage(), 500);
            $this->logger->error($e->getMessage());
        }
    }
}
