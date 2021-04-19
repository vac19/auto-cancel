<?php

namespace Salecto\AutoCancel\Cron;

use Psr\Log\LoggerInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Api\OrderManagementInterface;

class OrderCancel
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
     * Constructor
     *
     * @param CollectionFactory $orderCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param SerializerInterface $serialize
     * @param OrderRepositoryInterface $orderRepository
     * @param HistoryFactory $orderHistoryFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        SerializerInterface $serialize,
        OrderRepositoryInterface $orderRepository,
        HistoryFactory $orderHistoryFactory,
        OrderManagementInterface $orderManagement,
        LoggerInterface $logger
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->serialize = $serialize;
        $this->orderRepository = $orderRepository;
        $this->orderHistoryFactory = $orderHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
    }

    /**
     * Retrieve collection of 'orders' by date, mark cancel collected orders  
     * @return null
     */
	public function execute() {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $enabled = $this->scopeConfig->getValue(self::ENABLE, $storeScope);
        if ($enabled) {
            $flagDate = $this->scopeConfig->getValue(self::FLAG_DATE_AFTER, $storeScope);
            if ($flagDate && $flagDate !== null ) {
                $orderStatus = explode(',', $this->scopeConfig->getValue(self::ORDER_STATUS, $storeScope));
                $payMethods = $this->scopeConfig->getValue(self::PAY_METHODS, $storeScope);
                $methodsAry = $this->serialize->unserialize($payMethods);

                foreach ($methodsAry as $method){
                    $this->getOrdersAndCancelThem($method['method'],$flagDate,$orderStatus);
                }
            }
        }

        $this->logger->info('All Order after `'.$flagDate.'` are canceled');
	}

    /**
     * Gets considtion based orders and set them cancel with a comment.
     *
     * @param $paymentMethod
     * @param $date
     * @param $orderStatus
     * @return void | string
     */
    public function getOrdersAndCancelThem($paymentMethod,$date,$orderStatus) {

        $flagDate = date("Y-m-d h:i:s", strtotime($date));
        $collection = $this->orderCollectionFactory->create();
        $collection->getSelect()
        ->join(
            ["sop" => "sales_order_payment"],
            'main_table.entity_id = sop.parent_id',
            array('method')
        )
        ->where('sop.method = ?',$paymentMethod);

        $collection->addFieldToFilter('updated_at', ['gteq' => $flagDate]);
        $collection->addFieldToSelect('increment_id');
        $collection->addFieldToSelect('status');
        if ($collection->getTotalCount() > 0) {
            $comment = 'Auto Cancel due to inactivity in payment';
            foreach ($collection as $collect) {
                if (in_array($collect->getStatus(), $orderStatus)) {
                    try {
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
                       $this->logger->error($exception->getMessage());
                    }
                }
            }
        }
    }
}