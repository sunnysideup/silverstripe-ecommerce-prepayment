<?php

namespace Sunnysideup\EcommercePrepayment\Model;

use Demo\Product;
use SilverStripe\ORM\DataObject;

class PrepaymentMessage extends DataObject
{
    private static $db = [
        'Message' => 'HTMLText',
        'Send' => 'Boolean',
        'Sent' => 'Boolean',
    ];

    private static $has_one = [
        'PrepaymentHolder' => PrepaymentHolder::class,
    ];
}
