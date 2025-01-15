<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Bitpay Download Support Package block
 */
class DownloadSupportPackage extends Field
{
    private const DOWNLOAD_PATH = 'bitpay/system_config/support_package_download';

    /**
     * @var string
     */
    protected $_template = 'Bitpay_BPCheckout::system/config/fieldset/download_support_package.phtml';

    /**
     * Get download url
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->_urlBuilder->getUrl(
            self::DOWNLOAD_PATH,
            [
                'form_key' => $this->getFormKey(),
            ]
        );
    }

    /**
     * Remove element scope and render form element as HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->setData('scope', null);

        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->addData(
            [
                'button_label' => __($element->getOriginalData()['button_label']),
            ]
        );

        return $this->_toHtml();
    }
}
