(function () {
  var token = document.querySelector('input[name="_csrf"]');
  var csrf = token ? token.value : "";

  document.querySelectorAll(".meni-upload").forEach(function (input) {
    input.addEventListener("change", async function () {
      var file = input.files && input.files[0];
      if (!file) return;
      var targetName = input.getAttribute("data-target");
      var field = document.querySelector('input[name="' + targetName + '"]');
      if (!field) return;
      input.disabled = true;
      try {
        var compress = window.compressImageForUpload || function (f) { return Promise.resolve(f); };
        var ready = await compress(file);
        var fd = new FormData();
        fd.append("file", ready, ready.name || file.name);
        fd.append("_csrf", csrf);
        var res = await fetch("/moj-meni/upload", { method: "POST", body: fd });
        var data = await res.json();
        if (data.url) {
          field.value = data.url;
          var previewId = field.getAttribute("data-preview");
          if (previewId) {
            var box = document.getElementById(previewId);
            if (box) box.innerHTML = '<img src="' + data.url + '" alt="">';
          }
        } else {
          alert(data.error || "Upload nije uspio.");
        }
      } catch (e) {
        alert("Greška pri uploadu.");
      }
      input.disabled = false;
      input.value = "";
    });
  });
})();
