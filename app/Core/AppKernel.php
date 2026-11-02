<?php
declare(strict_types=1);

namespace App\Core;

use App\Application\Repositories\AccessRepository;
use App\Application\Repositories\AuditRepository;
use App\Application\Security\AuthenticationService;
use App\Application\Services\AccessManagementService;
use App\Application\Services\BranchManagementService;
use App\Application\Services\CatalogManagementService;
use App\Application\Services\PricingManagementService;
use App\Application\Services\InventoryManagementService;
use App\Application\Services\PurchasingManagementService;
use App\Application\Services\CustomerManagementService;
use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Security\SessionManager;
use App\Infrastructure\Persistence\PdoAccessRepository;
use App\Infrastructure\Persistence\PdoAuditRepository;
use App\Infrastructure\Persistence\PdoBranchAdminReadRepository;
use App\Infrastructure\Persistence\PdoBranchRepository;
use App\Infrastructure\Persistence\PdoCatalogRepository;
use App\Infrastructure\Persistence\PdoPricingRepository;
use App\Infrastructure\Persistence\PdoInventoryRepository;
use App\Infrastructure\Persistence\PdoPurchasingRepository;
use App\Infrastructure\Persistence\PdoCustomerRepository;

final class AppKernel
{
    private static ?Connection $connection = null;
    private static ?AccessRepository $access = null;
    private static ?AuditRepository $audit = null;
    private static ?SessionManager $session = null;

    public static function connection(): Connection
    {
        return self::$connection ??= new Connection(DatabaseConfig::fromArray(require dirname(__DIR__, 2) . '/config/database.php'));
    }

    public static function access(): AccessRepository { return self::$access ??= new PdoAccessRepository(self::connection()); }
    public static function audit(): AuditRepository { return self::$audit ??= new PdoAuditRepository(self::connection()); }
    public static function session(): SessionManager { return self::$session ??= new SessionManager(); }
    public static function authentication(): AuthenticationService { return new AuthenticationService(self::access(), self::audit(), self::connection()); }
    public static function accessManagement(): AccessManagementService { return new AccessManagementService(self::access(), self::audit(), self::connection()); }
    public static function branches(): PdoBranchRepository { return new PdoBranchRepository(self::connection()); }
    public static function branchReads(): PdoBranchAdminReadRepository { return new PdoBranchAdminReadRepository(self::connection()); }
    public static function branchManagement(): BranchManagementService { return new BranchManagementService(self::branches(), self::connection()); }
    public static function catalog(): PdoCatalogRepository { return new PdoCatalogRepository(self::connection()); }
    public static function catalogManagement(): CatalogManagementService { return new CatalogManagementService(self::catalog(), self::connection()); }
    public static function pricing(): PdoPricingRepository { return new PdoPricingRepository(self::connection()); }
    public static function pricingManagement(): PricingManagementService { return new PricingManagementService(self::pricing(), self::connection()); }
    public static function inventory(): PdoInventoryRepository { return new PdoInventoryRepository(self::connection()); }
    public static function inventoryManagement(): InventoryManagementService { return new InventoryManagementService(self::inventory(), self::connection()); }
    public static function purchasing(): PdoPurchasingRepository { return new PdoPurchasingRepository(self::connection()); }
    public static function purchasingManagement(): PurchasingManagementService { return new PurchasingManagementService(self::purchasing(), self::inventoryManagement(), self::connection()); }
    public static function customers(): PdoCustomerRepository { return new PdoCustomerRepository(self::connection()); }
    public static function customerManagement(): CustomerManagementService { return new CustomerManagementService(self::customers(), self::connection()); }
}
