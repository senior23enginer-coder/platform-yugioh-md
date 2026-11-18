<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

/** Command port. Infrastructure handles the remaining native persistence workflows. */
interface FormCommandHandler
{
    public function execute(FormCommand $command): ?string;
}
