<?php
declare(strict_types=1);
namespace App\Application\Services;

use App\Application\Exceptions\OperationConflict;
use App\Core\Persistence\Connection;

final class ModuleOperationGuard
{
    public function __construct(private readonly Connection $connection) {}

    public function assertAvailable(string $code, int $branchId): void
    {
        if(!preg_match('/^M\d{2}$/D',$code)||$branchId<1)throw new \InvalidArgumentException('Contexto modular inválido.');
        $statement=$this->connection->pdo()->prepare(
            'SELECT m.is_globally_enabled,m.requires_branch_enablement,b.is_active,COALESCE(bm.is_enabled,0) branch_enabled '
            .'FROM modules m LEFT JOIN branches b ON b.id=? LEFT JOIN branch_modules bm ON bm.branch_id=b.id AND bm.module_id=m.id '
            .'WHERE m.code=? FOR UPDATE'
        );
        $statement->execute([$branchId,$code]);$state=$statement->fetch();
        // A module not registered in legacy installations has no disabled state to enforce.
        if(!is_array($state))return;
        if(!(int)$state['is_globally_enabled']||!(int)$state['is_active']||((int)$state['requires_branch_enablement']&&!(int)$state['branch_enabled'])){
            throw new OperationConflict('El módulo está deshabilitado para esta sucursal.');
        }
    }
}
