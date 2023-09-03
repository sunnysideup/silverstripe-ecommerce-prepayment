<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\ORM\DataExtension;

class PrepaymentOrderItemExtension extends DataExtension
{
    public function updateSubTableTitle($title)
    {
        $owner = $this->getOwner();
        $product = $owner->Product();
        if($product->IsOnPresale()) {
            $fullPriceAsMoney = $product->CalculatedPriceAsMoney();
            $remainingAmountAsMoney = $product->getPostPresaleAmountAsMoney($owner->Quantity);
            return '
                <strong>Prepayment only.
                Full price is '.$fullPriceAsMoney->Nice(). ' per item.
                <br />Remaining amount to be paid on arrival is '.$remainingAmountAsMoney->Nice().'.</strong>';
        } else {
            $amount = $product->getNextAmountForMember();
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
        if($product->IsOnPresale() || $product->IsPostPresale()) {
            return $product->getNextAmountForMember();
        }
        return null;
    }

    public function runUpdateExtension($orderItem)
    {
        $owner = $this->getOwner();
        $product = $owner->Product();
        if($product->IsOnPresale()) {
            $test = (bool) $orderItem->HasPhysicalDispatch;
            if($test !== false) {
                $orderItem->HasPhysicalDispatch = false;
                $orderItem->write();
            }
        }
    }


}
