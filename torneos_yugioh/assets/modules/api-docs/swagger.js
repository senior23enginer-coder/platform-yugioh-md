/* Tokens live only in this page's memory. No storage, remote validator or background API calls. */
(() => {
  let csrfToken = "";
  let decklistToken = "";
  const apiRoot = new URL("../api/index.php/", window.location.href);
  window.ui = SwaggerUIBundle({
    url: "./openapi.json",
    dom_id: "#swagger-ui",
    deepLinking: true,
    validatorUrl: null,
    withCredentials: true,
    persistAuthorization: false,
    tryItOutEnabled: false,
    supportedSubmitMethods: ["get", "post"],
    defaultModelsExpandDepth: 0,
    requestInterceptor(request) {
      const url = new URL(request.url, window.location.href);
      if (
        url.origin !== apiRoot.origin ||
        !url.pathname.startsWith(apiRoot.pathname)
      )
        return request;
      if (request.method.toUpperCase() === "POST") {
        request.headers = request.headers || {};
        if (
          url.pathname.endsWith("/decklist") &&
          typeof request.body === "string"
        ) {
          try {
            const body = JSON.parse(request.body);
            if (!body.token && decklistToken) body.token = decklistToken;
            request.body = JSON.stringify(body);
          } catch (_) {
            /* The native controller reports malformed JSON. */
          }
        } else if (csrfToken && !request.headers["X-CSRF-Token"]) {
          request.headers["X-CSRF-Token"] = csrfToken;
        }
      }
      return request;
    },
    responseInterceptor(response) {
      const url = new URL(response.url || "", window.location.href);
      if (
        url.origin !== apiRoot.origin ||
        !url.pathname.startsWith(apiRoot.pathname)
      )
        return response;
      const body = response.obj;
      if (response.status === 200 && body && typeof body.token === "string") {
        if (url.pathname.endsWith("/decklist")) decklistToken = body.token;
        else {
          csrfToken = body.token;
          // An API-key scheme avoids a required header field blocking Execute before
          // requestInterceptor can supply the token captured from the preceding GET.
          if (window.ui) window.ui.preauthorizeApiKey("sessionCsrf", csrfToken);
        }
      }
      return response;
    },
  });
})();
