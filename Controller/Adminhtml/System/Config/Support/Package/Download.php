<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Controller\Adminhtml\System\Config\Support\Package;

use Bitpay\BPCheckout\Model\SupportPackage;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Response\Http\FileFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;

/**
 * Controller used for downloading Support Package
 */
class Download extends Action implements HttpPostActionInterface
{
    /**
     * Download constructor
     *
     * @param Context $context
     * @param SupportPackage $supportPackage
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        private SupportPackage $supportPackage,
        private FileFactory $fileFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute request
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        $archivePath = $this->supportPackage->prepareDownloadArchive();
        $content = [
            'type' => 'filename',
            'value' => $archivePath,
            'rm' => true,
        ];

        return $this->fileFactory->create('bitpay-support.zip', $content);
    }
}
