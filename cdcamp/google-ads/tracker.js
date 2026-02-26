(function () {
  var script = document.currentScript;
  var logUrl = (script && script.src) ? script.src.replace(/\/google-ads\/tracker\.js$/i, "/log.php") : "../log.php";

  function send(data) {
    fetch(logUrl, {
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
      text: (el.innerText || "").trim().slice(0, 120),
      href: el.getAttribute("href") || "",
      id: el.id || "",
      classes: (el.className || "").trim().slice(0, 120),
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
