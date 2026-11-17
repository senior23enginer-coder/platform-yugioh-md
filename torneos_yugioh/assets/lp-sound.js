(function () {
  'use strict';
  const preference = 'stationx_lp_sound';
  let enabled = true, context = null, voices = [];
  try { enabled = localStorage.getItem(preference) !== 'off'; } catch (_) {}
  const button = document.querySelector('[data-result-lp-sound-toggle]');
  function render() {
    if (!button) return;
    button.setAttribute('aria-pressed', String(enabled));
    const label = enabled ? 'Silenciar sonido de puntos de vida' : 'Activar sonido de puntos de vida';
    button.setAttribute('aria-label', label); button.title = label;
    button.innerHTML = `<i class="bi ${enabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'}" aria-hidden="true"></i>`;
  }
  function stop() {
    voices.forEach(voice => { try { voice.stop(); } catch (_) {} }); voices = [];
  }
  async function change(beforeA, beforeB, afterA, afterB) {
    const changes = [Number(afterA) - Number(beforeA), Number(afterB) - Number(beforeB)].filter(n => Number.isFinite(n) && n !== 0);
    if (!enabled || !changes.length) return;
    try {
      const Audio = window.AudioContext || window.webkitAudioContext; if (!Audio) return;
      if (!context || context.state === 'closed') context = new Audio();
      if (context.state === 'suspended') await context.resume();
      if (!enabled || context.state !== 'running') return;
      stop();
      const increasing = (changes.reduce((a, b) => a + b, 0) || changes[0]) > 0;
      const now = context.currentTime, count = 7;
      for (let i = 0; i < count; i++) {
        const oscillator = context.createOscillator(), gain = context.createGain(), at = now + i * .055;
        oscillator.type = 'triangle';
        oscillator.frequency.setValueAtTime(increasing ? 280 + i * 115 : 980 - i * 120, at);
        gain.gain.setValueAtTime(0, at); gain.gain.linearRampToValueAtTime(.045, at + .004); gain.gain.exponentialRampToValueAtTime(.001, at + .048);
        oscillator.connect(gain); gain.connect(context.destination);
        oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); voices = voices.filter(v => v !== oscillator); };
        voices.push(oscillator); oscillator.start(at); oscillator.stop(at + .052);
      }
    } catch (_) { /* A blocked audio device must never interrupt LP calculations. */ }
  }
  button?.addEventListener('click', () => {
    enabled = !enabled;
    try { localStorage.setItem(preference, enabled ? 'on' : 'off'); } catch (_) {}
    if (!enabled) stop(); render();
  });
  document.querySelectorAll('[data-result-lp-close]').forEach(node => node.addEventListener('click', stop));
  document.querySelector('#resultCaptureModal')?.addEventListener('hide.bs.modal', stop);
  window.StationXLpSound = { change }; render();
})();
