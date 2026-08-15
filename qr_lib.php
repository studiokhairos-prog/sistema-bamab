<?php
declare(strict_types=1);

/*
 * Gerador QR interno BAMAB.
 * QR Code Model 2, versão 4, nível L, modo byte, máscara 0.
 * Capacidade usada pelo sistema: payloads curtos de identificação BAMAB.
 */
function bamab_qr_gf_mul(int $x,int $y): int {
    $z=0;
    for($i=0;$i<8;$i++){
        if($y&1)$z^=$x;
        $y>>=1;
        $carry=$x&0x80;
        $x=($x<<1)&0xFF;
        if($carry)$x^=0x1D;
    }
    return $z;
}
function bamab_qr_generator(int $degree): array {
    $poly=[1];$root=1;
    for($i=0;$i<$degree;$i++){
        $new=array_fill(0,count($poly)+1,0);
        foreach($poly as $j=>$a){
            $new[$j]^=$a;
            $new[$j+1]^=bamab_qr_gf_mul($a,$root);
        }
        $poly=$new;$root=bamab_qr_gf_mul($root,2);
    }
    return $poly;
}
function bamab_qr_ecc(array $data,int $degree=20): array {
    $gen=bamab_qr_generator($degree);
    $rem=array_fill(0,$degree,0);
    foreach($data as $b){
        $factor=$b^$rem[0];
        array_shift($rem);$rem[]=0;
        for($j=0;$j<$degree;$j++)$rem[$j]^=bamab_qr_gf_mul($gen[$j+1],$factor);
    }
    return $rem;
}
function bamab_qr_append_bits(array &$bits,int $value,int $length): void {
    for($i=$length-1;$i>=0;$i--)$bits[]=($value>>$i)&1;
}
function bamab_qr_data_codewords(string $text): array {
    $bytes=array_values(unpack('C*',$text)?:[]);
    if(count($bytes)>70) throw new RuntimeException('Conteúdo do QR muito longo.');
    $bits=[];bamab_qr_append_bits($bits,0b0100,4);bamab_qr_append_bits($bits,count($bytes),8);
    foreach($bytes as $b)bamab_qr_append_bits($bits,$b,8);
    $capacity=80*8;
    for($i=0;$i<min(4,$capacity-count($bits));$i++)$bits[]=0;
    while(count($bits)%8)$bits[]=0;
    $out=[];
    for($i=0;$i<count($bits);$i+=8){
        $v=0;for($j=0;$j<8;$j++)$v=($v<<1)|($bits[$i+$j]??0);$out[]=$v;
    }
    $pads=[0xEC,0x11];$k=0;
    while(count($out)<80){$out[]=$pads[$k%2];$k++;}
    return array_slice($out,0,80);
}
function bamab_qr_matrix(string $text): array {
    $size=33;$matrix=array_fill(0,$size,array_fill(0,$size,false));
    $func=array_fill(0,$size,array_fill(0,$size,false));
    $set=function(int $x,int $y,bool $v)use(&$matrix,&$func,$size){
        if($x>=0&&$x<$size&&$y>=0&&$y<$size){$matrix[$y][$x]=$v;$func[$y][$x]=true;}
    };
    $finder=function(int $x,int $y)use(&$set,$size){
        for($dy=-1;$dy<=7;$dy++)for($dx=-1;$dx<=7;$dx++){
            $xx=$x+$dx;$yy=$y+$dy;if($xx<0||$xx>=$size||$yy<0||$yy>=$size)continue;
            $inside=$dx>=0&&$dx<=6&&$dy>=0&&$dy<=6;
            $v=$inside&&($dx===0||$dx===6||$dy===0||$dy===6||($dx>=2&&$dx<=4&&$dy>=2&&$dy<=4));
            $set($xx,$yy,$v);
        }
    };
    $finder(0,0);$finder($size-7,0);$finder(0,$size-7);

    // Alinhamento versão 4: centro 26,26.
    for($dy=-2;$dy<=2;$dy++)for($dx=-2;$dx<=2;$dx++)$set(26+$dx,26+$dy,max(abs($dx),abs($dy))!==1);

    for($i=8;$i<$size-8;$i++){$set($i,6,$i%2===0);$set(6,$i,$i%2===0);}
    $set(8,$size-8,true);

    // Formato: nível L (01), máscara 0.
    $mask=0;$formatData=(1<<3)|$mask;$rem=$formatData;
    for($i=0;$i<10;$i++)$rem=($rem<<1)^(((($rem>>9)&1)===1)?0x537:0);
    $format=(($formatData<<10)|$rem)^0x5412;
    for($i=0;$i<6;$i++)$set(8,$i,(bool)(($format>>$i)&1));
    $set(8,7,(bool)(($format>>6)&1));$set(8,8,(bool)(($format>>7)&1));$set(7,8,(bool)(($format>>8)&1));
    for($i=9;$i<15;$i++)$set(14-$i,8,(bool)(($format>>$i)&1));
    for($i=0;$i<8;$i++)$set($size-1-$i,8,(bool)(($format>>$i)&1));
    for($i=8;$i<15;$i++)$set(8,$size-15+$i,(bool)(($format>>$i)&1));
    $set(8,$size-8,true);

    $data=bamab_qr_data_codewords($text);
    $code=array_merge($data,bamab_qr_ecc($data,20));
    $bitIndex=0;$upward=true;
    for($right=$size-1;$right>=1;$right-=2){
        if($right===6)$right--;
        for($vert=0;$vert<$size;$vert++){
            $y=$upward?$size-1-$vert:$vert;
            for($j=0;$j<2;$j++){
                $x=$right-$j;if($func[$y][$x])continue;
                $bit=0;
                if($bitIndex<count($code)*8)$bit=($code[intdiv($bitIndex,8)]>>(7-($bitIndex&7)))&1;
                $bitIndex++;
                if((($x+$y)%2)===0)$bit^=1;
                $matrix[$y][$x]=(bool)$bit;
            }
        }
        $upward=!$upward;
    }
    return $matrix;
}
function bamab_qr_svg(string $text,int $scale=5,int $border=4): string {
    $m=bamab_qr_matrix($text);$size=count($m);$dim=($size+2*$border)*$scale;
    $paths=[];
    for($y=0;$y<$size;$y++)for($x=0;$x<$size;$x++)if($m[$y][$x]){
        $xx=($x+$border)*$scale;$yy=($y+$border)*$scale;
        $paths[]="M$xx $yy"."h$scale"."v$scale"."h-$scale"."z";
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$dim.' '.$dim.'" width="'.$dim.'" height="'.$dim.'" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path d="'.implode('',$paths).'" fill="#000"/></svg>';
}
