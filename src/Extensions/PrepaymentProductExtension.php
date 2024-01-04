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
use SilverStripe\ORM\FieldType\DBPercentage;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\Ecommerce\Api\ShoppingCart;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\EcommercePrepayment\Model\PrepaymentHolder;

class PrepaymentProductExtension extends DataExtension
{
    public const PREPAYMENT_STATUS_NORMAL = 'Normal';
    public const PREPAYMENT_STATUS_ON_PRESALE = 'On Presale';
    public const PREPAYMENT_STATUS_POST_PRESALE = 'Post Presale Unlimited Availability';
    private static $db = [
        'PrepaymentStatus' => 'Enum("' . self::PREPAYMENT_STATUS_NORMAL . ',' . self::PREPAYMENT_STATUS_ON_PRESALE . ', ' . self::PREPAYMENT_STATUS_POST_PRESALE . '", "' . self::PREPAYMENT_STATUS_NORMAL . '")',
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
            'Root.PrePayment',
            [
                DropdownField::create('PrepaymentStatus', 'Prepayment Status', $owner->dbObject('PrepaymentStatus')->enumValues())
                    ->setDescription('It is important that, when you take a product off Presale, you select the Post Presale option so that people with Presale can purchase the product with a discount if they have pre-paid.'),
                CurrencyField::create('PrepaymentFixed', 'Prepayment Fixed Amount'),
            ]
        );
        if($this->HasPrepaymentConditions()) {
            $fields->addFieldsToTab(
                'Root.PrePayment',
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

        return $owner->PrepaymentStatus !== PrepaymentProductExtension::PREPAYMENT_STATUS_NORMAL;
    }

    public function IsOnPresale(): bool
    {
        $owner = $this->getOwner();
        if($this->HasPrepaymentConditions()) {
            return $owner->PrepaymentStatus === PrepaymentProductExtension::PREPAYMENT_STATUS_ON_PRESALE;

        }

        return false;
    }

    public function IsPostPresale(): bool
    {
        $owner = $this->getOwner();
        if($this->HasPrepaymentConditions()) {
            return $owner->PrepaymentStatus === PrepaymentProductExtension::PREPAYMENT_STATUS_POST_PRESALE;
        }
        return false;
    }


    public function getMemberPrepaidAmount(): float
    {
        $owner = $this->getOwner();
        $member = Security::getCurrentUser();
        if(! $member) {
            $order = ShoppingCart::current_order();
            if($order) {
                if($order->MemberID) {
                    $member = $order->Member();
                }
                if(! $member) {
                    $email = $order->BillingAddress()->Email;
                    $member = Member::get()->filter(['Email' => $email])->first();
                }
            }
        }
        if ($member) {
            return (float) $member->getPrepaidAmount($owner, ShoppingCart::current_order());
        }

        return (float) 0;
    }


    /**
     * @param float $price
     *
     * @return null|float
     */
    public function getNextAmountForMember(): ?float
    {
        $owner = $this->getOwner();
        $price = $owner->getCalculatedPrice();
        if($this->IsOnPresale()) {
            return $this->getPresaleAmount();
        } elseif($this->IsPostPresale()) {
            return $this->getPostPresaleAmountForMember();
        }
        return null;
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function getPresaleAmount(?int $quantity = 1): ?float
    {
        $owner = $this->getOwner();
        $price = $owner->getCalculatedPrice();
        if ($owner->PrepaymentFixed) {
            return $owner->PrepaymentFixed * $quantity;
        }
        return $price;
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function getPostPresaleAmount(?int $quantity = 1): ?float
    {
        $owner = $this->getOwner();
        $price = $owner->getCalculatedPrice();
        return ($price - $this->getPresaleAmount()) * $quantity;
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function getPostPresaleAmountForMember(): ?float
    {
        $owner = $this->getOwner();
        $price = $owner->getCalculatedPrice();
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
    public function getPresaleAmountAsMoney(?int $quantity = 1): ?DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getPresaleAmount($quantity));
    }

    /**
     * @param float $price
     *
     * @return null|DBMoney
     */
    public function getMemberPrepaidAmountAsMoney(): ?DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getMemberPrepaidAmount());
    }

    /**
     * @param float $price
     *
     * @return null|DBMoney
     */
    public function getNextAmountForMemberAsMoney(): ?DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getNextAmountForMember());
    }

    /**
     * @param float $price
     *
     * @return null|DBMoney
     */
    public function getPostPresaleAmountAsMoney(?int $quantity = 1): ?DBMoney
    {
        return EcommerceCurrency::get_money_object_from_order_currency($this->getPostPresaleAmount($quantity));
    }



}
