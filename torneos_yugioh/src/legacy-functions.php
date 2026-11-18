<?php
declare(strict_types=1);

// Declaration-only adapters. Request handling stays in the explicit composition root.
require_once YGO_APP_ROOT . '/includes/events/catalog-options.php';
require_once YGO_APP_ROOT . '/includes/matches/evaluation.php';
require_once YGO_APP_ROOT . '/includes/settings/settings.php';
require_once YGO_APP_ROOT . '/includes/events/dependencies.php';
require_once YGO_APP_ROOT . '/includes/events/lifecycle.php';
require_once YGO_APP_ROOT . '/includes/media/uploads.php';
require_once YGO_APP_ROOT . '/includes/teams/team-services.php';
require_once YGO_APP_ROOT . '/includes/decks/eligibility.php';
require_once YGO_APP_ROOT . '/includes/events/standings.php';
require_once YGO_APP_ROOT . '/includes/leagues/structure.php';
require_once YGO_APP_ROOT . '/includes/events/generation.php';
require_once YGO_APP_ROOT . '/includes/calendar/scheduling.php';
require_once YGO_APP_ROOT . '/includes/events/configuration.php';
require_once YGO_APP_ROOT . '/includes/matches/live-duel.php';
require_once YGO_APP_ROOT . '/src/Infrastructure/Database/schema.php';
require_once YGO_APP_ROOT . '/src/Infrastructure/Database/seed.php';
require_once YGO_APP_ROOT . '/includes/http/post-actions.php';
require_once YGO_APP_ROOT . '/src/Infrastructure/Persistence/legacy-read-model.php';
require_once YGO_APP_ROOT . '/includes/events/pairing-facades.php';
