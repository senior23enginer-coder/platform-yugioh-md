<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Matches\ManualMatchInput;

final readonly class CreateManualMatch
{
    public function __construct(private ManualMatchStore $store, private ManualMatchRules $rules) {}

    public function execute(array $post): string
    {
        $divisionId = (int) ($post['manual_division_id'] ?? $this->store->firstDivision());
        $input = ManualMatchInput::initial($post);
        $phase = $input['phase'];
        $round = $input['round'];
        $table = $input['table'];
        $deckA = $input['deckA'];
        $deckB = $input['deckB'];
        if ($deckA <= 0 || $deckB <= 0 || $deckA === $deckB) {
            return 'partido_invalido';
        }
        $competitionId =
            ($post['manual_competition_id'] ?? '') === ''
                ? null
                : (int) $post['manual_competition_id'];
        if ($competitionId) {
            $event = $this->store->event($competitionId, false);
            if (
                $this->rules->teamKind($event) !== '' ||
                ($event['execution_model'] ?? '') === 'free_for_all'
            ) {
                throw new InvalidArgumentException(
                    'Programa esta modalidad desde su ficha con las alineaciones fijadas.',
                );
            }
        }
        $details = ManualMatchInput::details($post);
        $decks = [
            $deckA,
            $deckB,
            $details['deckC'],
            $details['deckD'],
            $details['deckE'],
            $details['deckF'],
        ];
        $ruleId =
            $competitionId === null
                ? $this->rules->gameRule((int) ($post['manual_game_rule_id'] ?? 0), $phase)
                : null;
        $validation = $this->rules->validateDecks(
            $competitionId,
            $divisionId,
            $decks,
            $details['participants'],
        );
        if ($validation['ok'] && $competitionId === null && $details['ruleset'] !== '') {
            $allowed = $this->rules->rulesetDecks($details['ruleset']);
            if (!$allowed || array_diff(array_values(array_filter($decks)), $allowed)) {
                $validation = [
                    'ok' => false,
                    'message' => 'Hay decks que no estan asociados al formato seleccionado.',
                ];
            }
        }
        if (!$validation['ok']) {
            $this->store->audit('blocked_create_match_by_deck_pool', [
                'competition_id' => $competitionId,
                'division_id' => $divisionId,
                'deck_ids' => $decks,
                'message' => $validation['message'],
            ]);
            return 'partido_bloqueado_pool';
        }
        $date = $post['manual_scheduled_on'] ?? '' ?: null;
        $time = $post['manual_starts_at'] ?? '' ?: null;
        $venue = trim((string) ($post['manual_venue'] ?? ''));
        $guard = $this->rules->validateSchedule($date, $time, $venue);
        if (!$guard['ok']) {
            $this->store->audit('blocked_create_match_by_rules', $guard);
            return 'partido_bloqueado_regla';
        }
        if ($competitionId) {
            $event = $this->store->event($competitionId, true);
            if (!$event) {
                throw new InvalidArgumentException('Evento inexistente.');
            }
            if (in_array($event['execution_model'], $this->rules->workflowModels(), true)) {
                throw new InvalidArgumentException(
                    'Este sistema genera sus etapas desde la ficha; no admite cruces manuales adicionales.',
                );
            }
            $this->rules->requireSupported($event);
        }
        if ($competitionId === null && $phase === 'friendly') {
            $table = $this->store->nextFriendlyTable();
        }
        $this->store->save([
            $divisionId > 0 ? $divisionId : null,
            $competitionId,
            $ruleId,
            $phase,
            $details['format'],
            $details['bestOf'],
            $details['participants'],
            $round,
            $table,
            'Ronda ' . $round,
            $table,
            ($round - 1) * 100 + $table,
            ...$decks,
            $date,
            $time,
            $venue,
            trim((string) ($post['manual_judge'] ?? '')),
            trim((string) ($post['manual_notes'] ?? '')),
        ]);
        if ($competitionId) {
            $this->rules->rebuild($competitionId, $phase);
        }
        return 'partido_manual';
    }
}
