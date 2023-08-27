<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use Sunnysideup\Ecommerce\Interfaces\BuyableModel;

class PrepaymentMemberExtension extends DataExtension
{
    private static $has_many = [
        'PrepaidAmounts' => PrepaymentHolder::class,
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
    }

    public function getPrepaidAmount(BuyableModel $buyable)
    {
        $owner = $this->getOwner();
        return $owner->PrepaidAmounts()->filter(['Buyable' => $buyable->ID])->sum('PrepaidAmount');
    }


    protected function IsOnPresale(): bool
    {
        $owner = $this->getOwner();
        $date = $owner->ForSaleFrom;
        if ($date) {
            return $owner->dbObject('ForSaleFrom')->InPast() ? false : true;
        }

        return false;
    }


    protected function memberPrepaidAmount(): float
    {
        $owner = $this->getOwner();
        $member = Security::getCurrentUser();
        if ($member) {
            return $member->getPrepaidAmount();
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
                $prepaidAmount = $this->memberPrepaidAmount();
                if($prepaidAmount) {
                    return $price - $prepaidAmount;
                }
            }
        }
        return null;
    }

}
