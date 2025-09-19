<?php

namespace Sunnysideup\EcommercePrepayment\Reports;

use SilverStripe\Reports\Report;
use Sunnysideup\Ecommerce\Model\Process\OrderStep;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Reports\EcommerceProductReportTrait;
use Sunnysideup\EcommercePrepayment\Extensions\PrepaymentProductExtension;

/**
 * Selects all products without an image.
 *
 * @author: Nicolaas [at] Sunny Side Up .co.nz
 * @package: ecommerce
 * @sub-package: reports
 */
class ProductsOnPresale extends Report
{
    use EcommerceProductReportTrait;

    protected $dataClass = Product::class;

    /**
     * @return int - for sorting reports
     */
    public function sort()
    {
        return 7001;
    }

    /**
     * @return string
     */
    public function title()
    {
        return 'E-commerce: Products: on pre-sale';
    }

    /**
     * @param mixed $params
     */
    protected function getEcommerceFilter($params = null): array
    {
        return ['PrepaymentStatus:Not' => PrepaymentProductExtension::PREPAYMENT_STATUS_NORMAL];
    }

    public function updateEcommerceReportColumns(array $columns): array
    {
        $columns['PrepaymentStatus'] = 'Pre-Sale Status';
        $columns['SoldCount'] = 'Sold';

        return $columns;
    }
}
