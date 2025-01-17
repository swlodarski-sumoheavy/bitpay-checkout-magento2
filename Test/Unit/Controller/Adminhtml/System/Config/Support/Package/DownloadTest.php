<?php

declare(strict_types=1);

namespace Bitpay\BPCheckout\Test\Unit\Controller\Adminhtml\System\Config\Support\Package;

use Bitpay\BPCheckout\Controller\Adminhtml\System\Config\Support\Package\Download;
use Bitpay\BPCheckout\Model\SupportPackage;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DownloadTest extends TestCase
{
    public function testExecute(): void
    {
        /**
         * @var Context|MockObject
         */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        /**
         * @var SupportPackage|MockObject
         */
        $supportPackageMock = $this->createMock(SupportPackage::class);
        $supportPackageMock->method('prepareDownloadArchive')->willReturn('/dummy/test/path.zip');

        $responseMock = $this->createMock(ResponseInterface::class);

        /**
         * @var FileFactory|MockObject
         */
        $fileFactoryMock = $this->createMock(FileFactory::class);
        $fileFactoryMock->method('create')->willReturn($responseMock);

        $action = new Download($contextMock, $supportPackageMock, $fileFactoryMock);
        $action->execute();
    }
}
