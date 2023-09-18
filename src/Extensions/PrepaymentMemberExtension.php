<?php

namespace Sunnysideup\EcommercePrepayment\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Member;
use Sunnysideup\Ecommerce\Interfaces\BuyableModel;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\EcommercePrepayment\Model\PrepaymentHolder;

class PrepaymentMemberExtension extends DataExtension
{
    private static $has_many = [
        'PrepaidAmounts' => PrepaymentHolder::class,
    ];

    public function getPrepaidAmount(BuyableModel $buyable, ?Order $order = null): ?float
    {
        $owner = $this->getOwner();
        if($owner->PrepaidAmounts()->exists()) {
            return $owner->PrepaidAmounts()->filter(['BuyableID' => $buyable->ID, 'MemberID' => $owner->ID])->sum('PrepaidAmountPaid');
        }
        return 0;
    }



}
