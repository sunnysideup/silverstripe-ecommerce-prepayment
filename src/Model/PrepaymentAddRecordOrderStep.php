<?php

namespace Sunnysideup\EcommercePrepayment\Model;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use Sunnysideup\Ecommerce\Email\OrderStatusEmail;
use Sunnysideup\Ecommerce\Interfaces\OrderStepInterface;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Model\Process\OrderStep;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\EcommercePrepayment\Extensions\PrepaymentProductExtension;

/**
 *
 * @property bool $SendMessageToCustomer
 */
class PrepaymentAddRecordOrderStep extends OrderStep implements OrderStepInterface
{
    /**
     * @var string
     */
    protected $emailClassName = OrderStatusEmail::class;

    private static $table_name = 'PrepaymentAddRecordOrderStep';

    private static $db = [
        'SendMessageToCustomer' => 'Boolean',
    ];

    private static $defaults = [
        'CustomerCanEdit' => 0,
        'CustomerCanCancel' => 0,
        'CustomerCanPay' => 0,
        'Name' => 'Prepayment add record',
        'Code' => 'PREPAYMENT_ADD_RECORD',
        'ShowAsInProcessOrder' => 1,
        'SendMessageToCustomer' => 0,
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.CustomerMessage',
            [
                CheckboxField::create(
                    'SendMessageToCustomer',
                    'Send message to Customer to notify of prepayment taken?'
                ),
            ]
        );

        return $fields;
    }

    /**
     *
     * @param Order $order object
     *
     * @return bool - true if the current step is ready to be run...
     */
    public function initStep(Order $order): bool
    {
        return $order->IsSubmitted();
    }

    /**
     * @return bool
     */
    public function doStep(Order $order): bool
    {
        foreach ($order->OrderItems() as $orderItem) {
            if ($orderItem->PrepaymentStatus === PrepaymentProductExtension::PREPAYMENT_STATUS_ON_PRESALE) {
                /** @var Product $buyable */
                $buyable = $orderItem->Buyable();
                PrepaymentHolder::add_prepayment_holder(
                    $order,
                    $buyable,
                    $order->Member(),
                    $orderItem->CalculatedTotal
                );
            } elseif($orderItem->PrepaymentStatus === PrepaymentProductExtension::PREPAYMENT_STATUS_POST_PRESALE) {
                /** @var Product $buyable */
                $buyable = $orderItem->Buyable();
                PrepaymentHolder::close_prepayment_holder(
                    $order,
                    $buyable,
                    $order->Member(),
                    $orderItem->CalculatedTotal
                );
            }
        }
        $adminOnlyOrToEmail = ! (bool) $this->SendMessageToCustomer;

        return (bool) $this->sendEmailForStep(
            $order,
            $subject = $this->EmailSubject,
            $message = $this->CustomerMessage,
            $resend = false,
            $adminOnlyOrToEmail,
            $this->getEmailClassName()
        );
    }

    /**
     * Allows the opportunity for the Order Step to add any fields to Order::getCMSFields.
     *
     * @return \SilverStripe\Forms\FieldList
     */
    public function addOrderStepFields(FieldList $fields, Order $order, ?bool $nothingToDo = false)
    {
        return parent::addOrderStepFields($fields, $order, true);
    }

    /**
     * For some ordersteps this returns true...
     *
     * @return bool
     */
    public function hasCustomerMessage()
    {
        return $this->SendMessageToCustomer;
    }

    /**
     * Explains the current order step.
     *
     * @return string
     */
    protected function myDescription()
    {
        return _t('OrderStep.PREPAYMENT_ADD_RECORD_DESCRIPTION', 'Record prepayment for pre-sale products.');
    }
}
