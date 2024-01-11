<?php

namespace Sunnysideup\EcommercePrepayment\Model;

use Respect\Validation\Helpers\CanValidateDateTime;
use SilverStripe\Control\Director;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use Sunnysideup\CmsEditLinkField\Forms\Fields\CMSEditLinkField;
use Sunnysideup\CMSNiceties\Forms\CMSNicetiesLinkButton;
use Sunnysideup\Ecommerce\Interfaces\BuyableModel;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Pages\Product;

class PrepaymentHolder extends DataObject
{
    public static function add_prepayment_holder(Order $order, BuyableModel $buyable, Member $member, float $amount)
    {

        $filter = [
            'OrderID' => $order->ID,
            'MemberID' => $member->ID,
            'BuyableID' => $buyable->ID,
            'PrepaidAmountPaid' => $amount,
        ];
        $obj = self::get()->filter($filter)->first();
        if(! $obj) {
            $obj = self::create($filter);
        }
        $obj->write();
        return $obj;
    }
    public static function close_prepayment_holder(Order $order, BuyableModel $buyable, Member $member, float $amount)
    {

        $filter = [
            'MemberID' => $member->ID,
            'BuyableID' => $buyable->ID,
        ];
        $obj = self::get()->filter($filter)->first();
        if(! $obj) {
            $obj = self::add_prepayment_holder($order, $buyable, $member, $amount);
            $obj->Note = 'Error';
        }
        $obj->ClosingOrderID = $order->ID;
        $obj->Completed = true;
        $obj->ClosingAmountPaid = $amount;
        $obj->write();
        return $obj;
    }

    private static $table_name = 'PrepaymentHolder';
    private static $db = [
        'PrepaidAmountPaid' => 'Currency',
        'ClosingAmountPaid' => 'Currency',
        'Completed' => 'Boolean',
        'Note' => 'Varchar',
    ];

    private static $has_one = [
        'Order' => Order::class,
        'ClosingOrder' => Order::class,
        'Member' => Member::class,
        'Buyable' => Product::class,
    ];

    private static $summary_fields = [
        'Created' => 'Created',
        'PrepaidAmountPaid.Nice' => 'Prepaid Amount',
        'Member.Email' => 'Member',
        'Buyable.Title' => 'Buyable',
    ];

    private static $indexes = [
        'PrepaidAmountPaid' => true,
        'Completed' => true,
    ];
    private static $casting = [
        'LoginAndAddToCartLink' => 'Varchar',
        'ViewOrderLink' => 'Varchar',
        'Title' => 'Varchar',
    ];

    public function Product()
    {
        return $this->Buyable();
    }

    public function getLoginAndAddToCartLink(): string
    {
        return Director::absoluteURL('/Security/login?BackURL=' . $this->Buyable()->Link());
    }

    public function getViewOrderLink(): string
    {
        return Director::absoluteURL($this->Order()->Link());
    }

    public function getClosingViewOrderLink(): string
    {
        return Director::absoluteURL($this->Order()->Link());
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('OrderID', CMSEditLinkField::create('OrderID', 'Order', $this->Order()));
        $fields->replaceField('MemberID', CMSEditLinkField::create('MemberID', 'Customer', $this->Member()));
        $fields->replaceField('BuyableID', CMSEditLinkField::create('BuyableID', 'Product', $this->Buyable()));
        $fields->replaceField('ClosingOrderID', CMSEditLinkField::create('ClosingOrderID', 'Completion Order', $this->ClosingOrder()));
        $fields->addFieldsToTab(
            'Root.Main',
            [
                CMSNicetiesLinkButton::create('LoginAndAddToCartLink', 'Login and add to Cart', $this->getLoginAndAddToCartLink(), true),
                CMSNicetiesLinkButton::create('ViewOrderLink', 'View Order', $this->getViewOrderLink(), true),
                CMSNicetiesLinkButton::create('ViewClosingOrderLink', 'View Closing Order', $this->getClosingViewOrderLink(), true),
            ]
        );
        return $fields;
    }

}
