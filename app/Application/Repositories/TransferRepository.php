<?php
declare(strict_types=1);
namespace App\Application\Repositories;
use App\Application\Read\Page;
interface TransferRepository {
 public function listing(string $search,string $status,?int $branch,?array $allowed,int $offset):Page;
 public function find(int $id,?array $allowed=null,bool $lock=false):?array;
 public function items(int $id,bool $lock=false):array;
 public function operation(string $key):?array;
 public function create(array $data):int;
 public function addItem(array $data):int;
 public function update(int $id,string $status,?int $approver=null):void;
 public function quantities(int $id,array $data):void;
 public function event(array $data):int;
 public function finishEvent(int $id,string $status):void;
 public function eventItem(int $event,int $item,string $quantity,?int $movement):void;
 public function history(int $id):array;
 public function quoteOptions(?array $allowed):array;
 public function branches():array;
 public function lockBranches(array $ids,bool $allowInactive=false):void;
 public function lockQuote(int $id):void;
 public function quoteVersion(int $id,bool $lock=false):?array;
 public function quoteNeed(int $version,int $variant):string;
 public function coverage(int $version,int $variant):string;
 public function destinationReservations(int $version):array;
 public function releaseDestination(int $item):void;
 public function assertUntracked(int $variant):void;
}
