(function (root) {
  'use strict';
  const operators = { add: '+', subtract: '−', multiply: '×', divide: '÷' };
  const isOperator = token => Object.values(operators).includes(token);
  function evaluate(tokens) {
    const values = [], ops = [], priority = { '+': 1, '−': 1, '×': 2, '÷': 2 };
    function apply() {
      const op = ops.pop(), right = values.pop(), left = values.pop();
      if (op === '÷' && right === 0) throw Error('División entre cero');
      const value = op === '+' ? left + right : op === '−' ? left - right : op === '×' ? left * right : left / right;
      if (!Number.isFinite(value)) throw Error('Resultado no válido');
      values.push(value);
    }
    tokens.forEach(token => {
      if (!isOperator(token)) values.push(Number(token));
      else { while (ops.length && priority[ops[ops.length - 1]] >= priority[token]) apply(); ops.push(token); }
    });
    while (ops.length) apply();
    return Number((values[0] || 0).toFixed(6));
  }
  function step(state, key) {
    if (key === 'clear') return { display: '0', expression: '0', tokens: [], evaluated: false };
    let tokens = Array.isArray(state.tokens) ? [...state.tokens] : [], evaluated = false;
    if (/^\d$/.test(key)) {
      if (state.evaluated || state.display === 'Error') tokens = [];
      const last = tokens[tokens.length - 1];
      if (last === undefined || isOperator(last)) tokens.push(key);
      else if (last.length < 12) tokens[tokens.length - 1] = (last === '0' ? key : last + key);
    } else if (operators[key]) {
      if (state.display === 'Error') return state;
      if (state.evaluated) tokens = [state.display];
      if (!tokens.length) tokens = ['0'];
      if (isOperator(tokens[tokens.length - 1])) tokens[tokens.length - 1] = operators[key];
      else if (tokens.length < 63) tokens.push(operators[key]);
    } else if (key === 'equals') { evaluated = true; if (isOperator(tokens[tokens.length - 1])) tokens.pop(); }
    else return state;
    const complete = isOperator(tokens[tokens.length - 1]) ? tokens.slice(0, -1) : tokens;
    try {
      const display = String(evaluate(complete));
      const expression = (tokens.join(' ') || '0') + (evaluated ? ` = ${display}` : '');
      return { display, expression, tokens, evaluated };
    } catch (error) { return { display: 'Error', expression: `${tokens.join(' ')} — ${error.message}`, tokens, evaluated: true }; }
  }
  const api = { step, evaluate };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  if (!root.document) return;
  const calculator = document.querySelector('[data-result-standard-modal]');
  const victory = document.querySelector('[data-result-lp-victory-modal]');
  let active = null, returnFocus = null, pendingVictory = null;
  function close(accepted = false) {
    if (!active) return;
    active.hidden = true; active = null;
    const resolve = pendingVictory; pendingVictory = null;
    returnFocus?.focus({ preventScroll: true }); returnFocus = null;
    if (resolve) resolve(accepted);
  }
  function show(node) {
    if (!node || active) return false;
    returnFocus = document.activeElement; active = node; node.hidden = false;
    node.querySelector('[data-dialog-focus],button:not([data-dialog-close])')?.focus();
    return true;
  }
  document.querySelectorAll('[data-result-standard-open]').forEach(button => button.addEventListener('click', () => show(calculator)));
  document.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', () => close(false)));
  victory?.querySelector('[data-result-lp-victory-confirm]')?.addEventListener('click', () => close(true));
  document.addEventListener('keydown', event => {
    if (!active) return;
    if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); close(false); }
    if (event.key === 'Tab') {
      const buttons = [...active.querySelectorAll('button:not(:disabled),[tabindex="0"]')].filter(n => n.getClientRects().length);
      const first = buttons[0], last = buttons[buttons.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
  }, true);
  document.querySelector('#resultCaptureModal')?.addEventListener('hide.bs.modal', () => close(false));
  api.close = close;
  api.confirmVictory = ({ text }) => new Promise(resolve => {
    if (!victory || active) return resolve(false);
    victory.querySelector('[data-result-lp-victory-text]').textContent = text;
    if (!show(victory)) return resolve(false);
    pendingVictory = resolve;
  });
  root.StationXLpWorkspace = api;
})(typeof window !== 'undefined' ? window : globalThis);
