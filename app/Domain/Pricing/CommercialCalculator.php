<?php
declare(strict_types=1);

namespace App\Domain\Pricing;

final class CommercialCalculator
{
    /** Additional discounts use the same M04 fiscal calculation and cent rounding. */
    public function calculateDiscounted(string $unitPrice,string $quantity,string $taxRate,bool $taxIncluded,string $discountRate):array
    {
        $rate=PricingRules::rate($discountRate);
        $base=$this->calculate($unitPrice,$quantity,$taxRate,$taxIncluded);
        $gross=Decimal::parse($taxIncluded?$base['total']:$base['subtotal'],2);
        $discount=$gross->multiply(Decimal::parse($rate,4))->divide(Decimal::parse('100',0),2);
        $net=$this->calculateGross($gross->subtract($discount),Decimal::parse($taxRate,4),$taxIncluded);
        return $base+['discount_rate'=>$rate,'discount_amount'=>Decimal::parse($base['subtotal'],2)->subtract(Decimal::parse($net['subtotal'],2))->format(2),'taxable_amount'=>$net['subtotal'],'tax_amount'=>$net['tax'],'discounted_total'=>$net['total']];
    }

    /** @return array{unit_price:string,quantity:string,subtotal:string,tax:string,total:string,tax_rate:string,tax_included:bool} */
    public function calculate(string $unitPrice, string $quantity, string $taxRate, bool $taxIncluded): array
    {
        $price=Decimal::parse($unitPrice,4,'El precio');$qty=Decimal::parse($quantity,6,'La cantidad');$rate=Decimal::parse($taxRate,4,'La tasa');
        if($price->compare(Decimal::parse('99999999999.9999',4))>0||$qty->isZero()||$rate->compare(Decimal::parse('100.0000',4))>0)throw new \InvalidArgumentException('Los valores del cálculo están fuera del rango permitido.');
        $gross=$price->multiply($qty)->round(2);
        $totals=$this->calculateGross($gross,$rate,$taxIncluded);
        return ['unit_price'=>$price->format(4),'quantity'=>$qty->format(6),'subtotal'=>$totals['subtotal'],'tax'=>$totals['tax'],'total'=>$totals['total'],'tax_rate'=>$rate->format(4),'tax_included'=>$taxIncluded];
    }
    private function calculateGross(Decimal $gross,Decimal $rate,bool $taxIncluded):array
    {
        $hundred=Decimal::parse('100',0);
        if($taxIncluded){$subtotal=$rate->isZero()?$gross:$gross->multiply($hundred)->divide($hundred->add($rate),2);$tax=$gross->subtract($subtotal);$total=$gross;}
        else{$subtotal=$gross;$tax=$subtotal->multiply($rate)->divide($hundred,2);$total=$subtotal->add($tax)->round(2);}
        return ['subtotal'=>$subtotal->format(2),'tax'=>$tax->format(2),'total'=>$total->format(2)];
    }
}
