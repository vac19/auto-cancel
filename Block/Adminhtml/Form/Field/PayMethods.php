<?php
namespace Salecto\AutoCancel\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Salecto\AutoCancel\Block\Adminhtml\Form\Field\PaymentMethodsColumn;
use Salecto\AutoCancel\Block\Adminhtml\Form\Field\UnitColumn;

/**
 * Class Ranges
 */
class PayMethods extends AbstractFieldArray
{
    /**
     * @var PaymentMethodsColumn
     */
    private $methodRenderer;

    /**
     * @var UnitColumn
     */
    private $unitRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('method', [
            'label' => __('Payment Methods'),
            'renderer' => $this->getMethodRenderer(), 'class' => 'required-entry']
        );
        $this->addColumn('duration', ['label' => __('Duration'), 'class' => 'required-entry']);
        $this->addColumn('unit', [
            'label' => __('Time Unit'),
            'renderer' => $this->getUnitRenderer(), 'class' => 'required-entry']
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $method = $row->getMethod();
        $unit = $row->getUnit();
        if ($method !== null) {
            $options['option_' . $this->getMethodRenderer()->calcOptionHash($method)] = 'selected="selected"';
        }
        if ($unit !== null) {
            $options['option_' . $this->getUnitRenderer()->calcOptionHash($unit)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @return MethodColumn
     * @throws LocalizedException
     */
    private function getMethodRenderer()
    {
        if (!$this->methodRenderer) {
            $this->methodRenderer = $this->getLayout()->createBlock(
                PaymentMethodsColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->methodRenderer;
    }

    /**
     * @return UnitColumn
     * @throws LocalizedException
     */
    private function getUnitRenderer()
    {
        if (!$this->unitRenderer) {
            $this->unitRenderer = $this->getLayout()->createBlock(
                UnitColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->unitRenderer;
    }
}