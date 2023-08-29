<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;

class PrepaymentOrderItemExtension extends DataExtension
{
    public function updateSubTableTitle()
    {
        $owner = $this->getOwner();
        $product = $owner->Product();
        if($product->IsOnPresale()) {
            return 'Prepayment of '.$product->PrepaymentPercentage.'% only.';
        } else {
            $memberPrepaidAmount = $product->getMemberPrepaidAmount();
            if($memberPrepaidAmount) {
                $memberPrepaidAmountObject = DBField::create_field('Currency', $memberPrepaidAmount);
                return 'Prepayment of '.$memberPrepaidAmountObject->Nice().' deducted from price.';

            }
        }
        return null;
    }

    public function updateTableTitle()
    {
        return null;
    }


}
