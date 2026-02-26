(function () {
  var LOG_URL = "https://api.hookly.org/google-ads/log.php";

  function send(data) {
    fetch(LOG_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    });
  }

  document.addEventListener("click", function (e) {
    var el = e.target.closest("button, a");
    if (!el) return;
    send({
      type: "click",
      tag: el.tagName,
      text: el.innerText.trim().slice(0, 120),
      href: el.getAttribute("href") || "",
      id: el.id || "",
      classes: el.className || "",
      page: location.href
    });
  });

  if (typeof document.hidden !== "undefined" && document.hidden) {
    document.addEventListener("visibilitychange", function () {
      if (!document.hidden) send({ type: "visit", page: location.href, referrer: document.referrer });
    });
  } else {
    send({ type: "visit", page: location.href, referrer: document.referrer });
  }
})();
