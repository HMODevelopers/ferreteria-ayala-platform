<?php
declare(strict_types=1);

namespace App\Domain\Pricing;

/** Decimal arithmetic implemented with digit strings; monetary paths never use float. */
final class Decimal
{
    private function __construct(private readonly string $digits, private readonly int $scale) {}

    public static function parse(string $value, int $maxScale, string $label = 'El valor'): self
    {
        $value = trim($value);
        if ($maxScale < 0 || preg_match('/^(0|[1-9]\d*)(?:\.(\d+))?$/D', $value, $matches) !== 1) {
            throw new \InvalidArgumentException($label . ' no es un decimal válido.');
        }
        $fraction = $matches[2] ?? '';
        if (strlen($fraction) > $maxScale) throw new \InvalidArgumentException($label . ' excede la precisión permitida.');
        return new self(self::trimDigits($matches[1] . $fraction), strlen($fraction));
    }

    public static function zero(int $scale = 0): self { return new self('0', $scale); }
    public function isZero(): bool { return $this->digits === '0'; }
    public function compare(self $other): int { [$a,$b] = $this->aligned($other); return self::compareDigits($a,$b); }

    public function add(self $other): self
    {
        [$a,$b,$scale] = $this->alignedWithScale($other);
        return new self(self::addDigits($a,$b), $scale);
    }

    public function subtract(self $other): self
    {
        [$a,$b,$scale] = $this->alignedWithScale($other);
        if (self::compareDigits($a,$b) < 0) throw new \InvalidArgumentException('El resultado decimal no puede ser negativo.');
        return new self(self::subtractDigits($a,$b), $scale);
    }

    public function multiply(self $other): self { return new self(self::multiplyDigits($this->digits,$other->digits), $this->scale + $other->scale); }

    public function divide(self $other, int $scale, bool $roundHalfUp = true): self
    {
        if ($other->isZero()) throw new \InvalidArgumentException('No se puede dividir entre cero.');
        $power = $scale + $other->scale - $this->scale;
        $numerator = $this->digits . str_repeat('0', max(0, $power));
        $denominator = $other->digits . str_repeat('0', max(0, -$power));
        [$quotient,$remainder] = self::divideDigits($numerator,$denominator);
        if ($roundHalfUp && self::compareDigits(self::multiplyDigits($remainder,'2'),$denominator) >= 0) $quotient = self::addDigits($quotient,'1');
        return new self($quotient,$scale);
    }

    public function round(int $scale): self
    {
        if ($scale >= $this->scale) return new self($this->digits . str_repeat('0',$scale-$this->scale),$scale);
        $cut = $this->scale - $scale;
        $keep = strlen($this->digits) > $cut ? substr($this->digits,0,-$cut) : '0';
        $discard = str_pad($this->digits,$cut+1,'0',STR_PAD_LEFT);
        if ((int) $discard[strlen($discard)-$cut] >= 5) $keep = self::addDigits($keep,'1');
        return new self($keep,$scale);
    }

    public function format(int $scale): string
    {
        $value = $this->round($scale); $digits = str_pad($value->digits,$scale+1,'0',STR_PAD_LEFT);
        return $scale === 0 ? $digits : substr($digits,0,-$scale) . '.' . substr($digits,-$scale);
    }

    private function aligned(self $other): array { [$a,$b] = $this->alignedWithScale($other); return [$a,$b]; }
    private function alignedWithScale(self $other): array { $scale=max($this->scale,$other->scale);return [$this->digits.str_repeat('0',$scale-$this->scale),$other->digits.str_repeat('0',$scale-$other->scale),$scale]; }
    private static function trimDigits(string $v): string { $v=ltrim($v,'0');return $v===''?'0':$v; }
    private static function compareDigits(string $a,string $b): int { $a=self::trimDigits($a);$b=self::trimDigits($b);return strlen($a)<=>strlen($b) ?: strcmp($a,$b); }
    private static function addDigits(string $a,string $b): string { $a=strrev($a);$b=strrev($b);$carry=0;$out='';$n=max(strlen($a),strlen($b));for($i=0;$i<$n;$i++){ $v=(int)($a[$i]??0)+(int)($b[$i]??0)+$carry;$out.=($v%10);$carry=intdiv($v,10);}if($carry)$out.=$carry;return self::trimDigits(strrev($out)); }
    private static function subtractDigits(string $a,string $b): string { $a=strrev($a);$b=strrev($b);$borrow=0;$out='';for($i=0;$i<strlen($a);$i++){ $v=(int)$a[$i]-(int)($b[$i]??0)-$borrow;if($v<0){$v+=10;$borrow=1;}else$borrow=0;$out.=$v;}return self::trimDigits(strrev($out)); }
    private static function multiplyDigits(string $a,string $b): string { if($a==='0'||$b==='0')return'0';$out=array_fill(0,strlen($a)+strlen($b),0);for($i=strlen($a)-1;$i>=0;$i--)for($j=strlen($b)-1;$j>=0;$j--){$p=(int)$a[$i]*(int)$b[$j]+$out[$i+$j+1];$out[$i+$j+1]=$p%10;$out[$i+$j]+=intdiv($p,10);}return self::trimDigits(implode('',$out)); }
    private static function divideDigits(string $n,string $d): array { $n=self::trimDigits($n);$d=self::trimDigits($d);$q='';$r='0';for($i=0;$i<strlen($n);$i++){ $r=self::trimDigits($r.$n[$i]);$digit=0;while(self::compareDigits($r,$d)>=0){$r=self::subtractDigits($r,$d);$digit++;}$q.=$digit;}return[self::trimDigits($q),$r]; }
}
