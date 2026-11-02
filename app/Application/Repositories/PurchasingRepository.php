<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;

interface PurchasingRepository
{
    public function suppliers(string $search, string $status, int $limit, int $offset): Page;
    public function activeSuppliers(): array;
    public function supplier(int $id, bool $lock = false): ?array;
    public function createSupplier(array $data): int;
    public function updateSupplier(int $id, array $data): void;
    public function setSupplierActive(int $id, bool $active, int $actorId): void;
    public function savePrimaryContact(int $supplierId, ?array $contact): void;
    public function invoices(string $search, ?int $branchId, ?int $supplierId, string $status, ?string $from, ?string $until, int $limit, int $offset): Page;
    public function invoice(int $id, bool $lock = false): ?array;
    public function invoiceItems(int $invoiceId, bool $lock = false): array;
    public function receipts(int $invoiceId): array;
    public function createInvoice(array $data): int;
    public function updateInvoice(int $id, array $data): void;
    public function addInvoiceItem(array $data): int;
    public function removeInvoiceItem(int $itemId, int $invoiceId): void;
    public function confirmInvoice(int $invoiceId, int $actorId): void;
    public function updateInvoiceProgress(int $invoiceId, string $status): void;
    public function incrementReceived(int $itemId, string $quantity): void;
    public function receiptByOperation(string $operationKey): ?array;
    public function createReceipt(array $data): int;
    public function createReceiptItem(array $data): void;
    public function branches(): array;
    public function variants(string $search = ''): array;
    public function unitsForVariant(int $variantId): array;
    public function variantUnitMeta(int $variantId, int $unitId): ?array;
}
