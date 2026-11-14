<?php
declare(strict_types=1);

namespace App\Core;

use App\Application\Repositories\AccessRepository;
use App\Application\Repositories\AuditRepository;
use App\Application\Security\AuthenticationService;
use App\Application\Services\AccessManagementService;
use App\Application\Services\BranchManagementService;
use App\Application\Services\BranchScheduleManagementService;
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
use App\Infrastructure\Persistence\PdoBranchScheduleRepository;
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
    public static function branchSchedule(): BranchScheduleManagementService { return new BranchScheduleManagementService(new PdoBranchScheduleRepository(self::connection()), self::branches(), self::connection()); }
    public static function catalog(): PdoCatalogRepository { return new PdoCatalogRepository(self::connection()); }
    public static function catalogManagement(): CatalogManagementService { return new CatalogManagementService(self::catalog(), self::connection()); }
    public static function pricing(): PdoPricingRepository { return new PdoPricingRepository(self::connection()); }
    public static function pricingManagement(): PricingManagementService { return new PricingManagementService(self::pricing(), self::connection()); }
    public static function inventory(): PdoInventoryRepository { return new PdoInventoryRepository(self::connection()); }
    public static function inventoryManagement(): InventoryManagementService { return new InventoryManagementService(self::inventory(), self::connection()); }
    public static function purchasing(): PdoPurchasingRepository { return new PdoPurchasingRepository(self::connection()); }
    public static function purchasingManagement(): PurchasingManagementService { return new PurchasingManagementService(self::purchasing(), self::inventoryManagement(), self::connection()); }
    public static function quotes(): \App\Infrastructure\Persistence\PdoQuoteRepository { return new \App\Infrastructure\Persistence\PdoQuoteRepository(self::connection()); }
    public static function quoteManagement(): \App\Application\Services\QuoteManagementService {
        return new \App\Application\Services\QuoteManagementService(self::quotes(),self::customers(),self::pricing(),self::pricingManagement(),new \App\Application\Services\QuoteReservationService(self::inventory(),self::quotes(),self::transfers()->installed()?self::transfers():null,self::deliveries()->installed()?self::deliveries():null),self::connection(),new \App\Application\Services\ModuleOperationGuard(self::connection()));
    }
    public static function deliveries(): \App\Infrastructure\Persistence\PdoDeliveryRepository {return new \App\Infrastructure\Persistence\PdoDeliveryRepository(self::connection());}
    public static function deliveryReservations(): \App\Application\Services\QuoteReservationService {return new \App\Application\Services\QuoteReservationService(self::inventory(),self::quotes(),self::transfers(),self::deliveries());}
    public static function notifications(): \App\Infrastructure\Persistence\PdoNotificationRepository {return new \App\Infrastructure\Persistence\PdoNotificationRepository(self::connection());}
    public static function deliveryManagement(): \App\Application\Services\DeliveryManagementService {return new \App\Application\Services\DeliveryManagementService(self::deliveries(),self::transfers(),self::inventory(),self::inventoryManagement(),self::deliveryReservations(),self::connection(),self::notifications(),new \App\Application\Services\ModuleOperationGuard(self::connection()));}
    public static function transfers(): \App\Infrastructure\Persistence\PdoTransferRepository {return new \App\Infrastructure\Persistence\PdoTransferRepository(self::connection());}
    public static function transferManagement(): \App\Application\Services\TransferManagementService {return new \App\Application\Services\TransferManagementService(self::transfers(),self::inventory(),self::inventoryManagement(),self::connection(),new \App\Application\Services\ModuleOperationGuard(self::connection()));}
    public static function returns(): \App\Infrastructure\Persistence\PdoReturnRepository {return new \App\Infrastructure\Persistence\PdoReturnRepository(self::connection());}
    public static function returnManagement(): \App\Application\Services\ReturnManagementService {return new \App\Application\Services\ReturnManagementService(self::returns(),self::inventoryManagement(),self::connection());}
    public static function customers(): PdoCustomerRepository { return new PdoCustomerRepository(self::connection()); }
    public static function customerManagement(): CustomerManagementService { return new CustomerManagementService(self::customers(), self::connection()); }

    public static function cms(): \App\Infrastructure\Persistence\PdoCmsRepository {return new \App\Infrastructure\Persistence\PdoCmsRepository(self::connection());}
    public static function cmsManagement(): \App\Application\Services\CmsManagementService {return new \App\Application\Services\CmsManagementService(self::cms(),self::connection());}
    public static function mediaDirectory(): string {return getenv('APP_ENV')==='testing'&&getenv('M11_MEDIA_DIRECTORY')?getenv('M11_MEDIA_DIRECTORY'):dirname(__DIR__,2).'/storage/media';}
    public static function cmsPrivateDirectory(): string {return getenv('APP_ENV')==='testing'&&getenv('M11_PRIVATE_DIRECTORY')?getenv('M11_PRIVATE_DIRECTORY'):dirname(__DIR__,2).'/storage/private';}
    public static function imageStorage(): \App\Infrastructure\Media\ImageStorage {return new \App\Infrastructure\Media\ImageStorage(self::mediaDirectory());}
    public static function mediaManagement(): \App\Application\Services\MediaManagementService {return new \App\Application\Services\MediaManagementService(self::cms(),self::connection(),self::imageStorage());}
    public static function quoteWorkflows(): \App\Infrastructure\Persistence\PdoQuoteWorkflowRepository {return new \App\Infrastructure\Persistence\PdoQuoteWorkflowRepository(self::connection());}
    public static function quoteWorkflow(): \App\Application\Services\QuoteWorkflowService {return new \App\Application\Services\QuoteWorkflowService(self::connection(),self::quoteManagement());}
    public static function publicRequests(): \App\Application\Services\PublicQuoteRequestService {return new \App\Application\Services\PublicQuoteRequestService(self::cms(),self::connection());}
    public static function reports(): \App\Infrastructure\Persistence\PdoReportRepository {return new \App\Infrastructure\Persistence\PdoReportRepository(self::connection());}
    public static function catalogImport(): \App\Application\Services\CatalogImportService {return new \App\Application\Services\CatalogImportService(self::connection(),dirname(__DIR__,2).'/storage/private/imports');}
}
