<?php
declare(strict_types=1);

/**
 * event-catalog: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_event_model_options(): array
{
    return [
        'competition_system' => [
            'manual' => 'Manual / personalizado',
            'free_for_all' => 'Battle Royale / Free-for-All (3-4 jugadores)',
            'swiss' => 'Suizo',
            'swiss_top_cut' => 'Suizo + Top Cut',
            'single_elimination' => 'Eliminacion directa',
            'double_elimination' => 'Doble eliminacion',
            'round_robin' => 'Round Robin',
            'double_round_robin' => 'Round Robin ida y vuelta',
            'groups' => 'Fase de grupos',
            'groups_knockout' => 'Grupos + eliminacion',
            'league_points' => 'Liga por puntos',
            'playoffs' => 'Playoffs',
            'top_cut' => 'Top Cut',
            'bracket' => 'Bracket / llave',
            'flights' => 'Flights',
            'ladder' => 'Ladder',
            'king_of_the_hill' => 'King of the Hill',
            'gauntlet' => 'Gauntlet',
            'series' => 'Serie / final',
            'season_playoffs' => 'Temporada + playoffs',
            'custom' => 'Custom',
        ],
        'access_type' => [
            'OPEN' => 'Open',
            'INVITATIONAL' => 'Invitational',
            'QUALIFIER' => 'Clasificatorio',
            'LAST_CHANCE_QUALIFIER' => 'Last Chance Qualifier',
            'CUSTOM' => 'Custom',
        ],
        'participation' => [
            'INDIVIDUAL_1V1' => 'Individual 1v1',
            'FREE_FOR_ALL' => 'Individual multijugador (3-4)',
            'TAG_2V2' => 'Tag 2v2',
            'TEAM_3V3' => 'Equipos 3v3',
            'CUSTOM_TEAM' => 'Equipo personalizado',
        ],
        'construction' => [
            'CONSTRUCTED' => 'Constructed',
            'SEALED' => 'Sealed',
            'DRAFT' => 'Draft',
            'STRUCTURE_DECK' => 'Structure Deck',
            'DIGITAL' => 'Digital',
            'CUSTOM' => 'Custom',
        ],
        'environment' => [
            'PRESENCIAL' => 'Presencial',
            'ONLINE' => 'Online',
            'REMOTE_DUEL' => 'Remote Duel',
            'HYBRID' => 'Hibrido',
        ],
        'level' => [
            'CASUAL' => 'Casual',
            'BEGINNER' => 'Beginner',
            'ACADEMY' => 'Academy',
            'LOCAL' => 'Local',
            'COMPETITIVE' => 'Competitivo',
            'OFFICIAL' => 'Oficial',
            'PREMIER' => 'Premier',
            'CHAMPIONSHIP' => 'Championship',
            'WORLD' => 'Mundial',
            'PROMOTIONAL' => 'Promocional',
            'EXHIBITION' => 'Exhibicion',
            'SPECIAL' => 'Especial',
        ],
        'event_flow' => [
            'STANDALONE' => 'Independiente',
            'QUALIFIES_TO_EVENT' => 'Clasifica a otro evento',
            'RECEIVES_FROM_EVENT' => 'Recibe clasificados',
            'SEASON' => 'Temporada',
            'SEASON_PLAYOFF' => 'Playoff de temporada',
            'MULTI_STAGE' => 'Multifase',
            'CHAMPIONSHIP_PATH' => 'Ruta a campeonato',
            'CUSTOM' => 'Custom',
        ],
        'participant_source' => [
            'MANUAL' => 'Manual',
            'OPEN_REGISTRATION' => 'Registro abierto',
            'RANKING' => 'Ranking',
            'PREVIOUS_EVENT' => 'Evento previo',
            'QUALIFIER' => 'Clasificatorio',
            'LEAGUE' => 'Liga',
            'CUP' => 'Copa',
            'INVITATION' => 'Invitacion',
            'DEFENDING_CHAMPION' => 'Campeon defensor',
            'DIVISION' => 'Division',
            'CUSTOM' => 'Custom',
        ],
        'advancement_rule' => [
            'FINAL_STANDINGS' => 'Tabla final, sin avance interno',
            'SOURCE_ADMISSION' => 'Plazas configuradas en el evento destino',
            'MANUAL_ENTRIES' => 'Seleccion manual de participantes de entrada',
            'GROUP_TOP_K' => 'Cupos por grupo y luego ganador de la llave',
            'MANUAL_SELECTION' => 'Seleccion manual',
            'TOP_N' => 'Top N',
            'TOP_PERCENTAGE' => 'Porcentaje de plazas por fuente',
            'MINIMUM_POINTS' => 'Puntos minimos',
            'GROUP_POSITION' => 'Posicion de grupo',
            'WINNER_ONLY' => 'Solo ganador',
            'RUNNER_UP' => 'Subcampeon',
            'RANKING_THRESHOLD' => 'Corte por ranking',
            'BEST_RECORD' => 'Mejor record',
            'CUSTOM' => 'Custom',
        ],
        'draw_policy' => [
            'ALLOWED' => 'Empate permitido',
            'NOT_ALLOWED' => 'Empate no permitido',
            'EXTRA_DUEL' => 'Duelo extra',
            'EXTRA_MATCH' => 'Match extra',
            'SUDDEN_DEATH' => 'Muerte subita',
            'USE_TIEBREAKER' => 'Usar desempate',
            'MANUAL_DECISION' => 'Decision manual',
            'CUSTOM' => 'Custom',
        ],
        'ranking_impact' => [
            'NONE' => 'No afecta ranking',
            'FULL' => 'Impacto completo',
            'REDUCED' => 'Impacto reducido',
            'BONUS' => 'Bonificacion',
            'CUSTOM' => 'Custom',
        ],
        'deck_policy' => [
            'NO_REGISTRATION' => 'Sin registro de deck',
            'OPTIONAL_REGISTRATION' => 'Registro opcional',
            'REQUIRED_REGISTRATION' => 'Registro requerido',
            'DECK_LOCK_EVENT' => 'Deck bloqueado por evento',
            'DECK_LOCK_PHASE' => 'Deck bloqueado por fase',
            'DECK_LOCK_ROUND' => 'Deck bloqueado por ronda',
            'ALLOW_DECK_CHANGE' => 'Permite cambio de deck',
            'MULTIPLE_DECKS_ALLOWED' => 'Multiples decks permitidos',
            'CUSTOM' => 'Custom',
        ],
        'phase_structure' => [
            'NONE' => 'Sin fases',
            'GROUPS' => 'Grupos',
            'REGULAR_SEASON' => 'Temporada regular',
            'PLAYOFFS' => 'Playoffs',
            'TOP_CUT' => 'Top Cut',
            'SEMIFINAL' => 'Semifinal',
            'FINAL' => 'Final',
            'GRAND_FINAL' => 'Grand Final',
            'GROUPS_TOP_CUT_FINAL' => 'Grupos + Top Cut + Final',
            'SEASON_PLAYOFF_FINAL' => 'Temporada + Playoffs + Final',
            'CUSTOM' => 'Custom',
        ],
    ];
}

function ygo_allowed_execution_models_for_type(string $type): array
{
    $map = [
        'qualifier' => ['manual', 'swiss', 'swiss_top_cut', 'top_cut', 'flights', 'ladder', 'custom'],
        'tournament' => ['gauntlet', 'round_robin', 'double_round_robin', 'league_points', 'manual', 'swiss', 'swiss_top_cut', 'single_elimination', 'double_elimination', 'groups', 'groups_knockout', 'top_cut', 'bracket', 'flights', 'custom'],
        'league' => ['groups', 'swiss', 'swiss_top_cut', 'manual', 'round_robin', 'double_round_robin', 'league_points', 'season_playoffs', 'playoffs', 'custom'],
        'cup' => ['series', 'swiss', 'swiss_top_cut', 'manual', 'single_elimination', 'double_elimination', 'groups_knockout', 'top_cut', 'bracket', 'playoffs', 'custom'],
        'world' => ['manual', 'groups', 'groups_knockout', 'swiss_top_cut', 'top_cut', 'bracket', 'single_elimination', 'custom'],
        'final' => ['manual', 'series', 'single_elimination', 'top_cut', 'playoffs', 'custom'],
        'friendly' => ['series', 'manual', 'swiss', 'round_robin', 'single_elimination', 'king_of_the_hill', 'gauntlet', 'custom'],
    ];
    return $map[$type] ?? ['free_for_all', 'manual', 'swiss', 'swiss_top_cut', 'single_elimination', 'double_elimination', 'round_robin', 'double_round_robin', 'groups', 'groups_knockout', 'league_points', 'playoffs', 'top_cut', 'bracket', 'flights', 'ladder', 'king_of_the_hill', 'gauntlet', 'series', 'season_playoffs', 'custom'];
}

function ygo_event_template_type(string $code): string
{
    foreach (ygo_event_available_templates() as $template) {
        if ((string) ($template['code'] ?? '') === $code) {
            return (string) ($template['type'] ?? '');
        }
    }
    return '';
}

function ygo_ruleset_version_catalog(): array
{
    $items = [
        ['rule', 'OCG', 'Original Rules', '1999', '1999-01-01'],
        ['rule', 'OCG', 'Expert Rules', '1999', '1999-01-01'],
        ['rule', 'OCG', 'New Expert Rules', '2000', '2000-01-01'],
        ['rule', 'TCG', 'TCG Classic Rules / New Expert Rules Era', '2002', '2002-01-01'],
        ['rule', 'OCG / TCG', 'Master Rule 1', '2008', '2008-01-01'],
        ['rule', 'OCG / TCG', 'Master Rule 2', '2011', '2011-01-01'],
        ['rule', 'OCG', 'Master Rule 3', '21/03/2014', '2014-03-21'],
        ['rule', 'TCG', 'Master Rule 3', '2014', '2014-01-01'],
        ['rule', 'OCG', 'New Master Rule / Master Rule 4', '25/03/2017', '2017-03-25'],
        ['rule', 'TCG', 'Master Rule 4', '2017', '2017-01-01'],
        ['rule', 'OCG / TCG', 'Master Rule April 2020 Revision', '01/04/2020', '2020-04-01'],
        ['rule', 'TCG', '2021 Rules Update', '2021', '2021-01-01'],
        ['rule', 'TCG', 'Time Wizard Rules', '2022', '2022-01-01'],
        ['rule', 'TCG', 'Time Travel Rules', '2025', '2025-01-01'],
        ['rule', 'TCG', 'Genesys Rules', '2025', '2025-01-01'],
        ['rule', 'OCG / TCG', 'Current Master Rule Framework', '2020-Actualidad', '9999-12-31'],
    ];
    foreach ([
        ['TCG May 2026', '18/05/2026', '2026-05-18'],
        ['TCG February 2026', '02/02/2026', '2026-02-02'],
        ['TCG October 2025', '27/10/2025', '2025-10-27'],
        ['TCG September 2025', 'Septiembre 2025', '2025-09-01'],
        ['TCG April 2025', 'Abril 2025', '2025-04-01'],
        ['TCG December 2024', 'Diciembre 2024', '2024-12-01'],
        ['TCG September 2024', 'Septiembre 2024', '2024-09-01'],
        ['TCG April 2024', 'Abril 2024', '2024-04-01'],
        ['TCG January 2024', 'Enero 2024', '2024-01-01'],
        ['TCG September 2023', 'Septiembre 2023', '2023-09-01'],
        ['TCG June 2023', 'Junio 2023', '2023-06-01'],
        ['TCG February 2023', 'Febrero 2023', '2023-02-01'],
        ['TCG December 2022', 'Diciembre 2022', '2022-12-01'],
        ['TCG October 2022', 'Octubre 2022', '2022-10-01'],
        ['TCG May 2022', 'Mayo 2022', '2022-05-01'],
        ['TCG February 2022', 'Febrero 2022', '2022-02-01'],
        ['TCG October 2021', 'Octubre 2021', '2021-10-01'],
        ['TCG July 2021', 'Julio 2021', '2021-07-01'],
        ['TCG March 2021', 'Marzo 2021', '2021-03-01'],
        ['TCG December 2020', 'Diciembre 2020', '2020-12-01'],
        ['TCG September 2020', 'Septiembre 2020', '2020-09-01'],
        ['TCG June 2020', 'Junio 2020', '2020-06-01'],
        ['TCG April 2020', 'Abril 2020', '2020-04-01'],
        ['TCG January 2020', 'Enero 2020', '2020-01-01'],
        ['TCG October 2019', '14/10/2019', '2019-10-14'],
        ['TCG July 2019', 'Julio 2019', '2019-07-01'],
        ['TCG April 2019', 'Abril 2019', '2019-04-01'],
        ['TCG January 2019', 'Enero 2019', '2019-01-01'],
        ['TCG December 2018', 'Diciembre 2018', '2018-12-01'],
        ['TCG September 2018', 'Septiembre 2018', '2018-09-01'],
        ['TCG May 2018', 'Mayo 2018', '2018-05-01'],
        ['TCG February 2018', 'Febrero 2018', '2018-02-01'],
        ['TCG November 2017', 'Noviembre 2017', '2017-11-01'],
        ['TCG September 2017', 'Septiembre 2017', '2017-09-01'],
        ['TCG June 2017', 'Junio 2017', '2017-06-01'],
        ['TCG March 2017', 'Marzo 2017', '2017-03-01'],
        ['TCG August 2016', 'Agosto 2016', '2016-08-01'],
        ['TCG April 2016', 'Abril 2016', '2016-04-01'],
        ['TCG November 2015', 'Noviembre 2015', '2015-11-01'],
        ['TCG July 2015', 'Julio 2015', '2015-07-01'],
        ['TCG April 2015', 'Abril 2015', '2015-04-01'],
        ['TCG January 2015', 'Enero 2015', '2015-01-01'],
        ['TCG October 2014', 'Octubre 2014', '2014-10-01'],
        ['TCG July 2014', 'Julio 2014', '2014-07-01'],
        ['TCG April 2014', '01/04/2014', '2014-04-01'],
        ['TCG January 2014', 'Enero 2014', '2014-01-01'],
        ['TCG September 2013', '01/09/2013', '2013-09-01'],
        ['TCG March 2013', '01/03/2013', '2013-03-01'],
        ['TCG September 2012', '01/09/2012', '2012-09-01'],
        ['TCG March 2012', '01/03/2012', '2012-03-01'],
        ['TCG September 2011', '01/09/2011', '2011-09-01'],
        ['TCG March 2011', '01/03/2011', '2011-03-01'],
        ['TCG September 2010', '01/09/2010', '2010-09-01'],
        ['TCG March 2010', '01/03/2010', '2010-03-01'],
        ['TCG September 2009', '01/09/2009', '2009-09-01'],
        ['TCG March 2009', '01/03/2009', '2009-03-01'],
        ['TCG September 2008', '01/09/2008', '2008-09-01'],
        ['TCG March 2008', '01/03/2008', '2008-03-01'],
        ['TCG September 2007', '01/09/2007', '2007-09-01'],
        ['TCG March 2007', '01/03/2007', '2007-03-01'],
        ['TCG September 2006', '01/09/2006', '2006-09-01'],
        ['TCG March 2006', '01/03/2006', '2006-03-01'],
        ['TCG September 2005', '01/09/2005', '2005-09-01'],
        ['TCG March 2005', '01/03/2005', '2005-03-01'],
        ['TCG September 2004', 'Septiembre 2004', '2004-09-01'],
        ['TCG March 2004', 'Marzo 2004', '2004-03-01'],
        ['TCG September 2003', 'Septiembre 2003', '2003-09-01'],
        ['TCG January 2003', 'Enero 2003', '2003-01-01'],
        ['TCG May 2001', 'Mayo 2001', '2001-05-01'],
        ['TCG November 2000', 'Noviembre 2000', '2000-11-01'],
        ['TCG April 2000', 'Abril 2000', '2000-04-01'],
        ['TCG August 1999', 'Agosto 1999', '1999-08-01'],
    ] as $row) {
        $items[] = ['banlist', 'TCG', $row[0], $row[1], $row[2]];
    }
    foreach ([
        ['OCG July 2026', '01/07/2026', '2026-07-01'], ['OCG April 2026', '01/04/2026', '2026-04-01'], ['OCG January 2026', '01/01/2026', '2026-01-01'],
        ['OCG October 2025', '01/10/2025', '2025-10-01'], ['OCG July 2025', '01/07/2025', '2025-07-01'], ['OCG April 2025', '01/04/2025', '2025-04-01'], ['OCG January 2025', '01/01/2025', '2025-01-01'],
        ['OCG October 2024', '01/10/2024', '2024-10-01'], ['OCG July 2024', '01/07/2024', '2024-07-01'], ['OCG April 2024', '01/04/2024', '2024-04-01'], ['OCG January 2024', '01/01/2024', '2024-01-01'],
        ['OCG October 2023', '01/10/2023', '2023-10-01'], ['OCG July 2023', '01/07/2023', '2023-07-01'], ['OCG April 2023', '01/04/2023', '2023-04-01'], ['OCG January 2023', '01/01/2023', '2023-01-01'],
        ['OCG October 2022', '01/10/2022', '2022-10-01'], ['OCG July 2022', '01/07/2022', '2022-07-01'], ['OCG April 2022', '01/04/2022', '2022-04-01'], ['OCG January 2022', '01/01/2022', '2022-01-01'],
        ['OCG October 2021', '01/10/2021', '2021-10-01'], ['OCG July 2021', '01/07/2021', '2021-07-01'], ['OCG April 2021', '01/04/2021', '2021-04-01'], ['OCG January 2021', '01/01/2021', '2021-01-01'],
        ['OCG October 2020', '01/10/2020', '2020-10-01'], ['OCG July 2020', '01/07/2020', '2020-07-01'], ['OCG April 2020', '01/04/2020', '2020-04-01'], ['OCG January 2020', '01/01/2020', '2020-01-01'],
        ['OCG October 2019', '01/10/2019', '2019-10-01'], ['OCG July 2019', '01/07/2019', '2019-07-01'], ['OCG April 2019', '01/04/2019', '2019-04-01'], ['OCG January 2019', '01/01/2019', '2019-01-01'],
        ['OCG October 2018', '01/10/2018', '2018-10-01'], ['OCG July 2018', '01/07/2018', '2018-07-01'], ['OCG April 2018', '01/04/2018', '2018-04-01'], ['OCG January 2018', '01/01/2018', '2018-01-01'],
        ['OCG October 2017', '01/10/2017', '2017-10-01'], ['OCG July 2017', '01/07/2017', '2017-07-01'], ['OCG April 2017', '01/04/2017', '2017-04-01'], ['OCG January 2017', '01/01/2017', '2017-01-01'],
        ['OCG October 2016', '01/10/2016', '2016-10-01'], ['OCG July 2016', '01/07/2016', '2016-07-01'], ['OCG April 2016', '01/04/2016', '2016-04-01'], ['OCG January 2016', '01/01/2016', '2016-01-01'],
        ['OCG October 2015', '01/10/2015', '2015-10-01'], ['OCG July 2015', '01/07/2015', '2015-07-01'], ['OCG April 2015', '01/04/2015', '2015-04-01'], ['OCG January 2015', '01/01/2015', '2015-01-01'],
        ['OCG October 2014', '01/10/2014', '2014-10-01'], ['OCG July 2014', '01/07/2014', '2014-07-01'], ['OCG April 2014', '01/04/2014', '2014-04-01'], ['OCG February 2014', '01/02/2014', '2014-02-01'],
        ['OCG November 2013', '01/11/2013', '2013-11-01'], ['OCG September 2013', '01/09/2013', '2013-09-01'], ['OCG March 2013', '01/03/2013', '2013-03-01'],
        ['OCG September 2012', '01/09/2012', '2012-09-01'], ['OCG March 2012', '01/03/2012', '2012-03-01'],
        ['OCG September 2011', '01/09/2011', '2011-09-01'], ['OCG March 2011', '01/03/2011', '2011-03-01'],
        ['OCG September 2010', '01/09/2010', '2010-09-01'], ['OCG March 2010', '01/03/2010', '2010-03-01'],
        ['OCG September 2009', '01/09/2009', '2009-09-01'], ['OCG March 2009', '01/03/2009', '2009-03-01'],
        ['OCG September 2008', '01/09/2008', '2008-09-01'], ['OCG March 2008', '01/03/2008', '2008-03-01'],
        ['OCG September 2007', '01/09/2007', '2007-09-01'], ['OCG March 2007', '01/03/2007', '2007-03-01'],
        ['OCG September 2006', '01/09/2006', '2006-09-01'], ['OCG March 2006', '01/03/2006', '2006-03-01'],
        ['OCG September 2005', '01/09/2005', '2005-09-01'], ['OCG March 2005', '01/03/2005', '2005-03-01'],
        ['OCG September 2004', '01/09/2004', '2004-09-01'], ['OCG March 2004', '01/03/2004', '2004-03-01'],
        ['OCG October 2003', '15/10/2003', '2003-10-15'], ['OCG July 2003', '01/07/2003', '2003-07-01'], ['OCG April 2003', '10/04/2003', '2003-04-10'], ['OCG January 2003', '01/01/2003', '2003-01-01'],
        ['OCG May 2002', '01/05/2002', '2002-05-01'], ['OCG January 2002', '01/01/2002', '2002-01-01'],
        ['OCG May 2001', '28/05/2001', '2001-05-28'], ['OCG January 2001', '15/01/2001', '2001-01-15'],
        ['OCG November 2000', '01/11/2000', '2000-11-01'], ['OCG August 2000', '15/08/2000', '2000-08-15'], ['OCG July 2000', '15/07/2000', '2000-07-15'], ['OCG May 2000', '15/05/2000', '2000-05-15'], ['OCG April 2000', '01/04/2000', '2000-04-01'],
        ['OCG 1999', '1999', '1999-01-01'],
    ] as $row) {
        $items[] = ['banlist', 'OCG', $row[0], $row[1], $row[2]];
    }
    usort($items, static fn(array $a, array $b): int => strcmp($b[4], $a[4]) ?: strcmp($b[2], $a[2]));
    return array_map(static function (array $item): array {
        return [
            'category' => $item[0],
            'system' => $item[1],
            'version' => $item[2],
            'date_label' => $item[3],
            'sort_date' => $item[4],
            'value' => strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $item[1] . '_' . $item[2] . '_' . $item[4])),
        ];
    }, $items);
}

function ygo_event_prompt_templates(): array
{
    $rows = [
        ['clasificatorio_divisiones_3_duelos', 'Clasificatorio | Suizo 3 duelos | Ranking para divisiones', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['torneo_local', 'Torneo Local', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Local'],
        ['liga', 'Liga', 'Round Robin / Suizo acumulativo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Local'],
        ['copa', 'Copa', 'Grupos + Eliminacion / Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['clasificatorio', 'Clasificatorio', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['regional_qualifier', 'Regional Qualifier', 'Suizo + Clasificacion', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Oficial'],
        ['national_qualifier', 'National Qualifier', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Oficial'],
        ['wcq_world_championship_qualifier', 'WCQ - World Championship Qualifier', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Premier'],
        ['ycs_yu_gi_oh_championship_series', 'YCS - Yu-Gi-Oh! Championship Series', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Premier'],
        ['team_ycs', 'Team YCS', 'Suizo + Top Cut', 'Equipos 3v3', 'Constructed', 'TCG Advanced', 'Presencial', 'Premier'],
        ['world_championship', 'World Championship', 'Rondas + Eliminacion', 'Individual 1v1', 'Constructed', 'Reglamento Mundial', 'Presencial', 'Mundial'],
        ['ots_championship', 'OTS Championship', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Oficial'],
        ['ots_tournament_series', 'OTS Tournament Series', 'Suizo + Top Cut', 'Equipos 3v3', 'Constructed', 'TCG Advanced', 'Presencial', 'Oficial'],
        ['ycs_vip_qualifier', 'YCS VIP Qualifier', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Clasificatorio'],
        ['dragon_duel', 'Dragon Duel', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Juvenil'],
        ['dragon_duel_championship', 'Dragon Duel Championship', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Juvenil'],
        ['remote_duel_regional', 'Remote Duel Regional', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Remoto', 'Oficial'],
        ['remote_duel_ycs', 'Remote Duel YCS', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Remoto', 'Premier'],
        ['genesys_regional_qualifier', 'Genesys Regional Qualifier', 'Suizo + Clasificacion', 'Individual 1v1', 'Constructed', 'Genesys', 'Presencial', 'Oficial'],
        ['genesys_championship', 'Genesys Championship', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'Genesys', 'Presencial', 'Oficial'],
        ['yugioh_day', 'Yu-Gi-Oh! Day', 'Suizo / Evento especial', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Promocional'],
        ['core_booster_premiere', 'Core Booster Premiere!', 'Evento de estreno', 'Individual 1v1', 'Sealed / Constructed', 'Reglas especiales', 'Presencial', 'Promocional'],
        ['world_championship_celebration', 'World Championship Celebration', 'Suizo / Especial', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Celebracion'],
        ['attack_of_the_giant_card', 'Attack of the Giant Card!!', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Evento publico'],
        ['win_a_mat', 'Win-A-Mat', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Evento publico'],
        ['public_event_playoff', 'Public Event Playoff', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Evento publico'],
        ['structure_deck_tournament', 'Structure Deck Tournament', 'Eliminacion / Flight', 'Individual 1v1', 'Structure Deck', 'Reglas del evento', 'Presencial', 'Especial'],
        ['charity_tournament', 'Charity Tournament', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Especial'],
        ['team_3v3', '3 vs 3 Team Dueling', 'Suizo / Eliminacion', 'Equipos 3v3', 'Constructed', 'TCG Advanced', 'Presencial', 'Equipos'],
        ['tag_duel', 'Tag Duel', 'Suizo / Eliminacion', 'Equipos 2v2', 'Constructed', 'Tag Duel', 'Presencial', 'Alternativo'],
        ['rivalry_of_warlords', 'Rivalry of Warlords', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Rivalry of Warlords', 'Presencial', 'Alternativo'],
        ['time_wizard', 'Time Wizard', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Historico', 'Presencial', 'Alternativo'],
        ['time_travel', 'Time Travel', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Historico con reglas modernas', 'Presencial', 'Alternativo'],
        ['goat_format_tournament', 'GOAT Format Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'GOAT 2005', 'Presencial', 'Historico'],
        ['edison_format_tournament', 'Edison Format Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Edison 2010', 'Presencial', 'Historico'],
        ['heart_of_the_underdog', 'Heart of the Underdog', 'Suizo', 'Individual 1v1', 'Constructed', 'Heart of the Underdog', 'Presencial', 'Alternativo'],
        ['common_charity', 'Common Charity', 'Suizo', 'Individual 1v1', 'Constructed', 'Common Charity', 'Presencial', 'Alternativo'],
        ['deck_master', 'Deck Master', 'Suizo', 'Individual 1v1', 'Constructed', 'Deck Master', 'Presencial', 'Alternativo'],
        ['pegasus_challenge', 'Pegasus Challenge', 'Suizo / Reglas especiales', 'Individual 1v1', 'Constructed', 'Pegasus Challenge', 'Presencial', 'Alternativo'],
        ['speed_duel_tournament', 'Speed Duel Tournament', 'Suizo', 'Individual 1v1', 'Constructed', 'Speed Duel', 'Presencial', 'Alternativo'],
        ['speed_duel_main_event', 'Speed Duel Main Event', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'Speed Duel', 'Presencial', 'Competitivo'],
        ['master_duel_tournament', 'Master Duel Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Digital', 'Master Duel', 'Online', 'Digital'],
        ['duel_links_tournament', 'Duel Links Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Digital', 'Duel Links', 'Online', 'Digital'],
        ['sealed_deck_tournament', 'Sealed Deck Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Sealed', 'Limitado', 'Presencial', 'Especial'],
        ['draft_tournament', 'Draft Tournament', 'Draft + Suizo / Eliminacion', 'Individual 1v1', 'Draft', 'Limitado', 'Presencial', 'Especial'],
        ['casual_tournament', 'Casual Tournament', 'Suizo / Libre', 'Individual 1v1', 'Constructed', 'Formato configurable', 'Presencial', 'Casual'],
        ['exhibition_match', 'Exhibition Match', 'Partida unica / Serie', 'Individual / Equipos', 'Constructed', 'Formato configurable', 'Presencial', 'Exhibicion'],
        ['invitational', 'Invitational', 'Suizo + Top Cut / Eliminacion', 'Individual 1v1', 'Constructed', 'Formato configurable', 'Presencial', 'Especial'],
        ['open_tournament', 'Open Tournament', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['championship', 'Championship', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['finals', 'Finals', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'Segun evento', 'Presencial', 'Final'],
        ['playoffs', 'Playoffs', 'Eliminacion directa', 'Individual / Equipos', 'Constructed', 'Segun evento', 'Presencial', 'Fase'],
        ['top_cut', 'Top Cut', 'Eliminacion directa', 'Individual / Equipos', 'Constructed', 'Segun evento', 'Presencial', 'Fase'],
        ['last_chance_qualifier', 'Last Chance Qualifier', 'Eliminacion / Suizo', 'Individual 1v1', 'Constructed', 'Segun evento', 'Presencial', 'Clasificatorio'],
        ['flight_tournament', 'Flight Tournament', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'Segun evento', 'Presencial', 'Mini torneo'],
        ['single_elimination_tournament', 'Single Elimination Tournament', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['double_elimination_tournament', 'Double Elimination Tournament', 'Doble eliminacion', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['round_robin_tournament', 'Round Robin Tournament', 'Todos contra todos', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['double_round_robin', 'Double Round Robin', 'Todos contra todos ida y vuelta', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['group_stage_tournament', 'Group Stage Tournament', 'Fase de grupos', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['groups_knockout', 'Groups + Knockout', 'Grupos + Eliminacion', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['swiss_tournament', 'Swiss Tournament', 'Suizo', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['swiss_top_cut', 'Swiss + Top Cut', 'Suizo + Eliminacion', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['league_table', 'League Table', 'Tabla por puntos', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Liga'],
        ['bracket_tournament', 'Bracket Tournament', 'Llave eliminatoria', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['best_of_1_event', 'Best of 1 Event', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial / Online', 'Generico'],
        ['best_of_3_event', 'Best of 3 Event', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'TCG estandar', 'Presencial / Online', 'Generico'],
        ['match_play', 'Match Play', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'TCG', 'Presencial', 'Competitivo'],
        ['team_league', 'Team League', 'Round Robin / Suizo', 'Equipos', 'Constructed', 'Configurable', 'Presencial', 'Liga'],
        ['team_cup', 'Team Cup', 'Grupos + Eliminacion', 'Equipos', 'Constructed', 'Configurable', 'Presencial', 'Copa'],
        ['team_championship', 'Team Championship', 'Suizo + Top Cut', 'Equipos', 'Constructed', 'Configurable', 'Presencial', 'Campeonato'],
        ['online_league', 'Online League', 'Round Robin / Suizo', 'Individual 1v1', 'Digital / Constructed', 'Configurable', 'Online', 'Liga'],
        ['online_cup', 'Online Cup', 'Suizo + Top Cut / Eliminacion', 'Individual 1v1', 'Digital', 'Configurable', 'Online', 'Copa'],
        ['online_qualifier', 'Online Qualifier', 'Suizo + Top Cut', 'Individual 1v1', 'Digital', 'Configurable', 'Online', 'Clasificatorio'],
        ['ranking_tournament', 'Ranking Tournament', 'Suizo / Liga', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Ranking'],
        ['ladder', 'Ladder', 'Clasificacion continua', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial / Online', 'Ranking'],
        ['king_of_the_hill', 'King of the Hill', 'Ganador permanece', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Casual'],
        ['gauntlet', 'Gauntlet', 'Serie contra multiples rivales', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Especial'],
        ['knockout_cup', 'Knockout Cup', 'Eliminacion directa', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Copa'],
        ['group_league', 'Group League', 'Round Robin por grupos', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Liga'],
        ['season_tournament', 'Season Tournament', 'Liga + Playoffs', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Temporada'],
        ['regular_season', 'Regular Season', 'Tabla / Round Robin', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Temporada'],
        ['postseason', 'Postseason', 'Playoffs', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Temporada'],
        ['grand_final', 'Grand Final', 'Final unica / Best of X', 'Individual / Equipos', 'Constructed', 'Segun evento', 'Presencial', 'Final'],
        ['super_cup', 'Super Cup', 'Campeones enfrentados', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Especial'],
        ['champions_cup', 'Champions Cup', 'Grupos + Eliminacion', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Copa'],
        ['masters_tournament', 'Masters Tournament', 'Invitacional / Suizo + Top', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Especial'],
        ['all_star_event', 'All-Star Event', 'Exhibicion / Equipos', 'Individual / Equipos', 'Constructed', 'Reglas especiales', 'Presencial', 'Exhibicion'],
        ['rookie_tournament', 'Rookie Tournament', 'Suizo', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Principiantes'],
        ['beginner_tournament', 'Beginner Tournament', 'Suizo / Casual', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Principiantes'],
        ['academy_tournament', 'Academy Tournament', 'Suizo / Liga', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Formacion'],
    ];
    return array_map(static function (array $row): array {
        [$code, $label, $system, $participation, $construction, $rules, $environment, $level] = $row;
        $haystack = strtolower($label . ' ' . $system . ' ' . $participation . ' ' . $rules . ' ' . $level);
        $typeByTemplate = [
            'torneo_local' => 'tournament',
            'liga' => 'league',
            'copa' => 'cup',
            'clasificatorio' => 'qualifier',
            'clasificatorio_divisiones_3_duelos' => 'qualifier',
            'regional_qualifier' => 'qualifier',
            'national_qualifier' => 'qualifier',
            'wcq_world_championship_qualifier' => 'qualifier',
            'ycs_yu_gi_oh_championship_series' => 'tournament',
            'team_ycs' => 'tournament',
            'world_championship' => 'world',
            'ots_championship' => 'tournament',
            'ots_tournament_series' => 'tournament',
            'ycs_vip_qualifier' => 'qualifier',
            'dragon_duel' => 'tournament',
            'dragon_duel_championship' => 'tournament',
            'remote_duel_regional' => 'qualifier',
            'remote_duel_ycs' => 'tournament',
            'genesys_regional_qualifier' => 'qualifier',
            'genesys_championship' => 'tournament',
            'yu_gi_oh_day' => 'special',
            'core_booster_premiere' => 'special',
            'world_championship_celebration' => 'special',
            'attack_of_the_giant_card' => 'special',
            'win_a_mat' => 'tournament',
            'public_event_playoff' => 'tournament',
            'structure_deck_tournament' => 'tournament',
            'charity_tournament' => 'special',
            'team_3v3' => 'tournament',
            'tag_duel' => 'tournament',
            'rivalry_of_warlords' => 'tournament',
            'time_wizard' => 'tournament',
            'time_travel' => 'tournament',
            'goat_format_tournament' => 'tournament',
            'edison_format_tournament' => 'tournament',
            'heart_of_the_underdog' => 'tournament',
            'common_charity' => 'tournament',
            'deck_master' => 'tournament',
            'pegasus_challenge' => 'special',
            'speed_duel_tournament' => 'tournament',
            'speed_duel_main_event' => 'tournament',
            'master_duel_tournament' => 'tournament',
            'duel_links_tournament' => 'tournament',
            'sealed_deck_tournament' => 'tournament',
            'draft_tournament' => 'tournament',
            'casual_tournament' => 'friendly',
            'exhibition_match' => 'friendly',
            'invitational' => 'tournament',
            'open_tournament' => 'tournament',
            'championship' => 'tournament',
            'finals' => 'final',
            'last_chance_qualifier' => 'qualifier',
            'flight_tournament' => 'tournament',
            'team_championship' => 'tournament',
            'online_qualifier' => 'qualifier',
            'ranking_tournament' => 'tournament',
            'season_tournament' => 'league',
            'grand_final' => 'final',
            'super_cup' => 'cup',
            'champions_cup' => 'cup',
            'masters_tournament' => 'tournament',
            'all_star_event' => 'special',
            'rookie_tournament' => 'tournament',
            'beginner_tournament' => 'tournament',
            'academy_tournament' => 'tournament',
        ];
        $type = $typeByTemplate[$code] ?? (str_contains($haystack, 'liga') || str_contains($haystack, 'league') || str_contains($haystack, 'round robin') || str_contains($haystack, 'season') || str_contains($haystack, 'temporada') ? 'league'
            : (str_contains($haystack, 'copa') || str_contains($haystack, 'cup') ? 'cup'
            : (str_contains($haystack, 'clasific') || str_contains($haystack, 'qualifier') || str_contains($haystack, 'ranking') || str_contains($haystack, 'ladder') ? 'qualifier'
            : (str_contains($haystack, 'world') || str_contains($haystack, 'mundial') ? 'world'
            : (str_contains($haystack, 'final') || str_contains($haystack, 'playoff') || str_contains($haystack, 'top cut') ? 'final'
            : (str_contains($haystack, 'casual') || str_contains($haystack, 'exhibition') || str_contains($haystack, 'exhibicion') ? 'friendly' : 'tournament'))))));
        $ruleset = str_contains($haystack, 'master duel') ? 'master_duel'
            : (str_contains($haystack, 'speed duel') ? 'speed_duel'
            : (str_contains($haystack, 'edison') ? 'edison'
            : (str_contains($haystack, 'goat') ? 'goat'
            : (str_contains($haystack, 'genesys') ? 'genesis'
            : (str_contains($haystack, 'ocg') && !str_contains($haystack, 'tcg') ? 'ocg'
            : (str_contains($haystack, 'tcg') ? 'tcg_advanced' : 'custom'))))));
        $matchFormat = str_contains($haystack, '3v3') ? '3v3' : (str_contains($haystack, '2v2') ? '2v2' : '1v1');
        if (str_contains($haystack, 'doble eliminacion') || str_contains($haystack, 'double elimination')) {
            $executionModel = 'double_elimination';
        } elseif ((str_contains($haystack, 'grupos') || str_contains($haystack, 'groups')) && (str_contains($haystack, 'eliminacion') || str_contains($haystack, 'knockout'))) {
            $executionModel = 'groups_knockout';
        } elseif (str_contains($haystack, 'fase de grupos') || str_contains($haystack, 'group stage')) {
            $executionModel = 'groups';
        } elseif ((str_contains($haystack, 'suizo') || str_contains($haystack, 'swiss')) && (str_contains($haystack, 'top cut') || str_contains($haystack, 'eliminacion'))) {
            $executionModel = 'swiss_top_cut';
        } elseif (str_contains($haystack, 'suizo') || str_contains($haystack, 'swiss')) {
            $executionModel = 'swiss';
        } elseif (str_contains($haystack, 'ida y vuelta') || str_contains($haystack, 'double round robin')) {
            $executionModel = 'double_round_robin';
        } elseif (str_contains($haystack, 'round robin') || str_contains($haystack, 'todos contra todos') || str_contains($haystack, 'tabla por puntos')) {
            $executionModel = 'round_robin';
        } elseif (str_contains($haystack, 'liga + playoffs') || str_contains($haystack, 'temporada') || str_contains($haystack, 'season')) {
            $executionModel = 'season_playoffs';
        } elseif (str_contains($haystack, 'ladder') || str_contains($haystack, 'clasificacion continua')) {
            $executionModel = 'ladder';
        } elseif (str_contains($haystack, 'king of the hill') || str_contains($haystack, 'ganador permanece')) {
            $executionModel = 'king_of_the_hill';
        } elseif (str_contains($haystack, 'gauntlet')) {
            $executionModel = 'gauntlet';
        } elseif (str_contains($haystack, 'flight')) {
            $executionModel = 'flights';
        } elseif (str_contains($haystack, 'playoff') || str_contains($haystack, 'top cut') || str_contains($haystack, 'bracket') || str_contains($haystack, 'llave') || str_contains($haystack, 'knockout') || str_contains($haystack, 'eliminacion')) {
            $executionModel = 'single_elimination';
        } elseif (str_contains($haystack, 'partida unica') || str_contains($haystack, 'serie') || str_contains($haystack, 'campeones enfrentados')) {
            $executionModel = 'series';
        } else {
            $executionModel = 'manual';
        }
        $schedule = in_array($executionModel, ['round_robin', 'double_round_robin', 'league_table'], true) ? 'auto_round_robin'
            : (in_array($executionModel, ['swiss', 'swiss_top_cut'], true) ? 'auto_swiss'
            : (in_array($executionModel, ['single_elimination', 'double_elimination', 'groups_knockout', 'groups', 'flights', 'season_playoffs'], true) ? 'auto_knockout' : 'manual'));
        $participationCode = $matchFormat === '3v3' ? 'TEAM_3V3' : ($matchFormat === '2v2' ? 'TAG_2V2' : 'INDIVIDUAL_1V1');
        $constructionCode = str_contains($haystack, 'sealed') ? 'SEALED' : (str_contains($haystack, 'draft') ? 'DRAFT' : (str_contains($haystack, 'structure deck') ? 'STRUCTURE_DECK' : (str_contains($haystack, 'digital') || str_contains($haystack, 'master duel') || str_contains($haystack, 'duel links') ? 'DIGITAL' : 'CONSTRUCTED')));
        $environmentCode = str_contains($haystack, 'remote') || str_contains($haystack, 'remoto') ? 'REMOTE_DUEL' : (str_contains($haystack, 'online') ? 'ONLINE' : 'PRESENCIAL');
        $levelCode = $type === 'world' ? 'WORLD' : ($type === 'friendly' ? 'EXHIBITION' : (str_contains($haystack, 'premier') ? 'PREMIER' : (str_contains($haystack, 'oficial') || str_contains($haystack, 'official') ? 'OFFICIAL' : (str_contains($haystack, 'principiante') || str_contains($haystack, 'beginner') ? 'BEGINNER' : (str_contains($haystack, 'academy') || str_contains($haystack, 'formacion') ? 'ACADEMY' : ($type === 'special' ? 'SPECIAL' : 'COMPETITIVE'))))));
        $eventFlow = $type === 'qualifier' ? 'QUALIFIES_TO_EVENT' : ($type === 'league' ? 'SEASON' : ($type === 'final' ? 'RECEIVES_FROM_EVENT' : ($type === 'world' ? 'CHAMPIONSHIP_PATH' : 'STANDALONE')));
        $participantSource = $type === 'final' || $type === 'world' ? 'PREVIOUS_EVENT' : ($type === 'league' ? 'DIVISION' : 'MANUAL');
        $accessType = str_contains($haystack, 'last chance') ? 'LAST_CHANCE_QUALIFIER' : ($type === 'qualifier' ? 'QUALIFIER' : (str_contains($haystack, 'invit') ? 'INVITATIONAL' : 'OPEN'));
        $advancementRule = $type === 'cup' || $type === 'final' ? 'WINNER_ONLY' : ($type === 'qualifier' || $type === 'world' ? 'TOP_N' : 'MANUAL_SELECTION');
        $drawPolicy = in_array($type, ['cup', 'final', 'world'], true) ? 'NOT_ALLOWED' : 'USE_TIEBREAKER';
        $rankingImpact = $type === 'friendly' ? 'NONE' : ($type === 'world' ? 'BONUS' : 'FULL');
        $deckPolicy = in_array($ruleset, ['draft', 'sealed'], true) ? 'REQUIRED_REGISTRATION' : 'OPTIONAL_REGISTRATION';
        $phaseStructure = str_contains($haystack, 'grupos') && (str_contains($haystack, 'top cut') || str_contains($haystack, 'final')) ? 'GROUPS_TOP_CUT_FINAL'
            : (str_contains($haystack, 'temporada') || str_contains($haystack, 'playoff') ? 'SEASON_PLAYOFF_FINAL'
            : (str_contains($haystack, 'top cut') ? 'TOP_CUT'
            : (str_contains($haystack, 'final') ? 'FINAL'
            : (str_contains($haystack, 'grupo') ? 'GROUPS' : 'NONE'))));
        $template = [
            'code' => $code,
            'label' => $label,
            'type' => $type,
            'execution_model' => $executionModel,
            'system' => $system,
            'participation' => $participation,
            'construction' => $construction,
            'rules' => $rules,
            'environment' => $environment,
            'level' => $level,
            'ruleset' => $ruleset,
            'match_format' => $matchFormat,
            'participants_per_side' => $matchFormat === '3v3' ? 3 : ($matchFormat === '2v2' ? 2 : 1),
            'schedule' => $schedule,
            'deck_pool' => $type === 'league' ? 'division' : 'all_active',
            'participation_code' => $participationCode,
            'construction_code' => $constructionCode,
            'environment_code' => $environmentCode,
            'level_code' => $levelCode,
            'event_flow' => $eventFlow,
            'participant_source' => $participantSource,
            'access_type' => $accessType,
            'advancement_rule' => $advancementRule,
            'draw_policy' => $drawPolicy,
            'ranking_impact' => $rankingImpact,
            'deck_policy' => $deckPolicy,
            'phase_structure' => $phaseStructure,
            'format' => strtolower($system . ' | ' . $construction . ' | ' . $rules),
            'notes' => "Tipo de evento: {$label}\nModalidad de competencia: {$system}\nTipo de acceso: {$accessType}\nModelo de ejecucion: {$executionModel}\nParticipacion: {$participation}\nConstruccion: {$construction}\nReglas: {$rules}\nEntorno: {$environment}\nNivel: {$level}",
        ];
        if ($code === 'clasificatorio_divisiones_3_duelos') {
            $template = array_merge($template, [
                'execution_model' => 'swiss',
                'system' => 'Suizo 3 duelos por deck',
                'schedule' => 'auto_swiss',
                'deck_pool' => 'all_active',
                'event_flow' => 'QUALIFIES_TO_EVENT',
                'participant_source' => 'OPEN_REGISTRATION',
                'access_type' => 'OPEN',
                'advancement_rule' => 'RANKING_THRESHOLD',
                'draw_policy' => 'USE_TIEBREAKER',
                'ranking_impact' => 'FULL',
                'deck_policy' => 'DECK_LOCK_EVENT',
                'phase_structure' => 'NONE',
                'best_of' => 3,
                'qualifier_duels_per_deck' => 3,
                'qualifier_matches_per_day' => 1,
                'notes' => "Clasificatorio para crear divisiones.\nParticipan todos los decks activos.\nCada deck juega maximo 3 duelos BO3.\nEl sistema debe evitar repetir rival/equipo al programar.\nEl ranking final sirve como base para rangos de division.",
            ]);
        }
        return ygo_correct_template_contract($template);
    }, $rows);
}

function ygo_ruleset_version_option_label(array $versionOption): string
{
    $system = trim((string) (($versionOption['ruleset_label'] ?? '') ?: ($versionOption['system'] ?? '')));
    $version = trim((string) (($versionOption['version_label'] ?? '') ?: ($versionOption['version'] ?? '')));
    if ($system === '') {
        return $version;
    }
    if ($version === '' || stripos($version, $system) === 0) {
        return $version !== '' ? $version : $system;
    }
    return $system . ' - ' . $version;
}
