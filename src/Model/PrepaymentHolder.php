<?php

namespace Sunnysideup\EcommercePrepayment\Model;

use Demo\Product;
use SilverStripe\ORM\DataObject;

class PrepaymentHolder extends DataObject
{
    private static $db = [
        'PrepaidAmount' => 'Currency',
    ];

    private static $has_one = [
        'Member' => Member::class,
        'Buyable' => Product::class,
    ];
    private static $has_many = [
        'PrepaymentMessages' => PrepaymentMessage::class,
    ];
}
