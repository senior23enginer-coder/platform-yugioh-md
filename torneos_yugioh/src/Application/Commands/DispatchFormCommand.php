<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

use InvalidArgumentException;

/** Routes an exact action through explicit ports; it has no PDO, session or global-function dependency. */
final readonly class DispatchFormCommand
{
    /** @param array<string, FormCommandHandler> $handlers */
    public function __construct(private array $handlers)
    {
        foreach ($handlers as $handler) {
            if (!($handler instanceof FormCommandHandler)) {
                throw new InvalidArgumentException('Invalid command handler');
            }
        }
    }

    public function supports(string $action): bool
    {
        return isset($this->handlers[$action]);
    }

    public function execute(FormCommand $command): ?string
    {
        return isset($this->handlers[$command->action])
            ? $this->handlers[$command->action]->execute($command)
            : null;
    }
}
