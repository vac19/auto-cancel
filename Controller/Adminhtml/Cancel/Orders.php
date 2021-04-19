<?php

namespace Salecto\AutoCancel\Controller\Adminhtml\Cancel;

use Psr\Log\LoggerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Backend\App\Action;

class Orders extends Action
{
    /**
     * enable config path
     */
    const ENABLE = 'salecto_autoCancel/general/enabled';

    /**
     * consider cancel date config path
     */
    const FLAG_DATE_AFTER = 'salecto_autoCancel/general/cancelDate';

    /**
     * order status(s) config path
     */
    const ORDER_STATUS = 'salecto_autoCancel/general/orderStatus';

    /**
     * Gateways config path
     */
    const PAY_METHODS = 'salecto_autoCancel/general/payMethods';

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var SerializerInterface
     */
    protected $serialize;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var HistoryFactory
     */
    protected $orderHistoryFactory;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @param Action\Context $context
     * @param CollectionFactory $orderCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param SerializerInterface $serialize
     * @param OrderRepositoryInterface $orderRepository
     * @param HistoryFactory $orderHistoryFactory
     * @param DateTime $date
     * @param LoggerInterface $logger
     */
    public function __construct(
        Action\Context $context,
        CollectionFactory $orderCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        SerializerInterface $serialize,
        OrderRepositoryInterface $orderRepository,
        HistoryFactory $orderHistoryFactory,
        OrderManagementInterface $orderManagement,
        DateTime $date,
        LoggerInterface $logger
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->serialize = $serialize;
        $this->orderRepository = $orderRepository;
        $this->orderHistoryFactory = $orderHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
        $this->date = $date;
        parent::__construct($context);
    }
 
    /**
     * Cancel orders and set comment.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $enabled = $this->scopeConfig->getValue(self::ENABLE, $storeScope);
        if ($enabled) {
            $configDate = $this->scopeConfig->getValue(self::FLAG_DATE_AFTER, $storeScope);
            $flagDate = $this->date->gmtDate(null,$configDate);
            if ($flagDate) {

                $configStatus = $this->scopeConfig->getValue(self::ORDER_STATUS, $storeScope);
                $orderStatus = ($configStatus) ? explode(',', $configStatus) : null;

                if (is_null($orderStatus)) {
                    $this->messageManager->addError('At least one status required.');
                    return $this->_redirect($this->_redirect->getRefererUrl());
                }
                $payMethods = $this->scopeConfig->getValue(self::PAY_METHODS, $storeScope);
                $methodsAry = ($payMethods) ? $this->serialize->unserialize($payMethods) : null;

                if ($methodsAry) {
                    $methodNames = array_column($methodsAry, 'method');
                    $this->check($flagDate,$orderStatus,$methodNames);
                } else {
                    $this->check($flagDate,$orderStatus);
                }

                die;
                $this->messageManager->addSuccess(__('Auto cancel orders complete!'));
                $this->_redirect($this->_redirect->getRefererUrl());

            } else {
                $this->messageManager->addError('`Consider Cancelation after` is a required field.');
                $this->_redirect($this->_redirect->getRefererUrl());    
            }
        }
    }

    /**
     * Gets considtion based orders and set them cancel and add a comment in order.
     *
     * @param $paymentMethod
     * @param $date
     * @param $orderStatus
     * @return void | string
     */
    public function getOrdersAndCancelThem1($date,$orderStatus = null,$paymentMethod = null) {
        $collection = $this->orderCollectionFactory->create();
        if ($paymentMethod['method']) {
            $collection->getSelect()
            ->join(
                ["sop" => "sales_order_payment"],
                'main_table.entity_id = sop.parent_id',
                array('method')
            )
            ->where('sop.method = ?',$paymentMethod['method']);
        }

        $collection->addFieldToFilter('updated_at', ['gteq' => $date]);
        $collection->addFieldToSelect('increment_id');
        $collection->addFieldToSelect('status');
        $collection->addFieldToSelect('updated_at');

        if ($collection->getTotalCount() > 0) {
            $comment = 'Auto Cancel due to inactivity in payment';
            foreach ($collection as $collect) {

                if (in_array($collect->getStatus(), $orderStatus)) {
                    $canCancelOrder = true;
                    if ($this->checkOrderExpiry($paymentMethod,$collect->getUpdatedAt())) {
                        echo "delete it"; 
                    } else {
                        echo "preserve it"; 
                    }


                    if ($paymentMethod && $this->checkOrderExpiry($paymentMethod,$collect->getUpdatedAt()) == false) {
                            $canCancelOrder = false;
                    }
                    
                    if ($canCancelOrder) {
                        /*try {
                            $order = $this->orderRepository->get($collect->getIncrementId());
                            if ($order->canComment() && $order->getStatus() !== 'canceled') {
                               $history = $this->orderHistoryFactory->create()
                                   ->setStatus(!empty($status) ? $status : $order->getStatus())
                                   ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                                   ->setComment(
                                       __('Comment: %1.', $comment)
                                   );

                               $history->setIsCustomerNotified(false)
                                       ->setIsVisibleOnFront(true);

                               $order->addStatusHistory($history);
                               $this->orderRepository->save($order);
                               $this->orderManagement->cancel($collect->getIncrementId());
                           }
                        } catch (NoSuchEntityException $exception) {
                            $this->messageManager->addError($exception->getMessage());
                            $this->_redirect($this->_redirect->getRefererUrl());
                        }*/    
                    }
                }
            }
        }
    }

    /**
     * Appends hours or days for order's updated date to put condition for collection acordingly.
     *
     * @param $unit
     * @param $duration
     * @return true | false
     */
    public function checkOrderExpiry($paymentMethod,$updatedAt) {
            $checkDate = $this->date->date('Y-m-d H:i:s', 
                strtotime($updatedAt.'+' .$paymentMethod['duration'].$paymentMethod['unit']));
            return (
                $this->date->gmtTimestamp() >= $this->date->gmtTimestamp($checkDate) ? 
                true : false
            );
    }



    /* 
    * Testing for order collection.
    */
    /*public function getOrdersAndCancelThem($date,$orderStatus = null,$paymentMethod = null) {
        var_dump($date);
        echo "<br />";
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('created_at', ['gteq' => $date]);
        $collection->addFieldToSelect('status');
        $collection->addFieldToSelect('created_at');
        $collection->addFieldToSelect('updated_at');
        $collection->addFieldToSelect('increment_id');

        echo "Found Orders -- ".$collection->getTotalCount() . "<br />";

        echo $collection->getSelect()->__toString();
        //var_dump($collection->getSelect());

        foreach ($collection as $collect) {
                echo "<pre>";print_r($collect->getData());
        } die;
    }*/

    /*
    * testing for join query in array sql syntax
    */
    public function check($date,$orderStatus = null,$paymentMethod = null) {
        $collection = $this->orderCollectionFactory->create();
        if ($paymentMethod) {
            $collection->getSelect()
            ->join(
                ["sop" => "sales_order_payment"],
                'main_table.entity_id = sop.parent_id',
                array('method')
            )
            ->where('sop.method IN (?)', $paymentMethod);
        }

        $collection->addFieldToFilter('updated_at', ['gteq' => $date]);
        $collection->addFieldToFilter('status', ['in' => $orderStatus]);
        $collection->addFieldToSelect('increment_id');
        $collection->addFieldToSelect('status');
        $collection->addFieldToSelect('updated_at');


        if ($collection->getTotalCount() > 0) {
            $comment = 'Auto Cancel due to inactivity in payment';
            foreach ($collection as $collect) {
                echo "<pre>";print_r($collect->getData());
            }
        } else {
            echo "no order found";
        }
    }
}
