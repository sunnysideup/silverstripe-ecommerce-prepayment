<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\ORM\DataExtension;
use Sunnysideup\Ecommerce\Interfaces\BuyableModel;
use Sunnysideup\EcommercePrepayment\Model\PrepaymentHolder;

class PrepaymentMemberExtension extends DataExtension
{
    private static $has_many = [
        'PrepaidAmounts' => PrepaymentHolder::class,
    ];

    public function getPrepaidAmount(BuyableModel $buyable): ?float
    {
        $owner = $this->getOwner();
        if($owner->PrepaidAmounts()->exists()) {
            return $owner->PrepaidAmounts()->filter(['BuyableID' => $buyable->ID])->sum('PrepaidAmount');
        }
        return 1000;
    }



}
