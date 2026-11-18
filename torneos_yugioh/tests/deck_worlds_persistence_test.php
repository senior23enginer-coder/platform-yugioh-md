<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/decks/attributes.php';
require __DIR__ . '/deck_worlds_fixture.php';

use StationX\Yugioh\Application\DeckWorlds\AssignWorldsCommand;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Infrastructure\Composition\DeckWorldsModule;
use StationX\Yugioh\Infrastructure\Persistence\DeckWorlds\DeckWorldsSchema;

function persistenceAssert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
ygo_worlds_test_schema($pdo);
ygo_worlds_test_deck($pdo, 650, 'Fixture', 'tcg_advanced');
$clock = new class implements Clock {
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-18 12:34:56', new DateTimeZone('America/Bogota'));
    }
};
$module = DeckWorldsModule::create($pdo, $clock);
persistenceAssert(
    (int) $pdo
        ->query("SELECT COUNT(*) FROM sqlite_master WHERE name='sx_ygo_deck_attributes'")
        ->fetchColumn() === 0,
    'Composition initialized schema',
);
DeckWorldsSchema::ensure($pdo);
$command = static fn(int $expected, array $attributes) => AssignWorldsCommand::fromArray([
    'deck_id' => 650,
    'expected_revision' => $expected,
    'attributes' => $attributes,
]);
for ($i = 0; $i < 40; $i++) {
    $last = $module->assign->execute($command($i, [$i % 2 ? 'water' : 'dark']));
}
$history = $module->query->history(650);
persistenceAssert(
    count($history) === 25 && $history[0]->revision === 40 && $history[24]->revision === 16,
    'History limit/order changed',
);
persistenceAssert($last->updatedAt === '2026-11-18 12:34:56', 'Persistence ignored injected clock');
$pdo->beginTransaction();
$module->assign->execute($command(40, ['water']));
persistenceAssert($pdo->inTransaction(), 'No-op committed a caller-owned transaction');
$module->assign->execute($command(40, ['fire']));
persistenceAssert($pdo->inTransaction(), 'Save committed a caller-owned transaction');
$pdo->rollBack();
persistenceAssert(
    (int) $pdo
        ->query('SELECT revision FROM sx_ygo_deck_attributes WHERE deck_id=650')
        ->fetchColumn() === 40,
    'Caller rollback did not revert assignment',
);
$pdo->exec(
    "CREATE TRIGGER fail_history BEFORE INSERT ON sx_ygo_deck_attribute_revisions WHEN NEW.revision=41 BEGIN SELECT RAISE(ABORT,'history fixture failure'); END",
);
try {
    $module->assign->execute($command(40, ['fire']));
    throw new RuntimeException('History failure accepted');
} catch (PDOException $expected) {
}
persistenceAssert(!$pdo->inTransaction(), 'Owned transaction left active after failure');
persistenceAssert(
    (int) $pdo
        ->query('SELECT revision FROM sx_ygo_deck_attributes WHERE deck_id=650')
        ->fetchColumn() === 40,
    'History failure left current row changed',
);
$pdo->beginTransaction();
try {
    $module->assign->execute($command(40, ['fire']));
    throw new RuntimeException('History failure accepted');
} catch (PDOException $expected) {
}
persistenceAssert($pdo->inTransaction(), 'Adapter rolled back a caller-owned transaction');
$pdo->rollBack();
persistenceAssert(
    (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_deck_attribute_revisions')->fetchColumn() === 40,
    'Failure leaked a history row',
);
echo "PASS worlds persistence: no composition side effects, 25-row history, atomic updates and caller-owned transactions.\n";
