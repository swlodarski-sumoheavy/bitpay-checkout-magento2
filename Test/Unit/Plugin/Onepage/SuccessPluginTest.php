<?php

namespace Bitpay\BPCheckout\Test\Unit\Plugin\Onepage;

use Bitpay\BPCheckout\Plugin\Onepage\SuccessPlugin;
use Bitpay\BPCheckout\Model\BPRedirect;
use Magento\Checkout\Controller\Onepage\Success;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SuccessPluginTest extends TestCase
{
    public function testAfterExecute(): void
    {
        $bpRedirect = $this->getMockBuilder(BPRedirect::class)->disableOriginalConstructor()->getMock();
        $subject = $this->getMockBuilder(Success::class)->disableOriginalConstructor()->getMock();
        $subject = $this->getMockBuilder(Success::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(RequestInterface::class)->disableOriginalConstructor()->getMock();
        $request->expects($this->once())->method('getParam')->willReturn(null);
        $subject->expects($this->once())->method('getRequest')->willReturn($request);
        $result = $this->getMockBuilder(ResultInterface::class)->disableOriginalConstructor()->getMock();
        $testedClass = new SuccessPlugin($bpRedirect);

        $bpRedirect->expects(self::once())->method('execute');

        $testedClass->afterExecute($subject, $result);
    }
}
