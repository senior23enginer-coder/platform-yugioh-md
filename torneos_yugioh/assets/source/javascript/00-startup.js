/**
 * startup: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
(function () {
  const data = window.DML_DATA || { divisions: {}, fixtures: {}, allDecks: [], teams: [], competitions: [], cards: [], media: [], honors: [], audit: [], events: [], rulesets: [], rulesetVersions: [], matchTypes: [], gameRules: [], gameRuleConditions: [], competitionDependencies: [], statusHistory: [], standingSnapshots: [], matchSnapshots: [], matchupAnalyses: [], matchupAnalysisHistory: [], deckAnalysisProfiles: [], deckAnalysisProfileHistory: [], medalUnlocks: [], medalProgress: [], rankingHistory: [], eventSummaries: [], checkins: [], metaHistory: [], eloHistory: [], settings: {} };
  const settings = data.settings || {};
  const OFFLINE_QUEUE_KEY = 'dml_offline_result_queue';
  const readOfflineQueue = () => {
    try {
      const value = JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
      if (Array.isArray(value)) return value;
      return value && Array.isArray(value.payload) ? [{ id: value.id || `legacy-${Date.now()}`, ...value }] : [];
    } catch { return []; }
  };
  const writeOfflineQueue = (queue) => localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
  const makeRequestId = () => (window.crypto?.randomUUID ? window.crypto.randomUUID() : `offline-${Date.now()}-${Math.random().toString(16).slice(2)}`);
  const serverToday = data.serverToday || new Date().toISOString().slice(0, 10);
  const appMain = document.querySelector('.app-main');
  const detachedEventScreen = document.querySelector('[data-screen="evento_ficha"]');
  if (appMain && detachedEventScreen && detachedEventScreen.parentElement !== appMain) {
    appMain.appendChild(detachedEventScreen);
  }
  const screens = Array.from(document.querySelectorAll('[data-screen]'));
  const routeButtons = Array.from(document.querySelectorAll('[data-route]'));
  const search = document.querySelector('[data-global-search]');
  const searchResults = document.querySelector('[data-search-results]');
  const searchPageInput = document.querySelector('[data-search-page-input]');
  const searchPageResults = document.querySelector('[data-search-page-results]');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const sidebarOpen = document.querySelector('[data-sidebar-open]');
  const sidebarCloseButtons = Array.from(document.querySelectorAll('[data-sidebar-close]'));
  const alertCenters = Array.from(document.querySelectorAll('[data-alert-center]'));
  const alertBadges = Array.from(document.querySelectorAll('[data-alert-badge]'));
  const alertModalNode = document.querySelector('[data-alert-modal]');
  const alertModalOpen = document.querySelector('[data-alert-modal-open]');
  const alertModal = alertModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(alertModalNode) : null;
  const officialDivisions = Object.keys(data.divisions || {}).filter((division) => Number(data.divisions[division].id || 0) > 0);
  let activeEventTypeShortcut = '';
