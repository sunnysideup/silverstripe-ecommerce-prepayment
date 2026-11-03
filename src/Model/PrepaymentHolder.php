<?php

namespace Sunnysideup\EcommercePrepayment\Model;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use Sunnysideup\CmsEditLinkField\Forms\Fields\CMSEditLinkField;
use Sunnysideup\CMSNiceties\Forms\CMSNicetiesLinkButton;
use Sunnysideup\Ecommerce\Interfaces\BuyableModel;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Pages\Product;

class PrepaymentHolder extends DataObject
{
    public static function add_prepayment_holder(Order $order, BuyableModel $buyable, Member $member, float $amount)
    {

        $filter = [
            'OrderID' => $order->ID,
            'MemberID' => $member->ID,
            'BuyableID' => $buyable->ID,
            'PrepaidAmountPaid' => $amount,
        ];
        $obj = self::get()->filter($filter)->first();
        if(! $obj) {
            $obj = self::create($filter);
        }
        $obj->write();
        return $obj;
    }
    public static function close_prepayment_holder(Order $order, BuyableModel $buyable, Member $member, float $amount)
    {
        $baseFilter = [
            'MemberID' => $member->ID,
            'BuyableID' => $buyable->ID,
        ];

        // this order has already closed the credit (e.g. the order step is run twice): nothing more to do.
        $alreadyClosed = self::get()->filter($baseFilter + ['ClosingOrderID' => $order->ID])->first();
        if ($alreadyClosed) {
            return $alreadyClosed;
        }

        // Claim ALL open holders for this customer and product in ONE statement.
        // The database makes sure that two orders that are submitted at the same time can never both claim the same credit:
        // whichever UPDATE runs second finds nothing left to claim.
        $table = DataObject::getSchema()->tableName(self::class);
        DB::prepared_query(
            'UPDATE "' . $table . '" SET "Completed" = 1, "ClosingOrderID" = ?, "LastEdited" = ?
                WHERE "MemberID" = ? AND "BuyableID" = ? AND "Completed" = 0',
            [(int) $order->ID, date('Y-m-d H:i:s'), (int) $member->ID, (int) $buyable->ID]
        );

        $claimed = self::get()->filter($baseFilter + ['ClosingOrderID' => $order->ID]);
        if (! $claimed->exists()) {
            // The order was priced with a prepayment discount, but there is no open prepayment (any more):
            // it has been used by another order in the meantime. Staff has to follow up.
            $obj = self::add_prepayment_holder($order, $buyable, $member, $amount);
            $obj->Note = 'Error: no open prepayment left for this customer and product. Check the discount on the order.';
            $obj->ClosingOrderID = $order->ID;
            $obj->Completed = true;
            $obj->ClosingAmountPaid = $amount;
            $obj->write();
            self::alert_admin(
                $order,
                'Order #' . $order->ID . ' was priced with a prepayment discount, but the customer has no open prepayment left for product #' . $buyable->ID . '.'
                . ' It has probably been used by another order. Please check the order.'
            );

            return $obj;
        }

        $obj = null;
        foreach ($claimed as $holder) {
            if (null === $obj) {
                $obj = $holder;
                $holder->ClosingAmountPaid = $amount;
            } else {
                $holder->Note = 'Closed together with holder #' . $obj->ID;
            }
            $holder->write();
        }

        return $obj;
    }

    protected static function alert_admin(Order $order, string $message): void
    {
        try {
            $to = Email::config()->get('admin_email');
            if (is_array($to)) {
                $to = (string) array_key_first($to);
            }
            if ($to) {
                Email::create()
                    ->setTo($to)
                    ->setFrom($to)
                    ->setSubject('Prepayment problem with order #' . $order->ID)
                    ->setBody(Convert::raw2xml($message))
                    ->send()
                ;
            }
        } catch (\Throwable $exception) {
            // never block the order because the alert could not be sent.
        }
    }

    private static $table_name = 'PrepaymentHolder';
    private static $db = [
        'PrepaidAmountPaid' => 'Currency',
        'ClosingAmountPaid' => 'Currency',
        'Completed' => 'Boolean',
        'Note' => 'Varchar',
    ];

    private static $has_one = [
        'Order' => Order::class,
        'ClosingOrder' => Order::class,
        'Member' => Member::class,
        'Buyable' => Product::class,
    ];

    private static $summary_fields = [
        'Created' => 'Created',
        'PrepaidAmountPaid.Nice' => 'Prepaid Amount',
        'Member.Email' => 'Member',
        'Buyable.Title' => 'Buyable',
    ];

    private static $indexes = [
        'PrepaidAmountPaid' => true,
        'Completed' => true,
    ];
    private static $casting = [
        'LoginAndAddToCartLink' => 'Varchar',
        'ViewOrderLink' => 'Varchar',
        'Title' => 'Varchar',
    ];

    public function Product()
    {
        return $this->Buyable();
    }

    public function getLoginAndAddToCartLink(): string
    {
        return Director::absoluteURL('/Security/login?BackURL=' . $this->Buyable()->Link());
    }

    public function getViewOrderLink(): string
    {
        return Director::absoluteURL($this->Order()->Link());
    }

    public function getClosingViewOrderLink(): string
    {
        return Director::absoluteURL($this->Order()->Link());
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('OrderID', CMSEditLinkField::create('OrderID', 'Order', $this->Order()));
        $fields->replaceField('MemberID', CMSEditLinkField::create('MemberID', 'Customer', $this->Member()));
        $fields->replaceField('BuyableID', CMSEditLinkField::create('BuyableID', 'Product', $this->Buyable()));
        $fields->replaceField('ClosingOrderID', CMSEditLinkField::create('ClosingOrderID', 'Completion Order', $this->ClosingOrder()));
        $fields->addFieldsToTab(
            'Root.Main',
            [
                CMSNicetiesLinkButton::create('LoginAndAddToCartLink', 'Login and add to Cart', $this->getLoginAndAddToCartLink(), true),
                CMSNicetiesLinkButton::create('ViewOrderLink', 'View Order', $this->getViewOrderLink(), true),
                CMSNicetiesLinkButton::create('ViewClosingOrderLink', 'View Closing Order', $this->getClosingViewOrderLink(), true),
            ]
        );
        return $fields;
    }

}
