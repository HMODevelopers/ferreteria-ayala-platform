<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\{EntityNotFound,OperationConflict};
use App\Application\Repositories\CustomerRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Customers\CustomerRules;

final class CustomerManagementService
{
    public function __construct(private readonly CustomerRepository $customers, private readonly TransactionManager $transactions) {}

    public function save(array $input, ?array $allowedBranches, int $actorId, ?int $id = null, ?callable $audit = null): array
    {
        $branchId = CustomerRules::positiveId($input['branch_id'] ?? 0, 'La sucursal');
        if ($allowedBranches !== null && !in_array($branchId, $allowedBranches, true)) throw new OperationConflict('No tienes acceso a la sucursal seleccionada.');
        $version = $id === null ? null : CustomerRules::positiveId($input['lock_version'] ?? 0, 'La versión');
        $data = [
            'branch_id' => $branchId, 'price_list_id' => trim((string) ($input['price_list_id'] ?? '')) === '' ? null : CustomerRules::positiveId($input['price_list_id'], 'La lista comercial'),
            'code' => CustomerRules::code((string) ($input['code'] ?? '')), 'customer_type' => CustomerRules::type((string) ($input['customer_type'] ?? '')),
            'legal_name' => CustomerRules::text((string) ($input['legal_name'] ?? ''), 'El nombre o razón social', 180),
            'trade_name' => CustomerRules::text((string) ($input['trade_name'] ?? ''), 'El nombre comercial', 180, false),
            'tax_identifier' => CustomerRules::taxIdentifier((string) ($input['tax_identifier'] ?? '')),
            'email' => CustomerRules::email((string) ($input['email'] ?? '')), 'phone' => CustomerRules::phone((string) ($input['phone'] ?? '')),
            'alternate_phone' => CustomerRules::phone((string) ($input['alternate_phone'] ?? ''), 'El teléfono alternativo'),
            'notes' => CustomerRules::text((string) ($input['notes'] ?? ''), 'Las observaciones', 1000, false), 'actor' => CustomerRules::positiveId($actorId, 'El usuario'),
        ];
        $contactName = CustomerRules::text((string) ($input['contact_name'] ?? ''), 'El nombre del contacto', 160, false);
        $contact = $contactName === null ? null : ['name' => $contactName, 'role' => CustomerRules::text((string) ($input['contact_role'] ?? ''), 'El puesto', 120, false), 'phone' => CustomerRules::phone((string) ($input['contact_phone'] ?? ''), 'El teléfono del contacto'), 'email' => CustomerRules::email((string) ($input['contact_email'] ?? ''))];
        $line1 = CustomerRules::text((string) ($input['address_line1'] ?? ''), 'La dirección', 240, false);
        $address = $line1 === null ? null : ['address_type' => in_array(($input['address_type'] ?? ''), ['main','fiscal','delivery'], true) ? $input['address_type'] : 'main', 'line1' => $line1, 'line2' => CustomerRules::text((string) ($input['address_line2'] ?? ''), 'La referencia', 240, false), 'city' => CustomerRules::text((string) ($input['city'] ?? ''), 'La ciudad', 120), 'state_name' => CustomerRules::text((string) ($input['state_name'] ?? ''), 'El estado', 120), 'postal_code' => CustomerRules::text((string) ($input['postal_code'] ?? ''), 'El código postal', 12, false)];
        return $this->transactions->transaction(function () use ($data, $contact, $address, $allowedBranches, $branchId, $id, $version, $audit): array {
            if ($data['price_list_id'] !== null && !array_filter($this->customers->priceLists(), static fn(array $row): bool => (int) $row['id'] === $data['price_list_id'])) throw new EntityNotFound('La lista comercial no existe o está inactiva.');
            $warnings = $this->customers->possibleDuplicates($data, $id);
            if ($id === null) $id = $this->customers->create($data);
            else {
                if ($this->customers->customer($id, $allowedBranches, true) === null) throw new EntityNotFound('El cliente no existe o está fuera de tu alcance.');
                $this->customers->update($id, $version ?? 0, $data);
            }
            $this->customers->savePrimaryContact($id, $contact);
            $this->customers->savePrimaryAddress($id, $address);
            if ($audit) $audit($id, $branchId);
            return ['id' => $id, 'warnings' => $warnings];
        });
    }

    public function setActive(int $id, int $version, bool $active, ?array $allowedBranches, int $actorId, ?callable $audit = null): void
    {
        $this->transactions->transaction(function () use ($id, $version, $active, $allowedBranches, $actorId, $audit): void {
            $customer = $this->customers->customer($id, $allowedBranches, true) ?? throw new EntityNotFound('El cliente no existe o está fuera de tu alcance.');
            if ((int) $customer['lock_version'] !== $version) throw new OperationConflict('El cliente cambió en otra sesión. Recarga la página.');
            $this->customers->setActive($id, $version, $active, CustomerRules::positiveId($actorId, 'El usuario'));
            if ($audit) $audit((int) $customer['branch_id']);
        });
    }
}
