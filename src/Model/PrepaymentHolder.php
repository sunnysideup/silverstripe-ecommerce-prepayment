<?php

namespace Sunnysideup\EcommercePrepayment\Model;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
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
            'PrepaidAmount' => $amount,
        ];
        $obj = self::get()->filter($filter)->first();
        if(! $obj) {
            $obj = self::create($filter);
        }
        $obj->write();
        return $obj;
    }

    private static $table_name = 'PrepaymentHolder';
    private static $db = [
        'PrepaidAmount' => 'Currency',
        'Completed' => 'Boolean',
    ];

    private static $has_one = [
        'Member' => Member::class,
        'Buyable' => Product::class,
        'Order' => Order::class,
    ];
    private static $has_many = [
        'PrepaymentMessages' => PrepaymentMessage::class,
    ];

    private static $summary_fields = [
        'Created' => 'Created',
        'PrepaidAmount' => 'Prepaid Amount',
        'Member.Email' => 'Member',
        'Buyable.Title' => 'Buyable',
    ];

    private static $indexes = [
        // 'PrepaidAmount' => true,
    ];
    private static $casting = [
        'LoginLink' => 'Varchar',
    ];

    public function getLoginAndAddToCartLink(): string
    {
        return Director::absoluteURL('/Security/login?BackURL='.$this->Product()->addLink());
    }

    public function getOrderLink(): string
    {
        return Director::absoluteURL('/Security/login?BackURL='.$this->Product()->addLink());
    }
}
