<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;

class PrepaymentOrderItemExtension extends DataExtension
{
    public function updateSubTableTitle($title)
    {
        $owner = $this->getOwner();
        $product = $owner->Product();
        if($product->IsOnPresale()) {
            $fullPriceAsMoney = $product->CalculatedPriceAsMoney();
            $remainingAmountAsMoney = $product->getPostPresaleAmountAsMoney();
            return '<strong>Prepayment only. Full price is '.$fullPriceAsMoney->Nice(). '. Remaining amount to pay afer this order: '.$remainingAmountAsMoney->Nice().'</strong>';
        } else {
            $amount = $product->PresalePostPresaleAmountForMember();
            if($amount) {
                $fullPriceAsMoney = $product->CalculatedPriceAsMoney();
                $memberPrepaidAmount = $product->getMemberPrepaidAmountAsMoney();
                return '<strong>Prepayment of '.$memberPrepaidAmount->Nice().' deducted from full price ('.$fullPriceAsMoney.').</strong>';

            }
        }
        return null;
    }

    public function updateUnitPrice(float $unitPrice): ?float
    {
        $owner = $this->getOwner();
        $product = $owner->Product();
        if($product->IsOnPresale() || $product->IsOnPostPresale()) {
            return $product->getPresalePostPresaleAmountForMember();
        }
        return null;
    }

    public function runUpdateExtension($orderItem)
    {
        $owner = $this->getOwner();
        $product = $owner->Product();
        if($product->IsOnPresale()) {
            $orderItem->HasPhysicalDispatch = false;
        }
    }


}
