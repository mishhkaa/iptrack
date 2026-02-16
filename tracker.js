(function () {
  document.addEventListener("click", function (e) {
    const el = e.target.closest("button, a");
    if (!el) return;

    fetch("https://api.hookly.org/google-ads/log.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        tag: el.tagName,
        text: el.innerText.trim().slice(0, 120),
        href: el.getAttribute("href") || "",
        id: el.id || "",
        classes: el.className || "",
        page: location.href
      })
    });
  });
})();
