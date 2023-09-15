<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;

class PrepaymentOrderItemExtension extends DataExtension
{
    private static $db = [
        'PrepaymentStatus' => 'Enum("Normal, On Presale, Post Presale Unlimited Availability", "Normal")',
    ];

    public function updateSubTableTitle($title)
    {
        $owner = $this->getOwner();
        $product = $owner->Product();
        if($product) {
            if($product->IsOnPresale()) {
                $fullPriceAsMoney = $product->CalculatedPriceAsMoney();
                $remainingAmountAsMoney = $product->getPostPresaleAmountAsMoney($owner->Quantity);
                return '
                    <strong>Prepayment only.
                    Full price is '.$fullPriceAsMoney->Nice(). ' per item.
                    <br />Remaining amount to be paid on arrival is '.$remainingAmountAsMoney->Nice().'.</strong>';
            } elseif($product->IsPostPresale()) {
                $amount = $product->getNextAmountForMember();
                if((float) $amount !== (float) 0) {
                    $fullPriceAsMoney = $product->CalculatedPriceAsMoney();
                    $memberPrepaidAmount = $product->getMemberPrepaidAmountAsMoney();
                    return '<strong>Prepayment of '.$memberPrepaidAmount->Nice().' deducted from the full price of '.$fullPriceAsMoney->Nice().'.</strong>';

                }
            }
        }
        return null;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('PrepaymentStatus', 'Prepayment Status', SiteTree::class),
            ]
        );
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
        if($owner->ID !== $orderItem->ID) {
            user_error('ID Mismatch');
        }
        $write = false;
        $product = $owner->Product();
        if($product) {
            if($product->IsOnPresale()) {
                if((bool) $owner->HasPhysicalDispatch !== false) {
                    $write = true;
                }
                $owner->HasPhysicalDispatch = false;
            }
            if($product->PrepaymentStatus !== $orderItem->PrepaymentStatus) {
                $owner->PrepaymentStatus = $product->PrepaymentStatus;
                $write = true;
            }
            if($write) {
                $owner->write();
            }
        }
    }


}
