<?php
declare(strict_types=1);

// Helper dependencies and early API routing; execution order is intentional.
require_once YGO_APP_ROOT . '/includes/deck_builder.php';
if ((string)($_GET['api'] ?? '') === 'deck_builder' && in_array((string)($_GET['op'] ?? ''), ['search','image','detail'], true)) ygo_card_catalog_api();

require_once dirname(YGO_APP_ROOT) . '/store-lib.php';
if ((string)($_GET['api'] ?? '') === 'deck_data') { require_once YGO_APP_ROOT.'/includes/decks/data-http.php'; ygo_data_http(); }
if ((string)($_GET['api'] ?? '') === 'deck_analysis_progress') { require_once YGO_APP_ROOT.'/includes/decks/analysis-progress-http.php'; ygo_analysis_progress_http(); }
if ((string)($_GET['api'] ?? '') === 'deck_attributes') { require_once YGO_APP_ROOT.'/includes/decks/attributes-http.php'; ygo_deck_attributes_http(); }
if ((string)($_GET['api'] ?? '') === 'combo_creator') { require_once YGO_APP_ROOT.'/includes/decks/combo-creator-http.php'; ygo_combo_http(); }
if ((string)($_GET['api'] ?? '') === 'news') { require_once YGO_APP_ROOT.'/includes/news_http.php'; ygo_news_http(); }
require_once YGO_APP_ROOT . '/includes/catalogs.php';
require_once YGO_APP_ROOT.'/includes/decks/public-metadata.php';
require_once YGO_APP_ROOT . '/includes/turns.php';
require_once YGO_APP_ROOT . '/includes/competitive_intelligence.php';
require_once YGO_APP_ROOT . '/includes/simple_pdf.php';
require_once YGO_APP_ROOT . '/includes/deck_analysis_baselines.php';
require_once YGO_APP_ROOT . '/includes/schedule_capacity.php';
require_once YGO_APP_ROOT . '/includes/seasons.php';
require_once YGO_APP_ROOT . '/includes/league_configuration.php';
require_once YGO_APP_ROOT . '/includes/fixed_calendar.php';
require_once YGO_APP_ROOT . '/includes/event_models.php';
require_once YGO_APP_ROOT . '/includes/library.php';
require_once YGO_APP_ROOT . '/includes/match_editor.php';
require_once YGO_APP_ROOT . '/includes/completion_times.php';
require_once YGO_APP_ROOT . '/includes/ui_labels.php';
require_once YGO_APP_ROOT . '/includes/event_audit.php';
require_once YGO_APP_ROOT . '/includes/event_logo.php';
require_once YGO_APP_ROOT . '/includes/event_progress.php';

sx_start();
date_default_timezone_set('America/Bogota');
const YGO_AUTO_SEED_COMPETITIONS = false;
const YGO_AUTO_SEED_MATCHES = false;
const YGO_AUTO_SEED_DIVISIONS = false;

