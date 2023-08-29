<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Member;
use Sunnysideup\Ecommerce\Model\Config\EcommerceDBConfig;
use Sunnysideup\Ecommerce\Model\ProductOrderItem;
use Sunnysideup\Ecommerce\Pages\Product;

class PrepaymentConfigExtension extends DataExtension
{
    private static $db = [
        'PrepaymentMessageWithProduct' => 'HTMLText',
    ];



    /**
     * Update Fields.
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Price',
            [
                HTMLEditorField::create('PrepaymentMessageWithProduct', 'Generic Prepayment Message')
                    ->setDescription('This message will be shown on all products that have a prepayment percentage set and are not yet for sale proper.'),
            ]
        );
    }


}
