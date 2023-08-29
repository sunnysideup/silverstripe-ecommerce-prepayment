<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use Sunnysideup\EcommercePrepayment\Model\PrepaymentHolder;

class PrepaymentProductExtension extends DataExtension
{
    private static $db = [
        'PrepaymentPercentage' => 'Percentage',
        'ForSaleFrom' => 'DBDatetime',
        'PrepaymentMessageWithProduct' => 'HTMLText',
    ];

    private static $has_many = [
        'PrepaymentHolders' => PrepaymentHolder::class,
    ];



    private $discountCouponAmount;

    /**
     * Update Fields.
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Price',
            [
                NumericField::create('PrepaymentPercentage', 'Prepayment Percentage'),
                DateField::create('ForSalFrom', 'For Sale From')
                    ->setDescription('Leave empty if you want to sell the product immediately. Products will go on sale from midnight on the specified date.'),
            ]
        );
        if($this->IsPrepaymentReady()) {
            $fields->addFieldsToTab(
                'Root.Price',
                [
                    HTMLEditorField::create('PrepaymentMessageWithProduct', 'Generic Prepayment Message')
                        ->setDescription('This message will be shown on all products that have a prepayment percentage set and are not yet for sale proper.'),
                    GridField::create(
                        'PrepaymentHolders',
                        'Prepayment Holders',
                        $this->getOwner()->PrepaymentHolders(),
                        GridFieldConfig_RecordViewer::create()
                    ),
                ]
            );
        }
    }


    protected function IsPrepaymentReady(): bool
    {
        $owner = $this->getOwner();
        return $owner->PrepaymentPercentage && $owner->ForSaleFrom;
    }

    protected function IsOnPresale(): bool
    {
        $owner = $this->getOwner();
        if($this->IsPrepaymentReady()) {
            return $owner->dbObject('ForSaleFrom')->InPast() ? false : true;
        }

        return false;
    }

    protected function IsPostPresale(): bool
    {
        $owner = $this->getOwner();
        return $this->IsPrepaymentReady() === true && $this->IsOnPresale() === false;
    }


    public function getMemberPrepaidAmount(): float
    {
        $owner = $this->getOwner();
        $member = Security::getCurrentUser();
        if ($member) {
            return $member->getPrepaidAmount($owner);
        }

        return 0;
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function updateCalculatedPrice(?float $price = null)
    {
        $owner = $this->getOwner();
        if ($owner->PrepaymentPercentage()) {
            if($this->IsOnPresale()) {
                return $price * $owner->PrepaymentPercentage();
            } else {
                $prepaidAmount = $this->getMemberPrepaidAmount();
                if($prepaidAmount) {
                    return $price - $prepaidAmount;
                }
            }
        }
        return null;
    }

    public function CanPurchase($member = null): ?bool
    {
        if($this->IsOnPresale()) {
            return $member || Security::getCurrentUser() ? true : false;
        }
        return null;
    }



}
