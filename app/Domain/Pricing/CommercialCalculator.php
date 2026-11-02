<?php
declare(strict_types=1);

namespace App\Domain\Pricing;

final class CommercialCalculator
{
    /** @return array{unit_price:string,quantity:string,subtotal:string,tax:string,total:string,tax_rate:string,tax_included:bool} */
    public function calculate(string $unitPrice, string $quantity, string $taxRate, bool $taxIncluded): array
    {
        $price=Decimal::parse($unitPrice,4,'El precio');$qty=Decimal::parse($quantity,6,'La cantidad');$rate=Decimal::parse($taxRate,4,'La tasa');
        if($price->compare(Decimal::parse('99999999999.9999',4))>0||$qty->isZero()||$rate->compare(Decimal::parse('100.0000',4))>0)throw new \InvalidArgumentException('Los valores del cálculo están fuera del rango permitido.');
        $gross=$price->multiply($qty)->round(2);$hundred=Decimal::parse('100',0);
        if($taxIncluded){$subtotal=$rate->isZero()?$gross:$gross->multiply($hundred)->divide($hundred->add($rate),2);$tax=$gross->subtract($subtotal);$total=$gross;}
        else{$subtotal=$gross;$tax=$subtotal->multiply($rate)->divide($hundred,2);$total=$subtotal->add($tax)->round(2);}
        return ['unit_price'=>$price->format(4),'quantity'=>$qty->format(6),'subtotal'=>$subtotal->format(2),'tax'=>$tax->format(2),'total'=>$total->format(2),'tax_rate'=>$rate->format(4),'tax_included'=>$taxIncluded];
    }
}
