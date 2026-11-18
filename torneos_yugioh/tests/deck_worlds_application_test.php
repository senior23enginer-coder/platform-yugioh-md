<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use StationX\Yugioh\Application\DeckWorlds\AssignDeckWorlds;
use StationX\Yugioh\Application\DeckWorlds\AssignWorldsCommand;
use StationX\Yugioh\Application\DeckWorlds\DeckWorldsQueries;
use StationX\Yugioh\Application\DeckWorlds\DeckWorldsServices;
use StationX\Yugioh\Application\DeckWorlds\Ports\EligibleDecks;
use StationX\Yugioh\Application\DeckWorlds\Ports\WorldAssignmentReader;
use StationX\Yugioh\Application\DeckWorlds\Ports\WorldAssignmentWriter;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\DeckWorlds\WorldAssignment;
use StationX\Yugioh\Domain\DeckWorlds\WorldSelection;
use StationX\Yugioh\Infrastructure\Http\DeckWorlds\DeckWorldsController;
use StationX\Yugioh\Infrastructure\Http\DeckWorlds\DeckWorldsRequest;

function worldsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function worldsReject(callable $operation, string $type): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        worldsAssert($error instanceof $type, 'Unexpected error: ' . $error::class);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $type);
}

final class WorldTestTransaction implements TransactionBoundary
{
    public bool $active = false;
    public array $events = [];
    public function run(callable $operation): mixed
    {
        $this->active = true;
        $this->events[] = 'begin';
        try {
            $result = $operation();
            $this->events[] = 'commit';
            return $result;
        } catch (Throwable $error) {
            $this->events[] = 'rollback';
            throw $error;
        } finally {
            $this->active = false;
        }
    }
}
final class WorldTestRepository implements WorldAssignmentReader, WorldAssignmentWriter
{
    public array $records = [];
    public array $revisions = [];
    public int $writes = 0;
    public function __construct(private WorldTestTransaction $transaction) {}
    public function all(): array
    {
        return array_values($this->records);
    }
    public function history(int $deckId): array
    {
        return array_reverse($this->revisions[$deckId] ?? []);
    }
    public function lockExistingDeck(int $deckId): bool
    {
        worldsAssert($this->transaction->active, 'Deck lock outside transaction');
        $this->transaction->events[] = 'deck-lock';
        return in_array($deckId, [650, 665, 704], true);
    }
    public function findForUpdate(int $deckId): ?WorldAssignment
    {
        worldsAssert($this->transaction->active, 'Assignment read outside transaction');
        $this->transaction->events[] = 'assignment-lock';
        return $this->records[$deckId] ?? null;
    }
    public function persistWithHistory(WorldAssignment $assignment, bool $alreadyExists): void
    {
        worldsAssert($this->transaction->active, 'Write outside transaction');
        worldsAssert(
            $alreadyExists === isset($this->records[$assignment->deckId]),
            'Wrong insert/update path',
        );
        $this->transaction->events[] = 'write-with-history';
        $this->writes++;
        $this->records[$assignment->deckId] = $assignment;
        $this->revisions[$assignment->deckId][] = $assignment;
    }
}
final class WorldTestEligibility implements EligibleDecks
{
    public function ids(): array
    {
        return [650, 665];
    }
}
final class WorldTestClock implements Clock
{
    public int $reads = 0;
    public function now(): DateTimeImmutable
    {
        $this->reads++;
        return new DateTimeImmutable('2026-11-18 12:34:56', new DateTimeZone('America/Bogota'));
    }
}

$transaction = new WorldTestTransaction();
$repository = new WorldTestRepository($transaction);
$eligibility = new WorldTestEligibility();
$clock = new WorldTestClock();
$assign = new AssignDeckWorlds($repository, $eligibility, $transaction, $clock);
$queries = new DeckWorldsQueries($repository, $eligibility);
$services = new DeckWorldsServices($assign, $queries);
$command = static fn(
    array $attributes,
    int $revision = 0,
    int $id = 650,
) => AssignWorldsCommand::fromArray([
    'deck_id' => $id,
    'expected_revision' => $revision,
    'attributes' => $attributes,
]);
$first = $assign->execute($command(['dark', 'water']));
worldsAssert(
    $first->selection->primary() === 'dark' && $first->revision === 1,
    'Wrong primary/revision',
);
worldsAssert($first->updatedAt === '2026-11-18 12:34:56', 'Injected clock ignored');
worldsAssert(
    $transaction->events === [
        'begin',
        'deck-lock',
        'assignment-lock',
        'write-with-history',
        'commit',
    ],
    'Lock/write order changed',
);
$same = $assign->execute($command(['dark', 'water'], 1));
worldsAssert(
    $same === $first && $repository->writes === 1 && $clock->reads === 1,
    'Idempotent save created a revision',
);
$second = $assign->execute($command(['water', 'dark'], 1));
worldsAssert(
    $second->revision === 2 && $second->selection->primary() === 'water',
    'Ordered primary was lost',
);
worldsReject(fn() => $assign->execute($command(['fire'], 1)), DomainException::class);
worldsAssert(
    $repository->writes === 2 && end($transaction->events) === 'rollback',
    'Conflict performed a write',
);
worldsReject(fn() => $assign->execute($command(['fire'], 0, 704)), InvalidArgumentException::class);
worldsReject(fn() => $assign->execute($command(['fire'], 0, 999)), InvalidArgumentException::class);
foreach ([[], ['dark', 'dark'], ['bad'], ['dark', 'water', 'fire', 'wind']] as $invalid) {
    worldsReject(fn() => $command($invalid), InvalidArgumentException::class);
}
worldsReject(
    fn() => AssignWorldsCommand::fromArray([
        'deck_id' => 650,
        'expected_revision' => 2,
        'action' => 'remove',
    ]),
    InvalidArgumentException::class,
);
worldsAssert(
    WorldSelection::fromStored([])->primary() === null,
    'Historical empty revision is no longer readable',
);
$repository->records[704] = new WorldAssignment(704, WorldSelection::fromInput(['fire']), 1, null);
worldsAssert(
    count($queries->records()) === 1 && isset($repository->records[704]),
    'Read projection deleted or exposed ineligible data',
);
worldsAssert(count($queries->overview(650)->history) === 2, 'History projection lost revisions');
worldsReject(fn() => $queries->overview(704), InvalidArgumentException::class);

// Authorization must finish before the lazy factory, body reader or any use case is invoked.
$factoryCalls = 0;
$bodyReads = 0;
$logs = [];
$controller = new DeckWorldsController(
    static function () use (&$factoryCalls, $services): DeckWorldsServices {
        $factoryCalls++;
        return $services;
    },
    static function (string $message) use (&$logs): void {
        $logs[] = $message;
    },
);
$request = static function (
    string $method = 'GET',
    bool $canEdit = true,
    string $token = 'valid',
    int $length = 0,
    string $body = '{}',
    array $query = [],
) use (&$bodyReads): DeckWorldsRequest {
    return new DeckWorldsRequest(
        $method,
        $query,
        $canEdit,
        'valid',
        $token,
        $length,
        static function () use (&$bodyReads, $body): string {
            $bodyReads++;
            return $body;
        },
    );
};
worldsAssert($controller->handle($request('DELETE'))->status === 405, 'Wrong method status');
worldsAssert(
    $controller->handle($request('POST', false))->status === 403,
    'Read-only save accepted',
);
worldsAssert(
    $controller->handle($request('POST', true, 'bad'))->status === 403,
    'Bad CSRF accepted',
);
worldsAssert(
    $controller->handle($request('POST', true, '0'))->status === 403,
    'Empty-equivalent token accepted',
);
worldsAssert(
    $controller->handle($request('GET', true, '', 2001))->status === 422,
    'Oversized request accepted',
);
worldsAssert(
    $factoryCalls === 0 && $bodyReads === 0,
    'Rejected request touched persistence or body',
);
$read = $controller->handle($request('GET', false, '', 0, '', ['deck_id' => 650]));
worldsAssert(
    $read->status === 200 && $read->body['token'] === null && $read->body['can_edit'] === false,
    'Read-only context leaked edit token',
);
worldsAssert(
    $bodyReads === 0 && $read->body['records'][0]['primary'] === 'water',
    'GET read a body or changed its response',
);
worldsAssert(
    $controller->handle($request('POST', true, 'valid', 0, '{'))->status === 422,
    'Malformed JSON accepted',
);
worldsAssert(
    $controller->handle($request('POST', true, 'valid', 0, 'null'))->status === 422,
    'Scalar command accepted',
);
$write = $controller->handle(
    $request(
        'POST',
        true,
        'valid',
        0,
        json_encode(['deck_id' => 650, 'expected_revision' => 2, 'attributes' => ['fire']]),
    ),
);
worldsAssert(
    $write->status === 200 &&
        $write->body['record']['revision'] === 3 &&
        count($write->body['history']) === 3,
    'POST response contract changed',
);
worldsAssert(
    $controller->handle(
        $request(
            'POST',
            true,
            'valid',
            0,
            json_encode(['deck_id' => 650, 'expected_revision' => 2, 'attributes' => ['earth']]),
        ),
    )->status === 409,
    'Stale command did not return 409',
);
$unavailable = new DeckWorldsController(
    static function (): DeckWorldsServices {
        throw new RuntimeException('private database detail');
    },
    static function (string $message) use (&$logs): void {
        $logs[] = $message;
    },
);
$failure = $unavailable->handle($request());
worldsAssert(
    $failure->status === 503 &&
        !str_contains($failure->body['message'], 'private') &&
        $logs === ['private database detail'],
    'Internal failure leaked details or was not logged',
);
worldsAssert(
    !function_exists('ygo_deck_attributes_save'),
    'Application tests loaded procedural persistence',
);
echo "PASS worlds application ports, atomic ordering, idempotency, concurrency, projections and lazy HTTP authorization.\n";
