(function () {
  var script = document.currentScript;
  var logUrl = (script && script.src) ? script.src.replace(/\/google-ads\/tracker\.js$/i, "/log.php") : "../log.php";

  document.addEventListener("click", function (e) {
    var el = e.target.closest("button, a");
    if (!el) return;

    fetch(logUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        tag: el.tagName,
        text: (el.innerText || "").trim().slice(0, 120),
        href: el.getAttribute("href") || "",
        id: el.id || "",
        classes: (el.className || "").trim().slice(0, 120),
        page: location.href
      })
    });
  });
})();
