<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\Security\Security;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
use Sunnysideup\EcommercePrepayment\Model\PrepaymentHolder;

class PrepaymentProductExtension extends DataExtension
{
    private static $db = [
        'PrepaymentStatus' => 'Enum("Normal, On Presale, Post Presale Unlimited Availability", "Normal")',
        'PrepaymentPercentage' => 'Percentage',
        'PrepaymentFixed' => 'Currency',
        'PrepaymentMessageWithProduct' => 'HTMLText',
    ];

    private static $has_many = [
        'PrepaymentHolders' => PrepaymentHolder::class,
    ];


    /**
     * Update Fields.
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        $fields->addFieldsToTab(
            'Root.Price',
            [
                DropdownField::create('PrepaymentStatus', 'Prepayment Status', $owner->dbObject('PrepaymentStatus')->enumValues()),
                CurrencyField::create('PrepaymentFixed', 'Prepayment Fixed Amount'),
                NumericField::create('PrepaymentPercentage', 'Prepayment Percentage of Price'),
                DateField::create('ForSaleFrom', 'For Sale From')
                    ->setDescription('Leave empty if you want to sell the product immediately. Products will go on sale from midnight on the specified date.'),
            ]
        );
        if($this->HasPrepaymentConditions()) {
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


    public function HasPrepaymentConditions(): bool
    {
        $owner = $this->getOwner();
        if(! $owner->canPurchase()) {
            return false;
        }

        return $owner->PrepaymentStatus !== 'Normal';
    }

    public function IsOnPresale(): bool
    {
        $owner = $this->getOwner();
        if($this->HasPrepaymentConditions()) {
            return $owner->PrepaymentStatus === 'On Presale';

        }

        return false;
    }

    public function IsPostPresale(): bool
    {
        $owner = $this->getOwner();
        if($this->HasPrepaymentConditions()) {
            return $owner->PrepaymentStatus !== 'On Presale';
        }
        return false;
    }


    public function getMemberPrepaidAmount(): ?float
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
    public function getPresalePostPresaleAmountForMember(?float $price = null): ?float
    {
        $owner = $this->getOwner();
        $price = $owner->getCalculatedPrice();
        if($this->IsOnPresale()) {
            return $this->getPresaleAmount($price);
        }
        if($this->IsPostPresale()) {
            return $this->getPostPresaleAmountForMember($price);
        }
        return null;
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function getPresaleAmount(?float $price = null): ?float
    {
        $owner = $this->getOwner();
        if(! $price) {
            $price = $owner->getCalculatedPrice();
        }
        if ($owner->PrepaymentPercentage || $owner->PrepaymentFixed) {
            return ($price * $owner->PrepaymentPercentage) + $owner->PrepaymentFixed;
        }
        return $price;
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function getPostPresaleAmount(?float $price = null): ?float
    {
        $owner = $this->getOwner();
        if(! $price) {
            $price = $owner->getCalculatedPrice();
        }
        return $price - $this->getPresaleAmount($price);
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function getPostPresaleAmountForMember(?float $price = null): ?float
    {
        $owner = $this->getOwner();
        if(! $price) {
            $price = $owner->getCalculatedPrice();
        }
        $prepaidAmount = $owner->getMemberPrepaidAmount();
        if($prepaidAmount) {
            return $price - $prepaidAmount;
        }
        return null;
    }

    /**
     * @param float $price
     *
     * @return null|DBMoney
     */
    public function getMemberPrepaidAmountAsMoney(?float $price = null): ?DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getPostPresaleAmount());
    }

    /**
     * @param float $price
     *
     * @return null|DBMoney
     */
    public function getPresalePostPresaleAmountForMemberAsMoney(?float $price = null): ?DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getPresalePostPresaleAmountForMember());
    }

    /**
     * @param float $price
     *
     * @return null|DBMoney
     */
    public function getPostPresaleAmountAsMoney(?float $price = null): ?DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getPostPresaleAmount());
    }



}
