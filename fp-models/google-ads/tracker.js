(function () {
  var script = document.currentScript;
  var src = (script && script.src) ? script.src : '';
  var m = src.match(/\/([^\/]+)\/google-ads\/tracker\.js/);
  var project = (m && m[1]) ? m[1] : 'default';

  document.addEventListener("click", function (e) {
    var el = e.target.closest("button, a");
    if (!el) return;

    fetch("/log.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        project: project,
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
