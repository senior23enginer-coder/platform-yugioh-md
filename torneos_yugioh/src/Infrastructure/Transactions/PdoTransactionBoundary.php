<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Transactions;

use PDO;
use Throwable;
use StationX\Yugioh\Application\Shared\TransactionBoundary;

/**
 * Owns only transactions it starts. In a borrowed transaction, failures propagate
 * to the caller, which must decide when to roll back its larger unit of work.
 * Set allowExisting=false for legacy commands that require their own transaction.
 */
final readonly class PdoTransactionBoundary implements TransactionBoundary
{
    public function __construct(private PDO $pdo, private bool $allowExisting = true) {}

    public function run(callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction || !$this->allowExisting) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
