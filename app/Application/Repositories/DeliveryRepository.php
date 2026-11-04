<?php
declare(strict_types=1);
namespace App\Application\Repositories;
use App\Application\Read\Page;
interface DeliveryRepository {
 public function installed():bool;
 public function listing(string $q,string $status,?int $branch,?array $allowed,int $offset):Page;
 public function find(int $id,?array $allowed=null,bool $lock=false):?array;
 public function quote(int $version,?array $allowed=null,bool $lock=false):?array;
 public function quoteItems(int $version):array;
 public function options(string $q,?array $allowed,int $offset):Page;
 public function items(int $id,bool $lock=false):array;
 public function operation(string $key):?array;
 public function create(array $data):int;
 public function addItem(int $id,int $quoteItem,string $quantity):int;
 public function update(int $id,string $status):void;
 public function quantity(int $item,string $field,string $value):void;
 public function allocated(int $quoteItem,?int $exclude=null):string;
 public function event(array $data):int;
 public function finishEvent(int $id,string $status):void;
 public function eventItem(int $event,int $item,string $qty,?int $movement=null):int;
 public function movement(int $eventItem,int $movement):void;
 public function history(int $id):array;
 public function consumedLocal(int $item):string;
 public function consumedTransfer(int $item):string;
 public function consume(int $eventItem,?int $local,?int $transfer,string $qty):void;
 public function resources(string $kind,?array $allowed,int $offset=0):Page;
 public function resource(string $kind,int $id,bool $lock=false):?array;
 public function saveResource(string $kind,array $data,?int $id,int $expected):int;
 public function addStop(int $delivery,int $run,int $position):void;
 public function stop(int $delivery):?array;
}
