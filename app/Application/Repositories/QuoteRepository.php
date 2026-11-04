<?php
declare(strict_types=1);
namespace App\Application\Repositories;
use App\Application\Read\Page;
interface QuoteRepository
{
    public function quotes(string $search,string $status,?int $branch,?array $allowed,int $offset):Page;
    public function quote(int $id,?array $allowed,bool $lock=false):?array;
    public function version(int $quoteId,int $number,bool $lock=false):?array;
    public function versions(int $quoteId):array;
    public function items(int $versionId):array;
    public function create(array $data):int;
    public function snapshotContact(int $quoteId,string $snapshot):void;
    public function createVersion(int $quoteId,int $number,array $data):int;
    public function saveVersion(int $id,array $data,array $items):void;
    public function update(int $id,int $expected,string $state,int $number,?int $assigned):void;
    public function issue(int $versionId,int $actor):void;
    public function history(int $id):array;
    public function operation(string $key):?array;
    public function record(array $data):void;
    public function assign(int $quoteId,int $actor):void;
    public function context(int $branch,int $actor):array;
    public function variants(string $search,?int $id=null):array;
    public function policy(int $branch):?array;
    public function approved(int $versionId,string $hash):bool;
    public function approve(int $versionId,string $hash,int $actor,string $reason):void;
    public function reservation(int $versionId):?array;
    public function createReservation(int $versionId,int $branch,int $actor):int;
    public function reservationItems(int $id):array;
    public function reserveItem(array $data):void;
    public function releaseReservation(int $id,int $actor,array $consumed=[]):void;
}
