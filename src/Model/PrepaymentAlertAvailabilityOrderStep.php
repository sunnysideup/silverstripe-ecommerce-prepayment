<?php

namespace Sunnysideup\EcommercePrepayment\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextField;
use SilverStripe\View\SSViewer;
use Sunnysideup\Ecommerce\Email\OrderStatusEmail;
use Sunnysideup\Ecommerce\Interfaces\OrderStepInterface;
use Sunnysideup\Ecommerce\Model\Config\EcommerceDBConfig;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Model\Process\OrderStep;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\EcommercePrepayment\Extensions\PrepaymentProductExtension;

/**
 *
 * @property bool $SendMessageToCustomer
 */
class PrepaymentAlertAvailabilityOrderStep extends OrderStep implements OrderStepInterface
{
    /**
     * @var string
     */
    protected $emailClassName = OrderStatusEmail::class;

    private static $table_name = 'PrepaymentAlertAvailabilityOrderStep';

    private static $db = [
        'SendMessageToCustomer' => 'Boolean',
    ];

    private static $defaults = [
        'CustomerCanEdit' => 0,
        'CustomerCanCancel' => 0,
        'CustomerCanPay' => 0,
        'Name' => 'Prepayment alert availability',
        'Code' => 'PREPAYMENT_ALERT_AVAILABILITY',
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
                    'Send message to Customer when the product is available?'
                ),
                TextField::create(
                    'SubjectForPrepaymentEmail',
                    'Subject for Customer'
                ),
                HTMLEditorField::create(
                    'MessageForPrepaymentEmail',
                    'Message for Customer'
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
                $filter = ['OrderID' => $order->ID, 'BuyableID' => $buyable->ID];
                $prepaymentHolder = PrepaymentHolder::get()->filter($filter)->first();
                if ($prepaymentHolder) {

                    if (!$this->sendNotifications($order, $prepaymentHolder)) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }
        return true;
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
        return _t('OrderStep.RECORD_PREPAYMENT_ALERT_AVAILABILITY', 'Alert customer that product is now available.');
    }

    protected function sendNotifications(Order $order, PrepaymentHolder $prepaymentHolder): bool
    {

        $product = $prepaymentHolder->Product();
        $member = $prepaymentHolder->Member();
        $loginLink = $prepaymentHolder->getLoginAndAddToCartLink();
        if ($product && $member) {
            $renderWithArray = [
                'Product' => $product,
                'Message' => $this->CustomerMessage,
                'Member' => $member,
                'LoginLink' => $loginLink,
            ];
            $themeEnabled = Config::inst()->get(SSViewer::class, 'theme_enabled');
            SSViewer::config()->set('theme_enabled', true);
            $message = $this->renderWith(
                self::class,
                $renderWithArray
            );
            SSViewer::config()->set('theme_enabled', $themeEnabled);
            $adminOnlyOrToEmail = ! (bool) $this->SendMessageToCustomer;

            $outcome = (bool) $this->sendEmailForStep(
                $order,
                (string) $this->EmailSubject,
                (string) $message,
                $resend = false,
                $adminOnlyOrToEmail,
                $this->getEmailClassName()
            );
            if (! $outcome) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }
}
