/** Browser HTTP adapters with injected fetch and form constructors. */
// @runtime-start
(function (root) {
  "use strict";

  async function liveDuel({
    fetchResponse,
    createFormData,
    getUrl,
    matchId,
    revision,
    operation,
    payload,
  }) {
    const options = {
      cache: "no-store",
      headers: { Accept: "application/json" },
    };
    if (operation) {
      const body = createFormData();
      body.set("match_id", matchId);
      body.set("actor", "judge");
      body.set("operation", operation);
      body.set("revision", String(revision || 0));
      body.set("payload", JSON.stringify(payload));
      options.method = "POST";
      options.body = body;
    }
    const response = await fetchResponse(getUrl(), options);
    const data = await response.json();
    return { response, data };
  }

  function submitOffline(fetchResponse, url, body) {
    return fetchResponse(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body: body.toString(),
      credentials: "same-origin",
      redirect: "follow",
    });
  }

  function pollLive(fetchResponse) {
    return fetchResponse("./?api=live", {
      cache: "no-store",
      credentials: "same-origin",
    });
  }

  const api = Object.freeze({ liveDuel, submitOffline, pollLive });
  root.StationXBrowserTransport = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
