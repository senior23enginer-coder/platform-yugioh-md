(function () {
  "use strict";
  // Bootstrap supports one modal at a time. Wait for hidden, not a fixed delay.
  for (const target of document.querySelectorAll(
    "#matchSheetModal, #resultCaptureModal",
  )) {
    let pending = false;
    target.addEventListener("show.bs.modal", (event) => {
      const others = [...document.querySelectorAll(".modal")].filter(
        (modal) => modal !== target &&
          (modal.classList.contains("show") || modal.getAttribute("aria-modal") === "true"),
      );
      if (!others.length) return;
      event.preventDefault();
      if (pending) return;
      pending = true;
      Promise.all(others.map((modal) => new Promise((resolve) => {
        const instance = window.bootstrap.Modal.getOrCreateInstance(modal);
        modal.addEventListener("hidden.bs.modal", resolve, { once: true });
        if (instance._isTransitioning && instance._isShown) {
          modal.addEventListener("shown.bs.modal", () => instance.hide(), { once: true });
        } else {
          instance.hide();
        }
      }))).then(() => {
        pending = false;
        window.bootstrap.Modal.getOrCreateInstance(target).show();
      });
    });
  }
})();
