<?php
declare(strict_types=1);

namespace Salecto\AutoCancel\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;

class PaymentMethodsColumn extends Select
{
    /**
     * Payment Helper Data
     *
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentHelper;
     
    /**
     * Payment Model Config
     *
     * @var \Magento\Payment\Model\Config
     */
    protected $_paymentConfig;
    
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_paymentConfig = $paymentConfig;
        parent::__construct($context, $data);
    }

    /**
     * Set "name" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    /**
     * Creates an array of payment methods for dropdown
     *
     * @return array
     */
    private function getSourceOptions(): array
    {
        /*
         * to get list of all active payment modethods
         *
         * $methods = $this->_paymentConfig->getActiveMethods();
         */
        $methods = $this->_paymentHelper->getPaymentMethodList();
        foreach($methods as $key=>$title){
            $options[] = [
                'label' => $title, 'value' => $key,
            ];
        }
        return $options;
    }
}
