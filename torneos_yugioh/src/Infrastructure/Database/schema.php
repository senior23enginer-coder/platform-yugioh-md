<?php
declare(strict_types=1);

/**
 * database-schema: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function ygo_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!ygo_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function ygo_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function ygo_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function ygo_drop_index_if_exists(PDO $pdo, string $table, string $index): void
{
    if (ygo_index_exists($pdo, $table, $index)) {
        $pdo->exec("ALTER TABLE $table DROP INDEX $index");
    }
}

function ensure_ygo_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_seasons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(80) NOT NULL UNIQUE,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_divisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            code VARCHAR(40) NOT NULL,
            name VARCHAR(120) NOT NULL,
            cup_name VARCHAR(120) NOT NULL,
            tier_order INT NOT NULL,
            deck_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_division (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            city VARCHAR(120) NULL,
            captain VARCHAR(120) NULL,
            contact VARCHAR(120) NULL,
            logo_url VARCHAR(500) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_team_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_decks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            division_id INT NOT NULL,
            seed_rank INT NOT NULL,
            name VARCHAR(160) NOT NULL,
            reason VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_deck (division_id, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_decks', 'team_id', 'INT NULL AFTER division_id');
    ygo_add_column($pdo, 'sx_ygo_decks', 'archetype', 'VARCHAR(120) NULL AFTER name');
    ygo_add_column($pdo, 'sx_ygo_decks', 'style', 'VARCHAR(120) NULL AFTER archetype');
    ygo_add_column($pdo, 'sx_ygo_decks', 'key_cards', 'TEXT NULL AFTER reason');
    ygo_add_column($pdo, 'sx_ygo_decks', 'variations', 'TEXT NULL AFTER key_cards');
    ygo_add_column($pdo, 'sx_ygo_decks', 'notes', 'TEXT NULL AFTER variations');
    ygo_add_column($pdo, 'sx_ygo_decks', 'cover_url', 'VARCHAR(500) NULL AFTER notes');
    ygo_add_column($pdo, 'sx_ygo_decks', 'side_deck', 'TEXT NULL AFTER key_cards');
    ygo_add_column($pdo, 'sx_ygo_decks', 'logo_url', 'VARCHAR(500) NULL AFTER cover_url');
    ygo_add_column($pdo, 'sx_ygo_decks', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER reason');
    try {
        $pdo->exec('ALTER TABLE sx_ygo_decks MODIFY division_id INT NULL');
    } catch (Throwable $error) {
        // Some shared hosts may block MODIFY when metadata is locked; existing installs continue with current schema.
    }
    ygo_add_column($pdo, 'sx_ygo_teams', 'short_code', 'VARCHAR(12) NULL AFTER name');
    ygo_add_column($pdo, 'sx_ygo_teams', 'primary_color', 'VARCHAR(30) NULL AFTER logo_url');
    ygo_add_column($pdo, 'sx_ygo_teams', 'secondary_color', 'VARCHAR(30) NULL AFTER primary_color');
    ygo_add_column($pdo, 'sx_ygo_teams', 'symbol', 'VARCHAR(24) NULL AFTER secondary_color');
    ygo_add_column($pdo, 'sx_ygo_teams', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER logo_url');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'accent', 'VARCHAR(30) NULL AFTER deck_count');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'description', 'TEXT NULL AFTER accent');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'starts_on', 'DATE NULL AFTER description');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'ends_on', 'DATE NULL AFTER starts_on');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'promotion_slots', 'INT NOT NULL DEFAULT 2 AFTER ends_on');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'relegation_slots', 'INT NOT NULL DEFAULT 2 AFTER promotion_slots');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'promotion_to_division_id', 'INT NULL AFTER relegation_slots');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'relegation_to_division_id', 'INT NULL AFTER promotion_to_division_id');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'league_competition_id', 'INT NULL AFTER relegation_to_division_id');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'source_qualifier_id', 'INT NULL AFTER league_competition_id');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'rank_from', 'INT NULL AFTER source_qualifier_id');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'rank_to', 'INT NULL AFTER rank_from');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'logo_url', 'VARCHAR(500) NULL AFTER rank_to');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER logo_url');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_competitions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            division_id INT NULL,
            code VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            type VARCHAR(40) NOT NULL,
            format VARCHAR(80) NOT NULL DEFAULT 'editable',
            status VARCHAR(40) NOT NULL DEFAULT 'draft',
            starts_on DATE NULL,
            ends_on DATE NULL,
            accent VARCHAR(30) NULL,
            rules TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_competition (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'scope', "VARCHAR(40) NOT NULL DEFAULT 'division' AFTER type");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'match_format', "VARCHAR(40) NOT NULL DEFAULT '1v1' AFTER format");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'best_of', 'INT NOT NULL DEFAULT 5 AFTER match_format');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'participants_per_side', 'INT NOT NULL DEFAULT 1 AFTER best_of');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'schedule_strategy', "VARCHAR(60) NOT NULL DEFAULT 'manual' AFTER participants_per_side");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'schedule_pattern', "VARCHAR(40) NOT NULL DEFAULT 'consecutive' AFTER schedule_strategy");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'schedule_interval_days', 'INT NOT NULL DEFAULT 1 AFTER schedule_pattern');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'qualifier_matches_per_day', 'INT NOT NULL DEFAULT 1 AFTER schedule_interval_days');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'game_rule_id', 'INT NULL AFTER qualifier_matches_per_day');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'configuration_locked', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER game_rule_id');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'started_at', 'DATETIME NULL AFTER configuration_locked');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'qualifier_duels_per_deck', 'INT NOT NULL DEFAULT 3 AFTER started_at');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'event_ruleset', "VARCHAR(60) NOT NULL DEFAULT 'tcg_advanced' AFTER schedule_strategy");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'ruleset_version', 'VARCHAR(180) NULL AFTER event_ruleset');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'deck_pool', "VARCHAR(60) NOT NULL DEFAULT 'all_active' AFTER event_ruleset");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'promotion_slots', 'INT NOT NULL DEFAULT 0 AFTER deck_pool');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'relegation_slots', 'INT NOT NULL DEFAULT 0 AFTER promotion_slots');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'promotion_to_division_id', 'INT NULL AFTER relegation_slots');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'relegation_to_division_id', 'INT NULL AFTER promotion_to_division_id');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'lock_division_moves', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER relegation_to_division_id');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'victory_condition', "VARCHAR(180) NULL AFTER deck_pool");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'defeat_condition', "VARCHAR(180) NULL AFTER victory_condition");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'draw_condition', "VARCHAR(180) NULL AFTER defeat_condition");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'prompt_template', 'VARCHAR(80) NULL AFTER draw_condition');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'event_system', 'VARCHAR(160) NULL AFTER prompt_template');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'execution_model', 'VARCHAR(80) NULL AFTER event_system');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'participation', 'VARCHAR(120) NULL AFTER execution_model');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'construction', 'VARCHAR(120) NULL AFTER participation');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'environment', 'VARCHAR(80) NULL AFTER construction');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'level', 'VARCHAR(80) NULL AFTER environment');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'event_flow', 'VARCHAR(80) NULL AFTER level');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'participant_source', 'VARCHAR(80) NULL AFTER event_flow');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'advancement_rule', 'VARCHAR(80) NULL AFTER participant_source');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'draw_policy', 'VARCHAR(80) NULL AFTER advancement_rule');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'ranking_impact', 'VARCHAR(80) NULL AFTER draw_policy');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'deck_policy', 'VARCHAR(80) NULL AFTER ranking_impact');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'access_type', 'VARCHAR(80) NULL AFTER deck_policy');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'phase_structure', 'VARCHAR(120) NULL AFTER access_type');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'logo_url', 'VARCHAR(500) NULL AFTER accent');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER rules');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    $pdo->prepare("UPDATE sx_ygo_competitions SET status='pending_configuration' WHERE status='draft' AND active = 1")->execute();
    $pdo->prepare("UPDATE sx_ygo_competitions SET status='pending_configuration' WHERE LOWER(name) = 'test' AND active = 1")->execute();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_rulesets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            code VARCHAR(80) NOT NULL,
            label VARCHAR(160) NOT NULL,
            format_family VARCHAR(80) NOT NULL DEFAULT 'custom',
            banlist_name VARCHAR(160) NULL,
            banlist_version VARCHAR(120) NULL,
            banlist_effective_on DATE NULL,
            match_format VARCHAR(40) NOT NULL DEFAULT '1v1',
            best_of INT NOT NULL DEFAULT 3,
            participants_per_side INT NOT NULL DEFAULT 1,
            schedule_strategy VARCHAR(60) NOT NULL DEFAULT 'manual',
            deck_pool VARCHAR(60) NOT NULL DEFAULT 'all_active',
            victory_condition VARCHAR(180) NULL,
            defeat_condition VARCHAR(180) NULL,
            draw_condition VARCHAR(180) NULL,
            notes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_ruleset (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_rulesets', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER notes');
    ygo_add_column($pdo, 'sx_ygo_competition_decks', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_ruleset_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            code VARCHAR(180) NOT NULL,
            category VARCHAR(40) NOT NULL DEFAULT 'banlist',
            system VARCHAR(40) NOT NULL,
            version_label VARCHAR(160) NOT NULL,
            date_label VARCHAR(80) NOT NULL,
            effective_on DATE NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_ruleset_version (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_ruleset_versions', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_match_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL,
            label VARCHAR(80) NOT NULL,
            participants_per_side INT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_match_type (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_match_types', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_competition_decks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            competition_id INT NOT NULL,
            deck_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_competition_deck (competition_id, deck_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_competition_decks', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_game_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            code VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            rule_type VARCHAR(60) NOT NULL DEFAULT 'custom',
            scope VARCHAR(80) NOT NULL DEFAULT 'general',
            trigger_event VARCHAR(120) NULL,
            effect TEXT NULL,
            points_value INT NOT NULL DEFAULT 0,
            priority_order INT NOT NULL DEFAULT 100,
            active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_game_rule (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_game_rules', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER priority_order');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_game_rule_conditions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            game_rule_id INT NOT NULL,
            condition_type VARCHAR(40) NOT NULL DEFAULT 'validation',
            condition_label VARCHAR(160) NOT NULL,
            operator VARCHAR(40) NOT NULL DEFAULT 'defined',
            target_value VARCHAR(160) NULL,
            result_value VARCHAR(20) NULL,
            status_value VARCHAR(40) NULL,
            score_value VARCHAR(40) NULL,
            failure_message VARCHAR(220) NULL,
            sort_order INT NOT NULL DEFAULT 100,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX ix_ygo_game_rule_condition_rule (game_rule_id, active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_game_rule_conditions', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
    ygo_add_column($pdo, 'sx_ygo_game_rule_conditions', 'result_value', 'VARCHAR(20) NULL AFTER target_value');
    ygo_add_column($pdo, 'sx_ygo_game_rule_conditions', 'status_value', 'VARCHAR(40) NULL AFTER result_value');
    ygo_add_column($pdo, 'sx_ygo_game_rule_conditions', 'score_value', 'VARCHAR(40) NULL AFTER status_value');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_competition_dependencies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NOT NULL,
            depends_on_competition_id INT NOT NULL,
            condition_type VARCHAR(40) NOT NULL DEFAULT 'completed_with_results',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_competition_dependency (competition_id, depends_on_competition_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_competition_dependencies', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER condition_type');
    ygo_add_column($pdo, 'sx_ygo_competition_dependencies', 'take_count', 'INT NOT NULL DEFAULT 0 AFTER condition_type');
    ygo_add_column($pdo, 'sx_ygo_competition_dependencies', 'notes', 'VARCHAR(255) NULL AFTER take_count');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_matches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            division_id INT NOT NULL,
            competition_id INT NULL,
            phase VARCHAR(40) NOT NULL DEFAULT 'league',
            round_number INT NOT NULL,
            table_number INT NOT NULL,
            deck_a_id INT NOT NULL,
            deck_b_id INT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_match (division_id, competition_id, phase, round_number, table_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_matches', 'scheduled_on', 'DATE NULL AFTER status');
    ygo_add_column($pdo, 'sx_ygo_matches', 'starts_at', 'TIME NULL AFTER scheduled_on');
    ygo_add_column($pdo, 'sx_ygo_matches', 'venue', 'VARCHAR(160) NULL AFTER starts_at');
    ygo_add_column($pdo, 'sx_ygo_matches', 'judge', 'VARCHAR(120) NULL AFTER venue');
    ygo_add_column($pdo, 'sx_ygo_matches', 'notes', 'TEXT NULL AFTER judge');
    ygo_add_column($pdo, 'sx_ygo_matches', 'competition_id', 'INT NULL AFTER division_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'game_rule_id', 'INT NULL AFTER competition_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'match_format', "VARCHAR(40) NOT NULL DEFAULT '1v1' AFTER phase");
    ygo_add_column($pdo, 'sx_ygo_matches', 'best_of', 'INT NOT NULL DEFAULT 5 AFTER match_format');
    ygo_add_column($pdo, 'sx_ygo_matches', 'participants_per_side', 'INT NOT NULL DEFAULT 1 AFTER best_of');
    ygo_add_column($pdo, 'sx_ygo_matches', 'deck_c_id', 'INT NULL AFTER deck_b_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'deck_d_id', 'INT NULL AFTER deck_c_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'deck_e_id', 'INT NULL AFTER deck_d_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'deck_f_id', 'INT NULL AFTER deck_e_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'bracket_round_label', 'VARCHAR(80) NULL AFTER table_number');
    ygo_add_column($pdo, 'sx_ygo_matches', 'bracket_slot', 'INT NULL AFTER bracket_round_label');
    ygo_add_column($pdo, 'sx_ygo_matches', 'bracket_side', 'VARCHAR(20) NULL AFTER bracket_slot');
    ygo_add_column($pdo, 'sx_ygo_matches', 'display_order', 'INT NOT NULL DEFAULT 0 AFTER bracket_side');
    ygo_add_column($pdo, 'sx_ygo_matches', 'generation_number', 'INT NOT NULL DEFAULT 1 AFTER display_order');
    ygo_add_column($pdo, 'sx_ygo_matches', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    $pdo->exec('ALTER TABLE sx_ygo_matches MODIFY division_id INT NULL');
    $pdo->exec("UPDATE sx_ygo_matches SET display_order = ((round_number - 1) * 100) + table_number WHERE display_order = 0");
    $pdo->exec("UPDATE sx_ygo_matches SET bracket_round_label = CONCAT('Ronda ', round_number) WHERE bracket_round_label IS NULL OR bracket_round_label = ''");
    $pdo->exec("UPDATE sx_ygo_matches SET bracket_slot = table_number WHERE bracket_slot IS NULL");
    $pdo->exec("UPDATE sx_ygo_matches SET bracket_side = 'main' WHERE bracket_side IS NULL OR bracket_side = ''");
    if (ygo_index_exists($pdo, 'sx_ygo_matches', 'ux_ygo_match')) {
        $indexColumns = $pdo->prepare("
            SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'sx_ygo_matches'
              AND INDEX_NAME = 'ux_ygo_match'
        ");
        $indexColumns->execute();
        if ((string) $indexColumns->fetchColumn() !== 'division_id,competition_id,phase,round_number,table_number') {
            ygo_drop_index_if_exists($pdo, 'sx_ygo_matches', 'ux_ygo_match');
            $pdo->exec('ALTER TABLE sx_ygo_matches ADD UNIQUE KEY ux_ygo_match (division_id, competition_id, phase, round_number, table_number)');
        }
    } else {
        $pdo->exec('ALTER TABLE sx_ygo_matches ADD UNIQUE KEY ux_ygo_match (division_id, competition_id, phase, round_number, table_number)');
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NULL,
            division_id INT NULL,
            code VARCHAR(120) NOT NULL,
            title VARCHAR(180) NOT NULL,
            event_type VARCHAR(40) NOT NULL DEFAULT 'qualifier',
            status VARCHAR(40) NOT NULL DEFAULT 'scheduled',
            event_date DATE NOT NULL,
            starts_at TIME NULL,
            ends_at TIME NULL,
            venue VARCHAR(160) NULL,
            notes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_event_code (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        UPDATE sx_ygo_events e
        SET e.active = 0
        WHERE e.competition_id IS NOT NULL
          AND e.active = 1
          AND (e.code LIKE 'competition-%-inicio' OR e.notes LIKE 'Evento creado desde ficha%' OR e.notes LIKE 'Evento activo persistido%')
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_matches m
              WHERE m.competition_id = e.competition_id
                AND m.scheduled_on = e.event_date
          )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_match_results (
            match_id INT PRIMARY KEY,
            d1 CHAR(1) NULL,
            d2 CHAR(1) NULL,
            d3 CHAR(1) NULL,
            d4 CHAR(1) NULL,
            d5 CHAR(1) NULL,
            result CHAR(1) NULL,
            score VARCHAR(10) NULL,
            validation_status VARCHAR(40) NOT NULL DEFAULT 'OK',
            notes TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_match_results', 'dice_roll', 'TEXT NULL AFTER notes');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'turn_choice', 'VARCHAR(40) NULL AFTER dice_roll');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'dice_winner', 'CHAR(1) NULL AFTER turn_choice');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'turn_chosen_by', 'CHAR(1) NULL AFTER dice_winner');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'turn_decision', 'VARCHAR(20) NULL AFTER turn_chosen_by');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'starts_player', 'CHAR(1) NULL AFTER turn_decision');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'duel_details', 'TEXT NULL AFTER starts_player');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_turn_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            duel_number TINYINT UNSIGNED NOT NULL,
            turn_number SMALLINT UNSIGNED NOT NULL,
            side CHAR(1) NOT NULL,
            ended_at VARCHAR(40) NULL,
            elapsed_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_turn_audit (match_id, duel_number, turn_number),
            KEY idx_ygo_turn_audit_match (match_id, duel_number, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_live_duel_state (
            match_id INT PRIMARY KEY,
            duel_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            state_json LONGTEXT NOT NULL,
            updated_by VARCHAR(20) NOT NULL DEFAULT 'judge',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ygo_live_duel_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_match_snapshots (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            duel_number TINYINT UNSIGNED NOT NULL,
            side CHAR(1) NOT NULL,
            competition_id INT NULL,
            deck_id INT NULL,
            team_id INT NULL,
            player_id INT NULL,
            deck_name VARCHAR(160) NULL,
            archetype VARCHAR(160) NULL,
            variation TEXT NULL,
            team_name VARCHAR(160) NULL,
            ruleset_code VARCHAR(80) NULL,
            ruleset_version VARCHAR(180) NULL,
            division_name VARCHAR(160) NULL,
            decklist_json LONGTEXT NULL,
            data_origin VARCHAR(40) NOT NULL DEFAULT 'captured',
            captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_match_snapshot (match_id, duel_number, side),
            KEY idx_ygo_match_snapshot_deck (deck_id, captured_at),
            KEY idx_ygo_match_snapshot_team (team_id, captured_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_matchup_analyses (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            deck_id INT NOT NULL,
            opponent_deck_id INT NOT NULL,
            deck_version VARCHAR(180) NOT NULL DEFAULT '',
            opponent_version VARCHAR(180) NOT NULL DEFAULT '',
            ruleset_version VARCHAR(180) NOT NULL DEFAULT '',
            strategic_score TINYINT NOT NULL DEFAULT 0,
            classification VARCHAR(30) NOT NULL DEFAULT 'EQUILIBRADO',
            reason TEXT NULL,
            key_advantages_json LONGTEXT NULL,
            key_disadvantages_json LONGTEXT NULL,
            choke_points_json LONGTEXT NULL,
            going_first_plan TEXT NULL,
            going_second_plan TEXT NULL,
            side_plan TEXT NULL,
            confidence VARCHAR(20) NOT NULL DEFAULT 'MEDIA',
            source_type VARCHAR(20) NOT NULL DEFAULT 'EDITORIAL',
            review_notes TEXT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_matchup_analysis_scope (deck_id, opponent_deck_id, deck_version, opponent_version, ruleset_version),
            KEY idx_ygo_matchup_analysis_deck (deck_id, active, updated_at),
            KEY idx_ygo_matchup_analysis_opponent (opponent_deck_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_matchup_analysis_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            analysis_id BIGINT NOT NULL,
            deck_id INT NOT NULL,
            opponent_deck_id INT NOT NULL,
            revision INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            change_reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_matchup_analysis_revision (analysis_id, revision),
            KEY idx_ygo_matchup_history_pair (deck_id, opponent_deck_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_deck_analysis_profiles (
            deck_id INT PRIMARY KEY,
            play_style VARCHAR(120) NULL,
            pace VARCHAR(30) NULL,
            complexity VARCHAR(30) NULL,
            primary_game_plan TEXT NULL,
            win_condition TEXT NULL,
            resource_engine TEXT NULL,
            opening_plan TEXT NULL,
            mid_game_plan TEXT NULL,
            late_game_plan TEXT NULL,
            going_first_plan TEXT NULL,
            going_second_plan TEXT NULL,
            side_deck_strategy TEXT NULL,
            strengths_json LONGTEXT NULL,
            weaknesses_json LONGTEXT NULL,
            strategy_tags_json LONGTEXT NULL,
            consistency_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            ceiling_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            resilience_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            interaction_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            recovery_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            first_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            second_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            confidence VARCHAR(20) NOT NULL DEFAULT 'MEDIA',
            source_type VARCHAR(20) NOT NULL DEFAULT 'EDITORIAL',
            analysis_notes TEXT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_deck_analysis_profile_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            deck_id INT NOT NULL,
            revision INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            change_reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_deck_analysis_profile_revision (deck_id, revision),
            KEY idx_ygo_deck_analysis_profile_history (deck_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_medal_unlocks (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(20) NOT NULL,
            entity_id INT NOT NULL,
            medal_code VARCHAR(80) NOT NULL,
            medal_name VARCHAR(160) NOT NULL,
            rarity VARCHAR(20) NOT NULL DEFAULT 'common',
            secret TINYINT(1) NOT NULL DEFAULT 0,
            format_code VARCHAR(80) NULL,
            competition_id INT NULL,
            match_id INT NULL,
            duel_number TINYINT UNSIGNED NULL,
            rival_name VARCHAR(160) NULL,
            progress_value DECIMAL(14,2) NOT NULL DEFAULT 0,
            unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_medal_unlock (entity_type, entity_id, medal_code, format_code),
            KEY idx_ygo_medal_first (medal_code, unlocked_at),
            KEY idx_ygo_medal_event (competition_id, match_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if ((int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_medal_unlocks WHERE format_code IS NULL")->fetchColumn() > 0) {
        $pdo->exec("DELETE newer FROM sx_ygo_medal_unlocks newer JOIN sx_ygo_medal_unlocks older ON older.entity_type=newer.entity_type AND older.entity_id=newer.entity_id AND older.medal_code=newer.medal_code AND COALESCE(older.format_code,'')=COALESCE(newer.format_code,'') AND older.id < newer.id");
        $pdo->exec("UPDATE sx_ygo_medal_unlocks SET format_code='' WHERE format_code IS NULL");
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_checkins (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            competition_id INT NULL,
            match_id INT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id INT NULL,
            display_name VARCHAR(160) NOT NULL,
            checkin_code VARCHAR(80) NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'manual',
            checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_checkin (competition_id, match_id, entity_type, entity_id, display_name),
            KEY idx_ygo_checkin_event (competition_id, checked_in_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_meta_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            snapshot_on DATE NOT NULL,
            season_id INT NULL,
            format_code VARCHAR(80) NOT NULL,
            group_type VARCHAR(20) NOT NULL DEFAULT 'deck',
            group_key VARCHAR(180) NOT NULL,
            appearances INT NOT NULL DEFAULT 0,
            matches INT NOT NULL DEFAULT 0,
            wins INT NOT NULL DEFAULT 0,
            win_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
            presence_share DECIMAL(6,2) NOT NULL DEFAULT 0,
            meta_score DECIMAL(6,2) NOT NULL DEFAULT 0,
            tier VARCHAR(20) NOT NULL DEFAULT 'Tier 3',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_meta_history (snapshot_on, season_id, format_code, group_type, group_key),
            KEY idx_ygo_meta_history_scope (season_id, format_code, snapshot_on)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_elo_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            competition_id INT NULL,
            deck_id INT NOT NULL,
            opponent_deck_id INT NOT NULL,
            played_on DATE NOT NULL,
            rating_before DECIMAL(10,2) NOT NULL,
            rating_after DECIMAL(10,2) NOT NULL,
            rating_delta DECIMAL(10,2) NOT NULL,
            result_code CHAR(1) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_elo_match_deck (match_id, deck_id),
            KEY idx_ygo_elo_deck_date (deck_id, played_on, match_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_global_ranking_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            snapshot_on DATE NOT NULL,
            season_id INT NOT NULL DEFAULT 0,
            scope_key VARCHAR(40) NOT NULL DEFAULT 'global',
            deck_id INT NOT NULL,
            position INT NOT NULL,
            previous_position INT NULL,
            position_delta INT NOT NULL DEFAULT 0,
            points INT NOT NULL DEFAULT 0,
            played INT NOT NULL DEFAULT 0,
            wins INT NOT NULL DEFAULT 0,
            draws INT NOT NULL DEFAULT 0,
            losses INT NOT NULL DEFAULT 0,
            duels_won INT NOT NULL DEFAULT 0,
            duels_drawn INT NOT NULL DEFAULT 0,
            duels_lost INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_global_rank_history (snapshot_on, season_id, scope_key, deck_id),
            KEY idx_ygo_global_rank_deck (deck_id, season_id, snapshot_on),
            KEY idx_ygo_global_rank_position (season_id, snapshot_on, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_event_summaries (
            competition_id INT PRIMARY KEY,
            season_id INT NOT NULL DEFAULT 0,
            finalized_on DATETIME NULL,
            champion_deck_id INT NULL,
            runner_up_deck_id INT NULL,
            champion_team_id INT NULL,
            runner_up_team_id INT NULL,
            standings_json LONGTEXT NOT NULL,
            metagame_json LONGTEXT NOT NULL,
            statistics_json LONGTEXT NOT NULL,
            summary_hash CHAR(64) NOT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ygo_event_summary_season (season_id, finalized_on)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_medal_progress (
            entity_type VARCHAR(20) NOT NULL,
            entity_id INT NOT NULL,
            medal_code VARCHAR(80) NOT NULL,
            medal_name VARCHAR(160) NOT NULL,
            rarity VARCHAR(20) NOT NULL,
            secret TINYINT(1) NOT NULL DEFAULT 0,
            current_value DECIMAL(14,2) NOT NULL DEFAULT 0,
            target_value DECIMAL(14,2) NOT NULL DEFAULT 1,
            unlocked TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (entity_type, entity_id, medal_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_sync_requests (
            request_id VARCHAR(80) PRIMARY KEY,
            match_id INT NOT NULL,
            payload_hash CHAR(64) NOT NULL,
            result_code VARCHAR(80) NOT NULL DEFAULT 'resultado',
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ygo_sync_match (match_id, synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_match_snapshots', 'decklist_hash', "CHAR(64) NULL AFTER decklist_json");
    ygo_add_column($pdo, 'sx_ygo_match_snapshots', 'snapshot_status', "VARCHAR(20) NOT NULL DEFAULT 'exact' AFTER data_origin");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(60) NOT NULL,
            entity_id INT NULL,
            action VARCHAR(80) NOT NULL,
            payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_competition_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NOT NULL,
            from_status VARCHAR(40) NULL,
            to_status VARCHAR(40) NOT NULL,
            action VARCHAR(80) NOT NULL,
            payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ygo_status_history_competition (competition_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_standing_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NOT NULL,
            snapshot_key VARCHAR(40) NOT NULL DEFAULT 'current',
            round_number INT NOT NULL DEFAULT 0,
            deck_id INT NOT NULL,
            position INT NOT NULL,
            points INT NOT NULL DEFAULT 0,
            played INT NOT NULL DEFAULT 0,
            wins INT NOT NULL DEFAULT 0,
            draws INT NOT NULL DEFAULT 0,
            losses INT NOT NULL DEFAULT 0,
            duels_won INT NOT NULL DEFAULT 0,
            duels_lost INT NOT NULL DEFAULT 0,
            difference INT NOT NULL DEFAULT 0,
            payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_standing_snapshot (competition_id, snapshot_key, deck_id),
            KEY idx_ygo_standing_snapshot_competition (competition_id, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $historyCount = (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_competition_status_history')->fetchColumn();
    if ($historyCount === 0) {
        $pdo->exec("
            INSERT INTO sx_ygo_competition_status_history (season_id, competition_id, from_status, to_status, action, payload)
            SELECT season_id, id, NULL, status, 'bootstrap_status_snapshot', '{\"source\":\"ensure_ygo_tables\"}'
            FROM sx_ygo_competitions
            WHERE active = 1
        ");
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_deck_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deck_id INT NOT NULL,
            card_name VARCHAR(160) NOT NULL,
            role VARCHAR(80) NULL,
            quantity INT NOT NULL DEFAULT 1,
            section VARCHAR(40) NOT NULL DEFAULT 'main',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_deck_cards', 'ygoprodeck_id', 'INT NULL AFTER notes');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_deck_media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deck_id INT NOT NULL,
            media_type VARCHAR(40) NOT NULL DEFAULT 'image',
            url VARCHAR(500) NOT NULL,
            title VARCHAR(160) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_honors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NULL,
            champion_deck_id INT NULL,
            runner_up_deck_id INT NULL,
            title VARCHAR(160) NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_players (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NULL,
            display_name VARCHAR(160) NOT NULL,
            alias VARCHAR(120) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ygo_players_team (team_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_player_decks (
            player_id INT NOT NULL,
            deck_id INT NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (player_id, deck_id),
            KEY idx_ygo_player_decks_deck (deck_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_logo_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(30) NOT NULL,
            entity_id INT NOT NULL,
            logo_url VARCHAR(500) NOT NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'upload',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ygo_logo_history_entity (entity_type, entity_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source)
        SELECT 'deck', d.id, d.cover_url, 'current' FROM sx_ygo_decks d
        WHERE d.cover_url IS NOT NULL AND d.cover_url <> ''
          AND NOT EXISTS (SELECT 1 FROM sx_ygo_logo_history h WHERE h.entity_type='deck' AND h.entity_id=d.id AND h.logo_url=d.cover_url)");
    $pdo->exec("INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source)
        SELECT 'team', t.id, t.logo_url, 'current' FROM sx_ygo_teams t
        WHERE t.logo_url IS NOT NULL AND t.logo_url <> ''
          AND NOT EXISTS (SELECT 1 FROM sx_ygo_logo_history h WHERE h.entity_type='team' AND h.entity_id=t.id AND h.logo_url=t.logo_url)");
    $pdo->exec("INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source)
        SELECT 'competition', c.id, c.logo_url, 'current' FROM sx_ygo_competitions c
        WHERE c.logo_url IS NOT NULL AND c.logo_url <> ''
          AND NOT EXISTS (SELECT 1 FROM sx_ygo_logo_history h WHERE h.entity_type='competition' AND h.entity_id=c.id AND h.logo_url=c.logo_url)");
    $historyExists = $pdo->prepare('SELECT id FROM sx_ygo_logo_history WHERE entity_type=? AND entity_id=? AND logo_url=? LIMIT 1');
    $historyImport = $pdo->prepare('INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source, created_at) VALUES (?, ?, ?, "imported", FROM_UNIXTIME(?))');
    foreach (['deck' => 'decks', 'team' => 'teams'] as $entityType => $folderName) {
        $baseDir = YGO_APP_ROOT . '/assets/uploads/' . $folderName;
        if (!is_dir($baseDir)) {
            continue;
        }
        foreach (glob($baseDir . '/' . $entityType . '-*', GLOB_ONLYDIR) ?: [] as $entityDir) {
            if (!preg_match('/' . preg_quote($entityType, '/') . '-(\d+)$/', basename($entityDir), $entityMatch)) {
                continue;
            }
            $entityId = (int) $entityMatch[1];
            foreach (glob($entityDir . '/*.{png,jpg,jpeg,webp,gif}', GLOB_BRACE) ?: [] as $imagePath) {
                $logoUrl = './assets/uploads/' . $folderName . '/' . basename($entityDir) . '/' . basename($imagePath);
                $historyExists->execute([$entityType, $entityId, $logoUrl]);
                if (!$historyExists->fetchColumn()) {
                    $historyImport->execute([$entityType, $entityId, $logoUrl, filemtime($imagePath) ?: time()]);
                }
            }
        }
    }
    ygo_add_column($pdo, 'sx_ygo_honors', 'champion_team_id', 'INT NULL AFTER champion_deck_id');
    ygo_add_column($pdo, 'sx_ygo_honors', 'runner_up_team_id', 'INT NULL AFTER runner_up_deck_id');
    $pdo->exec('UPDATE sx_ygo_honors h LEFT JOIN sx_ygo_decks d ON d.id = h.champion_deck_id SET h.champion_team_id = d.team_id WHERE h.champion_team_id IS NULL AND d.team_id IS NOT NULL');
    $pdo->exec('UPDATE sx_ygo_honors h LEFT JOIN sx_ygo_decks d ON d.id = h.runner_up_deck_id SET h.runner_up_team_id = d.team_id WHERE h.runner_up_team_id IS NULL AND d.team_id IS NOT NULL');
    $analyticsIndexes = [
        ['sx_ygo_matches', 'idx_ygo_matches_analytics', '(competition_id, status, scheduled_on, phase)'],
        ['sx_ygo_match_results', 'idx_ygo_results_analytics', '(match_id, result)'],
        ['sx_ygo_competition_decks', 'idx_ygo_competition_decks_analytics', '(competition_id, active, deck_id)'],
        ['sx_ygo_honors', 'idx_ygo_honors_analytics', '(season_id, competition_id)'],
        ['sx_ygo_audit_log', 'idx_ygo_audit_analytics', '(action, created_at)'],
    ];
    foreach ($analyticsIndexes as [$table, $index, $columns]) {
        if (ygo_table_exists($pdo, $table) && !ygo_index_exists($pdo, $table, $index)) {
            $pdo->exec("ALTER TABLE $table ADD INDEX $index $columns");
        }
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_setting (season_id, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_catalog_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL DEFAULT 0,
            catalog_key VARCHAR(80) NOT NULL,
            item_code VARCHAR(120) NOT NULL,
            label VARCHAR(180) NOT NULL,
            icon VARCHAR(80) NULL,
            accent VARCHAR(30) NULL,
            sort_order INT NOT NULL DEFAULT 100,
            meta_json TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_catalog_item (season_id, catalog_key, item_code),
            KEY ix_ygo_catalog_group (season_id, catalog_key, active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_ui_labels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL DEFAULT 0,
            label_key VARCHAR(120) NOT NULL,
            title VARCHAR(180) NOT NULL,
            subtitle TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_ui_label (season_id, label_key),
            KEY ix_ygo_ui_label_active (season_id, active, label_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
