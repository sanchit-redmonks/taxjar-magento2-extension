<?php declare(strict_types=1);
/**
 * Taxjar_SalesTax
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Taxjar
 * @package    Taxjar_SalesTax
 * @copyright  Copyright (c) 2017 TaxJar. TaxJar is a trademark of TPS Unlimited, Inc. (http://www.taxjar.com)
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace Taxjar\SalesTax\Plugin;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Taxjar\SalesTax\Model\Transaction\Refund;

class AdjustmentFeeApplicator
{
    /**
     * In this plugin, we are free to modify the returned values from `Model\Transaction\Refund::build`
     * so long as return type is not changed. This plugin utilizes an "after-" method and this application
     * follows similar logic to that in `Model\Transaction\Refund::build` for the purposes of "undo"-ing
     * the same changes that would've occurred due to its original execution. After un-distributing the
     * adjustment fee, we are free to modify the request body as necessary before returning it.
     *
     * @param Refund $refund
     * @param array $result
     * @param OrderInterface $order
     * @param CreditmemoInterface $creditmemo
     * @return array
     */
    public function afterBuild(
        Refund $refund,
        array $result,
        OrderInterface $order,
        CreditmemoInterface $creditmemo
    ): array {
        $subtotal = (float) $creditmemo->getSubtotal();
        $discount = (float) $creditmemo->getDiscountAmount();
        $adjustmentFee = $creditmemo->getAdjustmentNegative();
        $itemDiscounts = 0;

        if (isset($result['line_items'])) {
            foreach ($result['line_items'] as $k => $lineItem) {
                if ($subtotal != 0) {
                    $lineItemSubtotal = $lineItem['unit_price'] * $lineItem['quantity'];
                    $result['line_items'][$k]['discount'] -= ($adjustmentFee * ($lineItemSubtotal / $subtotal));
                }

                $itemDiscounts += $lineItem['discount'];
            }
        }

        if ((abs($discount) - $itemDiscounts) > 0) {
            $shippingDiscount = abs($discount) - $itemDiscounts;
            $result['shipping'] += $shippingDiscount;
        }

        $result['shipping'] += $adjustmentFee;

        $refund->request = $result;

        return $result;
    }
}
