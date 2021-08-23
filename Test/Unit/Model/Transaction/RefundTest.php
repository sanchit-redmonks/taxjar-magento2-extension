<?php

declare(strict_types=1);

namespace Taxjar\SalesTax\Test\Unit\Model\Transaction;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\MockObject\MockObject;
use Taxjar\SalesTax\Model\Transaction\Refund as TaxjarRefund;
use Taxjar\SalesTax\Test\Unit\UnitTestCase;

class RefundTest extends UnitTestCase
{
    /**
     * @var Order|MockObject
     */
    private $mockOrder;

    /**
     * @var Creditmemo|MockObject
     */
    private $mockCreditMemo;

    public function setUp(): void
    {
        $this->mockOrder = $this->createMock(Order::class);
        $this->mockOrder->expects($this->once())->method('getIncrementId')->willReturn('99999');

        $this->mockCreditMemo = $this->createMock(Creditmemo::class);
        $this->mockCreditMemo->expects($this->once())->method('getSubtotal')->willReturn(30);
        $this->mockCreditMemo->expects($this->once())->method('getShippingAmount')->willReturn(5);
        $this->mockCreditMemo->expects($this->once())->method('getDiscountAmount')->willReturn(3);
        $this->mockCreditMemo->expects($this->once())->method('getTaxAmount')->willReturn(2.5);
        $this->mockCreditMemo->expects($this->once())->method('getAdjustment')->willReturn(0);
        $this->mockCreditMemo->expects($this->once())->method('getIncrementId')->willReturn('999');
        $this->mockCreditMemo->expects($this->once())->method('getCreatedAt')->willReturn('2021-01-01');
        $this->mockCreditMemo->expects($this->once())->method('getAllItems')->willReturn([]);
        $this->mockCreditMemo->expects($this->once())->method('getAdjustmentNegative')->willReturn(0);
        $this->mockCreditMemo->expects($this->once())->method('getAdjustmentPositive')->willReturn(15);
    }

    public function testBuildReturnsArray()
    {
        /** @var TaxjarRefund|MockObject $sut */
        $sut = $this->getTaxjarRefundObject();

        $result = $sut->build($this->mockOrder, $this->mockCreditMemo);

        $this->assertIsArray($result);
    }

    public function testBuildPayloadContainsOrderAndCreditMemoData()
    {
        /** @var TaxjarRefund|MockObject $sut */
        $sut = $this->getTaxjarRefundObject();

        $result = $sut->build($this->mockOrder, $this->mockCreditMemo);

        // Validate base refund data from Order and Creditmemo
        $this->assertSame('magento', $result['plugin']);
        $this->assertSame('test-provider', $result['provider']);
        $this->assertSame('999-refund', $result['transaction_id']);
        $this->assertSame('99999', $result['transaction_reference_id']);
        $this->assertSame('2021-01-01', $result['transaction_date']);
        $this->assertSame(32.0, $result['amount']);
        $this->assertSame(2.0, $result['shipping']);
        $this->assertSame(2.5, $result['sales_tax']);
        $this->assertSame(2.0, $result['shipping']);
    }

    public function testBuildPayloadContainsFromAddress()
    {
        /** @var TaxjarRefund|MockObject $sut */
        $sut = $this->getTaxjarRefundObject();

        $result = $sut->build($this->mockOrder, $this->mockCreditMemo);

        // Validate origin address
        $this->assertSame('US', $result['from_country']);
        $this->assertSame('01888', $result['from_zip']);
        $this->assertSame('MA', $result['from_state']);
        $this->assertSame('Woburn', $result['from_city']);
        $this->assertSame('462 Washington St', $result['from_street']);
    }

    public function testBuildPayloadContainsToAddress()
    {
        /** @var TaxjarRefund|MockObject $sut */
        $sut = $this->getTaxjarRefundObject();

        $result = $sut->build($this->mockOrder, $this->mockCreditMemo);

        // Validate destination address
        $this->assertSame('US', $result['to_country']);
        $this->assertSame('94103', $result['to_zip']);
        $this->assertSame('CA', $result['to_state']);
        $this->assertSame('San Francisco', $result['to_city']);
        $this->assertSame('510 Townsend St', $result['to_street']);
    }

    public function testBuildPayloadContainsArrayKeyLineItemsAsArray()
    {
        /** @var TaxjarRefund|MockObject $sut */
        $sut = $this->getTaxjarRefundObject();

        $result = $sut->build($this->mockOrder, $this->mockCreditMemo);

        $this->assertIsArray($result['line_items']);
        $this->assertEquals(2, count($result['line_items']));
    }

    public function testBuildPayloadContainsOrderLineItems()
    {
        /** @var TaxjarRefund|MockObject $sut */
        $sut = $this->getTaxjarRefundObject();

        $result = $sut->build($this->mockOrder, $this->mockCreditMemo);

        // Validate Order Item data
        $this->assertSame('12345', $result['line_items'][0]['id']);
        $this->assertSame(2, $result['line_items'][0]['quantity']);
        $this->assertSame('test_product', $result['line_items'][0]['product_identifier']);
        $this->assertSame('Lorem ipsum dolor set amet', $result['line_items'][0]['description']);
        $this->assertSame(15.0, $result['line_items'][0]['unit_price']);
        $this->assertSame(0.0, $result['line_items'][0]['discount']);
        $this->assertSame(1.2, $result['line_items'][0]['sales_tax']);
        $this->assertSame('31000', $result['line_items'][0]['product_tax_code']);
    }

    public function testBuildPayloadContainsAdjustmentLineItem()
    {
        /** @var TaxjarRefund|MockObject $sut */
        $sut = $this->getTaxjarRefundObject();

        $result = $sut->build($this->mockOrder, $this->mockCreditMemo);

        $this->assertIsArray($result['line_items']);
        $this->assertEquals(2, count($result['line_items']));

        // Validate Adjustment Item data (NOTE: values are NOT cast to `float` like order item above)
        $this->assertSame('adjustment-refund', $result['line_items'][1]['id']);
        $this->assertSame(1, $result['line_items'][1]['quantity']);
        $this->assertSame('adjustment-refund', $result['line_items'][1]['product_identifier']);
        $this->assertSame('Adjustment Refund', $result['line_items'][1]['description']);
        $this->assertSame(15, $result['line_items'][1]['unit_price']);
        $this->assertSame(0, $result['line_items'][1]['discount']);
        $this->assertSame(0, $result['line_items'][1]['sales_tax']);
    }

    private function getTaxjarRefundObject()
    {
        /** @var TaxjarRefund|MockObject $sut */
        $sut = $this->createPartialMock(TaxjarRefund::class, [
            'getProvider',
            'buildFromAddress',
            'buildToAddress',
            'buildLineItems',
            'buildCustomerExemption',
        ]);
        $sut->expects($this->once())->method('getProvider')->willReturn('test-provider');
        $sut->expects($this->once())->method('buildFromAddress')->willReturn([
            'from_country' => 'US',
            'from_zip' => '01888',
            'from_state' => 'MA',
            'from_city' => 'Woburn',
            'from_street' => '462 Washington St',
        ]);
        $sut->expects($this->once())->method('buildToAddress')->willReturn([
            'to_country' => 'US',
            'to_zip' => '94103',
            'to_state' => 'CA',
            'to_city' => 'San Francisco',
            'to_street' => '510 Townsend St',
        ]);
        $sut->expects($this->once())->method('buildLineItems')->willReturn([
            'line_items' => [
                [
                    'id' => '12345',
                    'quantity' => 2,
                    'product_identifier' => 'test_product',
                    'description' => 'Lorem ipsum dolor set amet',
                    'unit_price' => 15.0,
                    'discount' => 0,
                    'sales_tax' => 1.2,
                    'product_tax_code' => '31000',
                ],
            ]
        ]);
        $sut->expects($this->once())->method('buildCustomerExemption')->willReturn([]);

        return $sut;
    }
}
